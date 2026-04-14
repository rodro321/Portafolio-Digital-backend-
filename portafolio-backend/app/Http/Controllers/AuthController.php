<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Usuario;
use App\Models\OauthAccount;

class AuthController extends Controller
{
    /**
     * POST /api/auth/registro
     * Campos: nombre, apellido, email, password
     */
    public function registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'   => 'required|string|max:80',
            'apellido' => 'required|string|max:80',
            'email'    => 'required|email|max:150',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $hash = Hash::make($request->password);

        $result = DB::select(
            "SELECT sp_registrar_usuario(?,?,?,?) AS result",
            [
                $request->nombre,
                $request->apellido,
                $request->email,
                $hash,
            ]
        );

        $data = json_decode($result[0]->result, true);

        if (!$data['ok']) {
            $status = $data['codigo'] === 'EMAIL_DUPLICADO' ? 409 : 400;
            return response()->json($data, $status);
        }

        // Obtener usuario recién creado para emitir token
        $usuario = Usuario::where('id_usuario', $data['id_usuario'])->first();
        $token   = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'ok'       => true,
            'mensaje'  => $data['mensaje'],
            'token'    => $token,
            'usuario'  => [
                'id_usuario' => $usuario->id_usuario,
                'nombre'     => $usuario->nombre,
                'apellido'   => $usuario->apellido,
                'email'      => $usuario->email,
            ],
        ], 201);
    }

    /**
     * POST /api/auth/login
     * Campos: email, password
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $result = DB::select(
            "SELECT sp_login_usuario(?) AS result",
            [$request->email]
        );

        $data = json_decode($result[0]->result, true);

        if (!$data['ok']) {
            $status = $data['codigo'] === 'CUENTA_INACTIVA' ? 403 : 401;
            return response()->json($data, $status);
        }

        $usr = $data['usuario'];

        // Verificar contraseña en Laravel (el SP solo devuelve el hash)
        if (!Hash::check($request->password, $usr['password_hash'])) {
            return response()->json([
                'ok'      => false,
                'codigo'  => 'CREDENCIALES_INVALIDAS',
                'mensaje' => 'Contraseña incorrecta',
            ], 401);
        }

        $usuario = Usuario::where('id_usuario', $usr['id_usuario'])->first();
        $token   = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'ok'      => true,
            'token'   => $token,
            'usuario' => [
                'id_usuario' => $usr['id_usuario'],
                'nombre'     => $usr['nombre'],
                'apellido'   => $usr['apellido'],
                'email'      => $usr['email'],
                'profesion'  => $usr['profesion'],
                'id_imagen'  => $usr['id_imagen'],
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true, 'mensaje' => 'Sesión cerrada correctamente']);
    }

    /**
     * GET /api/auth/me
     * Devuelve los datos del usuario autenticado.
     * El frontend usa este endpoint para restaurar la sesión con el token guardado.
     * Usa sp_obtener_perfil_usuario para una sola consulta a BD.
     */
    public function me(Request $request)
    {
        $usuario = $request->user();
        $id = $usuario->id_usuario;

        // Intentar usar el SP para obtener datos completos en una sola consulta
        try {
            $result = DB::select("SELECT sp_obtener_perfil_usuario(?) AS result", [$id]);
            $data   = json_decode($result[0]->result, true);

            if ($data['ok'] && isset($data['perfil'])) {
                $perfil = $data['perfil'];
                // Construir foto_url completa si existe
                if (!empty($perfil['foto_url'])) {
                    $perfil['foto_url'] = asset('storage/' . $perfil['foto_url']);
                }
                return response()->json([
                    'ok'      => true,
                    'usuario' => $perfil,
                ]);
            }
        } catch (\Exception $e) {
            // Si el SP no existe, usamos fallback manual
        }

        // Fallback: datos directos del modelo
        $fotoUrl = null;
        if ($usuario->id_imagen) {
            $imagen = DB::selectOne(
                "SELECT ruta FROM imagen WHERE id_imagen = ?",
                [$usuario->id_imagen]
            );
            if ($imagen) {
                $fotoUrl = asset('storage/' . $imagen->ruta);
            }
        }

        return response()->json([
            'ok'      => true,
            'usuario' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre'     => $usuario->nombre,
                'apellido'   => $usuario->apellido,
                'email'      => $usuario->email,
                'profesion'  => $usuario->profesion,
                'biografia'  => $usuario->biografia,
                'id_imagen'  => $usuario->id_imagen,
                'foto_url'   => $fotoUrl,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  GitHub OAuth
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/auth/github
     * Redirige al usuario a GitHub para autorización OAuth.
     */
    public function githubRedirect()
    {
        $url = Socialite::driver('github')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['ok' => true, 'url' => $url]);
    }

    /**
     * GET /api/auth/github/callback
     * GitHub redirige aquí con el code.
     * Crea o vincula el usuario y redirige al frontend con el token.
     * Usa los SPs para mantener consistencia (roles, auditoría, etc.).
     */
    public function githubCallback(Request $request)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        try {
            $githubUser = Socialite::driver('github')->stateless()->user();
        } catch (\Exception $e) {
            return redirect($frontendUrl . '/auth/callback?error=github_auth_failed');
        }

        $providerId = (string) $githubUser->getId();
        $email      = $githubUser->getEmail();

        // 1. Buscar usuario existente vinculado a este GitHub ID (usa SP)
        $buscar  = DB::select("SELECT sp_buscar_por_github(?) AS result", [$providerId]);
        $busData = json_decode($buscar[0]->result, true);

        if ($busData['ok']) {
            // Ya existe: actualizar tokens vía SP
            $usuario = Usuario::where('id_usuario', $busData['usuario']['id_usuario'])->first();
            DB::select("SELECT sp_vincular_github(?,?,?,?) AS result", [
                $usuario->id_usuario, $providerId, $githubUser->token, $githubUser->refreshToken,
            ]);
        } else {
            // 2. Verificar si existe un usuario con el mismo email
            $usuario = $email ? Usuario::where('email', $email)->first() : null;

            if (!$usuario) {
                // 3. Crear nuevo usuario vía SP (asigna rol 'usuario' automáticamente)
                $nameParts      = explode(' ', $githubUser->getName() ?? $githubUser->getNickname(), 2);
                $nombre         = $nameParts[0] ?? 'Usuario';
                $apellido       = $nameParts[1] ?? 'GitHub';
                $randomPassword = Hash::make(bin2hex(random_bytes(16)));

                $regResult = DB::select("SELECT sp_registrar_usuario(?,?,?,?) AS result", [
                    $nombre, $apellido, $email ?? $providerId . '@github.oauth', $randomPassword,
                ]);
                $regData = json_decode($regResult[0]->result, true);

                if (!$regData['ok']) {
                    return redirect($frontendUrl . '/auth/callback?error=registro_fallido');
                }

                $usuario = Usuario::where('id_usuario', $regData['id_usuario'])->first();
            }

            // 4. Vincular cuenta GitHub vía SP
            DB::select("SELECT sp_vincular_github(?,?,?,?) AS result", [
                $usuario->id_usuario, $providerId, $githubUser->token, $githubUser->refreshToken,
            ]);
        }

        // 5. Descargar foto de perfil de GitHub (si el usuario no tiene una)
        if (!$usuario->id_imagen && $githubUser->getAvatar()) {
            try {
                $avatarUrl  = $githubUser->getAvatar();
                $imgContent = file_get_contents($avatarUrl);

                if ($imgContent) {
                    $filename = 'github_' . $usuario->id_usuario . '_' . time() . '.jpg';
                    $path     = 'fotos_perfil/' . $filename;

                    Storage::disk('public')->put($path, $imgContent);

                    $tamanioKb = (int) round(strlen($imgContent) / 1024);
                    DB::select("SELECT sp_actualizar_foto_perfil(?,?,?,?,?) AS result", [
                        $usuario->id_usuario, $path, $filename, 'image/jpeg', $tamanioKb,
                    ]);
                }
            } catch (\Exception $e) {
                // No bloquear el login si falla la descarga de foto
                \Log::warning('No se pudo descargar avatar de GitHub: ' . $e->getMessage());
            }
        }

        // Emitir token Sanctum
        $token = $usuario->createToken('github_auth')->plainTextToken;

        return redirect($frontendUrl . '/auth/callback?token=' . $token);
    }
}
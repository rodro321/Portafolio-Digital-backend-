<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

class AuthController extends Controller
{
    protected $usuarioService;

    public function __construct(UsuarioService $usuarioService)
    {
        $this->usuarioService = $usuarioService;
    }

    /**
     * Registro con email/password
     */
    public function register(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:80',
            'apellido' => 'required|string|max:80',
            'email' => 'required|email|max:150',
            'password' => 'required|string|min:6',
            'profesion' => 'nullable|string|max:120',
        ]);

        $respuesta = $this->usuarioService->registrar($request->all());

        if (!$respuesta['ok']) {
            return response()->json($respuesta, 400);
        }

        // Autenticar automáticamente
        $user = User::find($respuesta['id_usuario']);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id_usuario,
                'nombre' => $user->nombre,
                'email' => $user->email,
            ]
        ], 201);
    }

    /**
     * Login con email/password
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $respuesta = $this->usuarioService->buscarPorEmail($request->email);

        if (!$respuesta['ok']) {
            return response()->json($respuesta, 401);
        }

        $usuario = $respuesta['usuario'];
        if (!Hash::check($request->password, $usuario['password_hash'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Credenciales inválidas'], 401);
        }

        $user = User::find($usuario['id_usuario']);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id_usuario,
                'nombre' => $user->nombre,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true, 'mensaje' => 'Sesión cerrada']);
    }

    /**
     * Redirigir a GitHub para OAuth
     */
    public function redirectToGitHub()
    {
        return Socialite::driver('github')->redirect();
    }

    /**
     * Callback de GitHub OAuth
     */
    public function handleGitHubCallback(Request $request)
    {
        try {
            $githubUser = Socialite::driver('github')->user();

            \Log::info('GitHub User:', [
                'id' => $githubUser->getId(),
                'email' => $githubUser->getEmail(),
                'name' => $githubUser->getName(),
            ]);

            $respuesta = $this->usuarioService->buscarPorGitHub($githubUser->getId());
            if ($respuesta['ok']) {
                $user = User::find($respuesta['usuario']['id_usuario']);
            } else {
                $user = User::where('email', $githubUser->getEmail())->first();
                if (!$user) {
                    $user = $this->usuarioService->crearDesdeGitHub([
                        'id' => $githubUser->getId(),
                        'name' => $githubUser->getName(),
                        'nickname' => $githubUser->getNickname(),
                        'email' => $githubUser->getEmail(),
                        'bio' => $githubUser->user['bio'] ?? null,
                    ]);
                } else {
                    $this->usuarioService->vincularGitHub($user->id_usuario, $githubUser->getId());
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            return redirect()->to($frontendUrl . '/auth/github/callback?token=' . $token);
        } catch (\Exception $e) {
            \Log::error('GitHub callback error: ' . $e->getMessage());
            return redirect()->to(env('FRONTEND_URL') . '/login?error=github_failed');
        }
    }
}
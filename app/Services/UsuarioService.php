<?php

namespace App\Services;

use App\Models\Rol;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Imagen;

class UsuarioService
{
    protected function setAuditContext(?int $userId): void
    {
        if ($userId) {
            DB::statement("SELECT set_config('app.usuario_actual', ?, true)", [$userId]);
        }
    }

    /**
     * Registrar nuevo usuario con email/password
     */
    public function registrar(array $data): array
    {
        $passwordHash = Hash::make($data['password']);

        $result = DB::selectOne(
            "SELECT sp_registrar_usuario(?, ?, ?, ?, ?, ?, ?, ?) AS respuesta",
            [
                $data['nombre'],
                $data['apellido'],
                $data['email'],
                $passwordHash,
                $data['biografia'] ?? null,
                $data['telefono'] ?? null,
                $data['ciudad'] ?? null,
                $data['pais'] ?? null
            ]
        );

        return json_decode($result->respuesta, true);
    }
    /**
     * Buscar usuario por email (para login)
     */
    public function buscarPorEmail(string $email): array
    {
        $result = DB::selectOne(
            "SELECT sp_login_usuario(?) AS respuesta",
            [$email]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Obtener perfil completo
     */
    public function obtenerPerfil(int $userId): array
    {
        $result = DB::selectOne(
            "SELECT sp_obtener_perfil_usuario(?) AS respuesta",
            [$userId]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Actualizar perfil
     */
    public function actualizarPerfil(int $userId, array $data): array
    {
        $this->setAuditContext($userId);

        $result = DB::selectOne(
            "SELECT sp_actualizar_perfil_usuario(?, ?, ?, ?, ?, ?, ?, ?) AS respuesta",
            [
                $userId,
                $data['nombre'] ?? null,
                $data['apellido'] ?? null,
                $data['profesion'] ?? null,
                $data['biografia'] ?? null,
                $data['telefono'] ?? null,
                $data['ciudad'] ?? null,
                $data['pais'] ?? null
            ]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Cambiar contraseña
     */
    public function cambiarPassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = User::find($userId);
        if (!$user || !Hash::check($currentPassword, $user->password_hash)) {
            return ['ok' => false, 'codigo' => 'PASSWORD_INCORRECTA', 'mensaje' => 'Contraseña actual incorrecta'];
        }

        $this->setAuditContext($userId);
        $newHash = Hash::make($newPassword);
        $currentHash = $user->password_hash;

        $result = DB::selectOne(
            "SELECT sp_cambiar_password(?, ?, ?) AS respuesta",
            [$userId, $currentHash, $newHash]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Actualizar foto de perfil
     */
    public function actualizarFoto(int $userId, string $ruta, ?string $nombre = null, ?string $tipo = null, ?int $tamanio = null): array
    {
        $this->setAuditContext($userId);

        $result = DB::selectOne(
            "SELECT sp_actualizar_foto_perfil(?, ?, ?, ?, ?) AS respuesta",
            [$userId, $ruta, $nombre, $tipo, $tamanio]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Desactivar cuenta
     */
    public function desactivar(int $userId): array
    {
        $this->setAuditContext($userId);

        $result = DB::selectOne(
            "SELECT sp_desactivar_usuario(?) AS respuesta",
            [$userId]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Vincular cuenta GitHub
     */
    public function vincularGitHub(int $userId, string $githubId, ?string $accessToken = null, ?string $refreshToken = null): array
    {
        $this->setAuditContext($userId);

        $result = DB::selectOne(
            "SELECT sp_vincular_github(?, ?, ?, ?) AS respuesta",
            [$userId, $githubId, $accessToken, $refreshToken]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Buscar usuario por GitHub ID
     */
    public function buscarPorGitHub(string $githubId): array
    {
        $result = DB::selectOne(
            "SELECT sp_buscar_por_github(?) AS respuesta",
            [$githubId]
        );

        return json_decode($result->respuesta, true);
    }

    /**
     * Crear usuario desde datos de GitHub (sin password)
     */
    public function crearDesdeGitHub(array $githubUser): User
    {
        $fullName = trim($githubUser['name'] ?? $githubUser['nickname'] ?? 'Usuario');
        $parts = preg_split('/\s+/', $fullName, 2);
        $nombre = $parts[0] ?? '';
        $apellido = $parts[1] ?? '';

        $user = User::create([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $githubUser['email'] ?? $githubUser['id'] . '@github.user',
            'password_hash' => Hash::make(uniqid()),
            'profesion' => $githubUser['bio'] ?? null,
            'activo' => true,
            'fecha_registro' => now()
        ]);

        // Asignar rol
        $rolUsuario = Rol::where('nombre', 'usuario')->first();
        if ($rolUsuario) {
            $user->roles()->attach($rolUsuario->id_rol);
        }

        // Vincular GitHub
        $this->vincularGitHub($user->id_usuario, $githubUser['id']);

        // Descargar y guardar foto de GitHub
        if (!empty($githubUser['avatar'])) {
            try {
                $avatarUrl = $githubUser['avatar'];
                \Log::info('Intentando descargar avatar desde: ' . $avatarUrl);

                // Configurar cliente HTTP con timeout y sin verificación SSL (solo desarrollo)
                $response = Http::timeout(15)
                    ->withoutVerifying()  // Útil en Windows con problemas de certificados
                    ->get($avatarUrl);

                if ($response->successful()) {
                    $contentType = $response->header('Content-Type');
                    $extension = 'jpg';
                    if (strpos($contentType, 'png') !== false) {
                        $extension = 'png';
                    } elseif (strpos($contentType, 'gif') !== false) {
                        $extension = 'gif';
                    }

                    $filename = 'avatar_' . $user->id_usuario . '_' . time() . '.' . $extension;
                    $path = 'avatars/' . $filename;

                    // Guardar en storage/app/public/avatars
                    Storage::disk('public')->put($path, $response->body());

                    // Crear registro en tabla imagen
                    $imagen = Imagen::create([
                        'ruta' => '/storage/' . $path,           // Ruta relativa accesible públicamente
                        'nombre' => $filename,
                        'tipo' => $contentType ?? 'image/jpeg',
                        'tamanio_kb' => (int) (strlen($response->body()) / 1024),
                        'fecha_subida' => now()
                    ]);

                    $user->id_imagen = $imagen->id_imagen;
                    $user->save();

                    \Log::info('Avatar guardado correctamente: ' . $path);
                } else {
                    \Log::error('Error HTTP al descargar avatar: Código ' . $response->status());
                }
            } catch (\Exception $e) {
                \Log::error('Excepción al descargar avatar de GitHub: ' . $e->getMessage());
            }
        }

        return $user;
    }
}
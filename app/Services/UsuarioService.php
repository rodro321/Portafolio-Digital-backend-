<?php

namespace App\Services;

use App\Models\Rol;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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
            "SELECT sp_registrar_usuario(?, ?, ?, ?, ?, ?, ?, ?, ?) AS respuesta",
            [
                $data['nombre'],
                $data['apellido'],
                $data['email'],
                $passwordHash,
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
        $user = User::create([
            'nombre' => $githubUser['name'] ?? explode(' ', $githubUser['nickname'] ?? 'Usuario')[0],
            'apellido' => $githubUser['name'] ? substr(strstr($githubUser['name'], ' '), 1) : '',
            'email' => $githubUser['email'] ?? $githubUser['id'] . '@github.user',
            'password_hash' => Hash::make(uniqid()), // contraseña aleatoria
            'profesion' => $githubUser['bio'] ?? null,
            'activo' => true,
            'fecha_registro' => now()
        ]);

        // Asignar rol usuario
        $rolUsuario = Rol::where('nombre', 'usuario')->first();
        if ($rolUsuario) {
            $user->roles()->attach($rolUsuario->id_rol);
        }

        // Vincular cuenta GitHub
        $this->vincularGitHub($user->id_usuario, $githubUser['id']);

        return $user;
    }
}
<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Provider personalizado para la tabla `usuario` de PostgreSQL.
 * La tabla usa `id_usuario` como PK y `password_hash` en vez de `password`.
 */
class UsuarioUserProvider extends EloquentUserProvider
{
    /**
     * Recuperar un usuario por su identificador primario (id_usuario).
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $model = $this->createModel();
        return $model->where($model->getAuthIdentifierName(), $identifier)->first();
    }

    /**
     * Recuperar un usuario por sus credenciales (email).
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || !array_key_exists('email', $credentials)) {
            return null;
        }

        $model = $this->createModel();
        return $model->where('email', $credentials['email'])->first();
    }

    /**
     * Validar credenciales contra password_hash.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'];
        return $this->hasher->check($plain, $user->getAuthPassword());
    }
}
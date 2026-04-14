<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // Nombre de la tabla en PostgreSQL
    protected $table      = 'usuario';
    protected $primaryKey = 'id_usuario';
    public    $timestamps = false;   // la tabla usa fecha_registro, no created_at/updated_at

    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'password_hash',
        'profesion',
        'biografia',
        'id_imagen',
        'activo',
    ];

    protected $hidden = [
        'password_hash',
    ];

    // Sanctum necesita el campo "password" para ciertos helpers internos;
    // mapeamos password_hash a ese atributo virtual.
    public function getPasswordAttribute(): string
    {
        return $this->password_hash;
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
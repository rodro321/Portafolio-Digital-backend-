<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'password_hash',
        'profesion',
        'biografia',
        'id_imagen',
        'telefono',
        'ciudad',
        'pais',
        'activo',
        'fecha_registro'
    ];

    protected $hidden = ['password_hash'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function imagen()
    {
        return $this->belongsTo(Imagen::class, 'id_imagen', 'id_imagen');
    }

    public function habilidades()
    {
        return $this->belongsToMany(
            Habilidad::class,
            'usuario_habilidad',
            'id_usuario',
            'id_habilidad'
        )->withPivot('nivel');
    }

    public function oauthAccounts()
    {
        return $this->hasMany(OAuthAccount::class, 'id_usuario', 'id_usuario');
    }

    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'rol_usuario',
            'id_usuario',
            'id_rol'
        );
    }
}
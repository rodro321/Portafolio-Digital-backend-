<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OauthAccount extends Model
{
    protected $table      = 'oauth_account';
    protected $primaryKey = 'id_oauth';
    public    $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'provider',
        'provider_user_id',
        'access_token',
        'refresh_token',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}

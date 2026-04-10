<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OAuthAccount extends Model
{
    protected $table = 'oauth_account';
    protected $primaryKey = 'id_oauth';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'provider',
        'provider_user_id',
        'access_token',
        'refresh_token',
        'created_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
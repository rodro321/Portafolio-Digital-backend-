<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UsuarioHabilidad extends Pivot
{
    protected $table = 'usuario_habilidad';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id_usuario', 'id_habilidad', 'nivel'];
}
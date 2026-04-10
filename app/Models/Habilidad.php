<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Habilidad extends Model
{
    protected $table = 'habilidad';
    protected $primaryKey = 'id_habilidad';
    public $timestamps = false;

    protected $fillable = ['nombre', 'tipo', 'categoria', 'descripcion'];

    public function usuarios()
    {
        return $this->belongsToMany(
            User::class,
            'usuario_habilidad',
            'id_habilidad',
            'id_usuario'
        )->withPivot('nivel');
    }
}
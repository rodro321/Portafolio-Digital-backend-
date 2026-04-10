<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Imagen extends Model
{
    protected $table = 'imagen';
    protected $primaryKey = 'id_imagen';
    public $timestamps = false;

    protected $fillable = ['ruta', 'nombre', 'tipo', 'tamanio_kb', 'fecha_subida'];
}
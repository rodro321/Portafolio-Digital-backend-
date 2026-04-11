<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerfilController extends Controller
{
    // T02-02: GET perfil del usuario
    public function show($id)
    {
        $usuario = DB::select("
            SELECT u.id_usuario, u.nombre, u.apellido, u.email, 
                   u.profesion, u.biografia, u.telefono, u.ciudad, u.pais,
                   i.ruta as foto_perfil
            FROM usuario u
            LEFT JOIN imagen i ON u.id_imagen = i.id_imagen
            WHERE u.id_usuario = ?
        ", [$id]);

        if (empty($usuario)) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($usuario[0]);
    }

    // T02-03: PUT actualizar datos del perfil
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre'    => 'required|string|max:80',
            'apellido'  => 'nullable|string|max:80',
            'profesion' => 'nullable|string|max:120',
            'biografia' => 'nullable|string',
            'telefono'  => 'nullable|string|max:20',
            'ciudad'    => 'nullable|string|max:80',
            'pais'      => 'nullable|string|max:80',
        ]);

        DB::update("
            UPDATE usuario SET
                nombre    = ?,
                apellido  = ?,
                profesion = ?,
                biografia = ?,
                telefono  = ?,
                ciudad    = ?,
                pais      = ?
            WHERE id_usuario = ?
        ", [
            $request->nombre,
            $request->apellido,
            $request->profesion,
            $request->biografia,
            $request->telefono,
            $request->ciudad,
            $request->pais,
            $id
        ]);

        return response()->json(['message' => 'Perfil actualizado correctamente']);
    }
}
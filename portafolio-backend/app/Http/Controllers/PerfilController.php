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
    // T02-04: Subida de foto de perfil
public function uploadFoto(Request $request, $id)
{
    $request->validate([
        'foto' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $archivo = $request->file('foto');
    $nombreArchivo = 'perfil_' . $id . '_' . time() . '.' . $archivo->getClientOriginalExtension();
    $ruta = $archivo->storeAs('fotos_perfil', $nombreArchivo, 'public');

    // Guardar en tabla imagen
    $idImagen = DB::selectOne("
        INSERT INTO imagen (ruta, nombre, tipo, tamanio_kb)
        VALUES (?, ?, ?, ?)
        RETURNING id_imagen
    ", [
        $ruta,
        $nombreArchivo,
        $archivo->getMimeType(),
        round($archivo->getSize() / 1024)
    ]);

    // Actualizar usuario con la nueva imagen
    DB::update("UPDATE usuario SET id_imagen = ? WHERE id_usuario = ?", 
        [$idImagen->id_imagen, $id]);

    return response()->json([
        'message' => 'Foto de perfil actualizada correctamente',
        'ruta' => $ruta
    ]);
}
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PerfilController extends Controller
{
    protected $usuarioService;

    public function __construct(UsuarioService $usuarioService)
    {
        $this->usuarioService = $usuarioService;
    }

    /**
     * Obtener perfil del usuario autenticado
     */
    public function show()
    {
        $userId = Auth::id();
        $respuesta = $this->usuarioService->obtenerPerfil($userId);
        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    /**
     * Actualizar perfil
     */
    public function update(Request $request)
    {
        $request->validate([
            'nombre' => 'sometimes|string|max:80',
            'apellido' => 'sometimes|string|max:80',
            'profesion' => 'sometimes|string|max:120',
            'biografia' => 'sometimes|string',
            'telefono' => 'sometimes|string|max:20',
            'ciudad' => 'sometimes|string|max:80',
            'pais' => 'sometimes|string|max:80',
        ]);

        $userId = Auth::id();
        $respuesta = $this->usuarioService->actualizarPerfil($userId, $request->only([
            'nombre', 'apellido', 'profesion', 'biografia', 'telefono', 'ciudad', 'pais'
        ]));

        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6',
        ]);

        $userId = Auth::id();
        $respuesta = $this->usuarioService->cambiarPassword(
            $userId,
            $request->current_password,
            $request->new_password
        );

        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    /**
     * Subir foto de perfil
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $userId = Auth::id();
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');
        $fullPath = Storage::url($path);

        $respuesta = $this->usuarioService->actualizarFoto(
            $userId,
            $fullPath,
            $file->getClientOriginalName(),
            $file->getMimeType(),
            (int)($file->getSize() / 1024)
        );

        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    /**
     * Desactivar cuenta
     */
    public function deactivate()
    {
        $userId = Auth::id();
        $respuesta = $this->usuarioService->desactivar($userId);

        if ($respuesta['ok']) {
            // Revocar todos los tokens
            Auth::user()->tokens()->delete();
        }

        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }
}
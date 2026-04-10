<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HabilidadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HabilidadController extends Controller
{
    protected $habilidadService;

    public function __construct(HabilidadService $habilidadService)
    {
        $this->habilidadService = $habilidadService;
    }

    public function catalogo()
    {
        $respuesta = $this->habilidadService->obtenerCatalogo();
        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    public function misHabilidades()
    {
        $userId = Auth::id();
        $respuesta = $this->habilidadService->listarHabilidadesUsuario($userId);
        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    public function agregar(Request $request)
    {
        $request->validate([
            'id_habilidad' => 'required|integer',
            'nivel'        => 'nullable|integer|min:0|max:100'
        ]);

        $userId = Auth::id();
        $respuesta = $this->habilidadService->agregarHabilidad(
            $userId,
            $request->id_habilidad,
            $request->nivel
        );

        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    public function actualizarNivel(Request $request)
    {
        $request->validate([
            'id_habilidad' => 'required|integer',
            'nivel'        => 'required|integer|min:0|max:100'
        ]);

        $userId = Auth::id();
        $respuesta = $this->habilidadService->editarNivel(
            $userId,
            $request->id_habilidad,
            $request->nivel
        );

        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }

    public function eliminar(Request $request)
    {
        $request->validate(['id_habilidad' => 'required|integer']);

        $userId = Auth::id();
        $respuesta = $this->habilidadService->eliminarHabilidad(
            $userId,
            $request->id_habilidad
        );

        return response()->json($respuesta, $respuesta['ok'] ? 200 : 400);
    }
}
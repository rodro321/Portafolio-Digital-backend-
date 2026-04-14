<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HabilidadController extends Controller
{
    /**
     * GET /api/habilidades/catalogo
     * Devuelve el catálogo completo (público, sin autenticación).
     */
    public function catalogo()
    {
        // Cache del catálogo por 10 minutos — raramente cambia
        $data = cache()->remember('catalogo_habilidades', 600, function () {
            $result = DB::select("SELECT sp_listar_catalogo_habilidades() AS result");
            return json_decode($result[0]->result, true);
        });

        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * GET /api/habilidades
     * Lista las habilidades del usuario autenticado.
     * El SP devuelve {tecnicas, blandas} por separado.
     * Unificamos en un solo array 'habilidades' con campo 'tipo' para el frontend.
     */
    public function listar(Request $request)
    {
        $id     = $request->user()->id_usuario;
        $result = DB::select("SELECT sp_listar_habilidades_usuario(?) AS result", [$id]);
        $data   = json_decode($result[0]->result, true);

        if ($data['ok']) {
            // Unificar técnicas y blandas en un solo array con 'tipo'
            $habilidades = [];
            foreach (($data['tecnicas'] ?? []) as $t) {
                $t['tipo'] = 'tecnica';
                $habilidades[] = $t;
            }
            foreach (($data['blandas'] ?? []) as $b) {
                $b['tipo'] = 'blanda';
                $habilidades[] = $b;
            }
            $data['habilidades'] = $habilidades;
        }

        return response()->json($data, $data['ok'] ? 200 : 404);
    }

    /**
     * POST /api/habilidades
     * Vincula una habilidad del catálogo al perfil.
     * Body: { id_habilidad, nivel? }
     */
    public function agregar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_habilidad' => 'required|integer',
            'nivel'        => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id = $request->user()->id_usuario;

        $data = DB::transaction(function () use ($id, $request) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_agregar_habilidad_usuario(?,?,?) AS result",
                [$id, $request->id_habilidad, $request->nivel]
            );

            return json_decode($result[0]->result, true);
        });

        $codigo = $data['codigo'] ?? '';
        if ($codigo === 'CREADO') {
            $status = 201;
        } elseif ($codigo === 'DUPLICADO') {
            $status = 409;
        } else {
            $status = $data['ok'] ? 200 : 400;
        }

        return response()->json($data, $status);
    }

    /**
     * POST /api/habilidades/personalizada
     * Crea una habilidad personalizada y la vincula.
     * Body: { nombre, tipo, nivel? }
     */
    public function agregarPersonalizada(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:80',
            'tipo'   => 'required|in:tecnica,blanda',
            'nivel'  => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id = $request->user()->id_usuario;

        $data = DB::transaction(function () use ($id, $request) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_agregar_habilidad_personalizada(?,?,?,?) AS result",
                [$id, $request->nombre, $request->tipo, $request->nivel]
            );

            return json_decode($result[0]->result, true);
        });

        return response()->json($data, $data['ok'] ? 201 : 400);
    }

    /**
     * PUT /api/habilidades/{id_habilidad}/nivel
     * Actualiza el nivel de una habilidad técnica.
     * Body: { nivel }
     */
    public function editarNivel(Request $request, int $idHabilidad)
    {
        $validator = Validator::make($request->all(), [
            'nivel' => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id = $request->user()->id_usuario;

        $data = DB::transaction(function () use ($id, $idHabilidad, $request) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_editar_nivel_habilidad(?,?,?) AS result",
                [$id, $idHabilidad, $request->nivel]
            );

            return json_decode($result[0]->result, true);
        });

        return response()->json($data, $data['ok'] ? 200 : 400);
    }

    /**
     * DELETE /api/habilidades/{id_habilidad}
     * Desvincula una habilidad del perfil.
     */
    public function eliminar(Request $request, int $idHabilidad)
    {
        $id = $request->user()->id_usuario;

        $data = DB::transaction(function () use ($id, $idHabilidad) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_eliminar_habilidad_usuario(?,?) AS result",
                [$id, $idHabilidad]
            );

            return json_decode($result[0]->result, true);
        });

        return response()->json($data, $data['ok'] ? 200 : 404);
    }

    /**
     * PUT /api/habilidades/sincronizar
     * Reemplaza TODAS las habilidades de un tipo en bloque.
     * Body: { tipo: 'tecnica'|'blanda', habilidades: [{id_habilidad, nivel?},...] }
     */
    public function sincronizar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo'         => 'required|in:tecnica,blanda',
            'habilidades'  => 'present|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id = $request->user()->id_usuario;
        $habilidadesJson = json_encode($request->habilidades ?: []);

        $data = DB::transaction(function () use ($id, $request, $habilidadesJson) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_sincronizar_habilidades(?,?,?::jsonb) AS result",
                [$id, $request->tipo, $habilidadesJson]
            );

            return json_decode($result[0]->result, true);
        });

        return response()->json($data, $data['ok'] ? 200 : 400);
    }
}
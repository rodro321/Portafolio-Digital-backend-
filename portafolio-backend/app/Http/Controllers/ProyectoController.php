<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProyectoController extends Controller
{
    /**
     * GET /api/proyectos
     * Lista todos los proyectos del usuario autenticado.
     */
    public function listar(Request $request)
    {
        $id     = $request->user()->id_usuario;
        $result = DB::select("SELECT sp_listar_proyectos_usuario(?) AS result", [$id]);
        $data   = json_decode($result[0]->result, true);

        // Mapear campos del SP a los nombres que usa el frontend
        if ($data['ok'] && !empty($data['proyectos'])) {
            $data['proyectos'] = array_map(function ($p) {
                // Alias: nombre → titulo, url_repositorio → link
                $p['titulo'] = $p['nombre'] ?? '';
                $p['link']   = $p['url_repositorio'] ?? '';

                if (!empty($p['imagen_portada'])) {
                    $p['imagen_portada_url'] = asset('storage/' . $p['imagen_portada']);
                }
                return $p;
            }, $data['proyectos']);
        }

        return response()->json($data);
    }

    /**
     * POST /api/proyectos
     * Crea un nuevo proyecto.
     * Body: { titulo, descripcion?, link? }
     */
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo'      => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'link'        => 'nullable|url|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id = $request->user()->id_usuario;

        $data = DB::transaction(function () use ($id, $request) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_crear_proyecto(?,?,?,?) AS result",
                [$id, $request->titulo, $request->descripcion, $request->link]
            );

            return json_decode($result[0]->result, true);
        });

        return response()->json($data, $data['ok'] ? 201 : 400);
    }

    /**
     * GET /api/proyectos/{id}
     * Devuelve el detalle completo de un proyecto.
     */
    public function obtener(Request $request, int $idProyecto)
    {
        $id     = $request->user()->id_usuario;
        $result = DB::select(
            "SELECT sp_obtener_proyecto(?,?) AS result",
            [$idProyecto, $id]
        );

        $data = json_decode($result[0]->result, true);

        if ($data['ok']) {
            // Alias campos
            $data['proyecto']['titulo'] = $data['proyecto']['nombre'] ?? '';
            $data['proyecto']['link']   = $data['proyecto']['url_repositorio'] ?? '';

            // Construir URLs completas de imágenes
            if (!empty($data['proyecto']['imagenes'])) {
                $data['proyecto']['imagenes'] = array_map(function ($img) {
                    $img['url'] = asset('storage/' . $img['ruta']);
                    return $img;
                }, $data['proyecto']['imagenes']);
            }
        }

        return response()->json($data, $data['ok'] ? 200 : 404);
    }

    /**
     * PUT /api/proyectos/{id}
     * Actualiza los datos básicos del proyecto.
     * Body: { titulo?, descripcion?, link? }
     */
    public function actualizar(Request $request, int $idProyecto)
    {
        $validator = Validator::make($request->all(), [
            'titulo'      => 'nullable|string|max:150',
            'descripcion' => 'nullable|string',
            'link'        => 'nullable|url|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id = $request->user()->id_usuario;

        $data = DB::transaction(function () use ($id, $idProyecto, $request) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_actualizar_proyecto(?,?,?,?,?) AS result",
                [$idProyecto, $id, $request->titulo, $request->descripcion, $request->link]
            );

            return json_decode($result[0]->result, true);
        });

        return response()->json($data, $data['ok'] ? 200 : 400);
    }

    /**
     * DELETE /api/proyectos/{id}
     * Elimina el proyecto y sus imágenes del almacenamiento.
     */
    public function eliminar(Request $request, int $idProyecto)
    {
        $id = $request->user()->id_usuario;

        // Obtener rutas de imágenes antes de eliminar
        $imagenes = DB::select(
            "SELECT i.ruta FROM imagen i
             JOIN proyecto_imagen pi2 ON pi2.id_imagen = i.id_imagen
             WHERE pi2.id_proyecto = ?",
            [$idProyecto]
        );

        $data = DB::transaction(function () use ($id, $idProyecto) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_eliminar_proyecto(?,?) AS result",
                [$idProyecto, $id]
            );

            return json_decode($result[0]->result, true);
        });

        if ($data['ok']) {
            // Borrar archivos físicos del storage
            foreach ($imagenes as $img) {
                Storage::disk('public')->delete($img->ruta);
            }
        }

        return response()->json($data, $data['ok'] ? 200 : 400);
    }

    /**
     * POST /api/proyectos/{id}/imagenes
     * Agrega una imagen de evidencia al proyecto.
     * Form-data: imagen (file)
     */
    public function agregarImagen(Request $request, int $idProyecto)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id   = $request->user()->id_usuario;
        $file = $request->file('imagen');

        $ruta      = $file->store("proyectos/{$idProyecto}", 'public');
        $nombre    = $file->getClientOriginalName();
        $tipo      = $file->getMimeType();
        $tamanioKb = (int) round($file->getSize() / 1024);

        $data = DB::transaction(function () use ($idProyecto, $id, $ruta, $nombre, $tipo, $tamanioKb) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_agregar_imagen_proyecto(?,?,?,?,?,?) AS result",
                [$idProyecto, $id, $ruta, $nombre, $tipo, $tamanioKb]
            );

            return json_decode($result[0]->result, true);
        });

        if ($data['ok']) {
            $data['url'] = asset('storage/' . $ruta);
        } else {
            // Si el SP falló, borrar el archivo que ya subimos
            Storage::disk('public')->delete($ruta);
        }

        return response()->json($data, $data['ok'] ? 201 : 400);
    }

    /**
     * DELETE /api/proyectos/{id}/imagenes/{id_imagen}
     * Elimina una imagen de evidencia.
     */
    public function eliminarImagen(Request $request, int $idProyecto, int $idImagen)
    {
        $id = $request->user()->id_usuario;

        $data = DB::transaction(function () use ($id, $idImagen, $idProyecto) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_eliminar_imagen_proyecto(?,?,?) AS result",
                [$idImagen, $idProyecto, $id]
            );

            return json_decode($result[0]->result, true);
        });

        if ($data['ok'] && !empty($data['ruta'])) {
            Storage::disk('public')->delete($data['ruta']);
        }

        return response()->json($data, $data['ok'] ? 200 : 404);
    }

    /**
     * PUT /api/proyectos/{id}/habilidades
     * Sincroniza los chips de habilidades del proyecto.
     * Body: { ids_habilidades: [1, 4, 7, ...] }
     */
    public function sincronizarHabilidades(Request $request, int $idProyecto)
    {
        $validator = Validator::make($request->all(), [
            'ids_habilidades' => 'required|array',
            'ids_habilidades.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errores' => $validator->errors()], 422);
        }

        $id      = $request->user()->id_usuario;
        $idsJson = json_encode($request->ids_habilidades);

        $data = DB::transaction(function () use ($idProyecto, $id, $idsJson) {
            DB::statement("SET LOCAL app.usuario_actual = '{$id}'");

            $result = DB::select(
                "SELECT sp_sincronizar_habilidades_proyecto(?,?,?::jsonb) AS result",
                [$idProyecto, $id, $idsJson]
            );

            return json_decode($result[0]->result, true);
        });

        return response()->json($data, $data['ok'] ? 200 : 400);
    }
}
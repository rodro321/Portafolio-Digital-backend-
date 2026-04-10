<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HabilidadService
{
    protected function setAuditContext(int $userId): void
    {
        DB::statement("SELECT set_config('app.usuario_actual', ?, true)", [$userId]);
    }

    public function obtenerCatalogo(): array
    {
        $result = DB::selectOne("SELECT sp_listar_catalogo_habilidades() AS respuesta");
        return json_decode($result->respuesta, true);
    }

    public function listarHabilidadesUsuario(int $userId): array
    {
        $result = DB::selectOne("SELECT sp_listar_habilidades_usuario(?) AS respuesta", [$userId]);
        return json_decode($result->respuesta, true);
    }

    public function agregarHabilidad(int $userId, int $habilidadId, ?int $nivel): array
    {
        $this->setAuditContext($userId);
        $result = DB::selectOne(
            "SELECT sp_agregar_habilidad_usuario(?, ?, ?) AS respuesta",
            [$userId, $habilidadId, $nivel]
        );
        return json_decode($result->respuesta, true);
    }

    public function editarNivel(int $userId, int $habilidadId, int $nuevoNivel): array
    {
        $this->setAuditContext($userId);
        $result = DB::selectOne(
            "SELECT sp_editar_nivel_habilidad(?, ?, ?) AS respuesta",
            [$userId, $habilidadId, $nuevoNivel]
        );
        return json_decode($result->respuesta, true);
    }

    public function eliminarHabilidad(int $userId, int $habilidadId): array
    {
        $this->setAuditContext($userId);
        $result = DB::selectOne(
            "SELECT sp_eliminar_habilidad_usuario(?, ?) AS respuesta",
            [$userId, $habilidadId]
        );
        return json_decode($result->respuesta, true);
    }
}
<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

/**
 * Crea los departamentos por defecto (Bancos, Tesorería, Obras, Contabilidad,
 * Facturación, Seguridad, Apoyo) para cada empresa existente que aún no los tenga.
 *
 * Idempotente: usa firstOrCreate, por lo que se puede correr varias veces
 * sin duplicar departamentos. Las empresas creadas después de correr este
 * seeder no reciben departamentos automáticamente — deben crearse manualmente
 * vía POST /api/departamentos o volviendo a correr este seeder.
 *
 * No se registra en DatabaseSeeder::run(); se corre manualmente con:
 * php artisan db:seed --class=DepartamentoSeeder
 */
class DepartamentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Empresa::all()->each(function (Empresa $empresa) {
            foreach (Departamento::DEPARTAMENTOS_DEFAULT as $nombre => $descripcion) {
                Departamento::firstOrCreate([
                    'empresa_id' => $empresa->id,
                    'nombre'     => $nombre,
                ], [
                    'descripcion' => $descripcion,
                    'activo'      => true,
                ]);
            }
        });
    }
}

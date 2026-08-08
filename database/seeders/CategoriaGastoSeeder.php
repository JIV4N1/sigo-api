<?php

namespace Database\Seeders;

use App\Models\CategoriaGasto;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

/**
 * Crea las categorías de gasto por defecto (Materiales, Mano de obra, Equipo,
 * Transporte, Otros) para cada empresa existente que aún no las tenga.
 *
 * Idempotente: usa firstOrCreate, por lo que se puede correr varias veces
 * sin duplicar categorías. Las empresas creadas después de correr este
 * seeder no reciben categorías automáticamente — deben crearse manualmente
 * vía POST /api/gastos-obra/categorias o volviendo a correr este seeder.
 */
class CategoriaGastoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Empresa::all()->each(function (Empresa $empresa) {
            foreach (CategoriaGasto::CATEGORIAS_DEFAULT as $nombre) {
                CategoriaGasto::firstOrCreate([
                    'empresa_id' => $empresa->id,
                    'nombre'     => $nombre,
                ]);
            }
        });
    }
}

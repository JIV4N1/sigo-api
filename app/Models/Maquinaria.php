<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla de maquinaria del sistema SIGO.
 *
 * Representa el equipo pesado/maquinaria disponible para renta,
 * asociado a una empresa, con su tarifa por hora.
 */
class Maquinaria extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'maquinaria';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'unidad_renta',
        'precio_hora',
        'empresa_id',
        'activo',
    ];

    /**
     * Casts de atributos para conversión de tipos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_hora' => 'float',
            'activo'      => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla de materiales del sistema SIGO.
 *
 * Representa los insumos y materiales de construcción asociados
 * a una empresa, con su precio, stock y proveedor asignado.
 */
class Material extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'materiales';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'unidad_medida',
        'precio_compra',
        'precio_venta',
        'stock_actual',
        'stock_minimo',
        'proveedor_id',
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
            'precio_compra' => 'float',
            'precio_venta'  => 'float',
            'stock_actual'  => 'float',
            'stock_minimo'  => 'float',
            'activo'        => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: un material pertenece a un proveedor.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}

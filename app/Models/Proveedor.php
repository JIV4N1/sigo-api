<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla de proveedores del sistema SIGO.
 *
 * Representa a las empresas o personas que suministran materiales
 * a las obras administradas por cada empresa cliente.
 */
class Proveedor extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'proveedores';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'contacto',
        'telefono',
        'email',
        'direccion',
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
            'activo' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: un proveedor puede suministrar muchos materiales.
     */
    public function materiales(): HasMany
    {
        return $this->hasMany(Material::class, 'proveedor_id');
    }
}

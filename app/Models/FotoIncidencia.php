<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo para las fotos de evidencia adjuntas a las incidencias del sistema SIGO.
 *
 * Cada foto almacena la ruta relativa en el disco público y opcionalmente
 * las coordenadas GPS donde fue tomada.
 */
class FotoIncidencia extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'fotos_incidencia';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incidencia_id',
        'ruta_imagen',
        'descripcion',
        'latitud',
        'longitud',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitud'  => 'float',
            'longitud' => 'float',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: una foto pertenece a una incidencia.
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class, 'incidencia_id');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Retorna la URL pública completa de la imagen almacenada en el disco público.
     */
    public function getUrlImagenAttribute(): string
    {
        return Storage::disk('public')->url($this->ruta_imagen);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo para las fotos adjuntas a los reportes diarios del sistema SIGO.
 *
 * Cada foto se almacena en el disco público y registra metadatos como
 * su categoría, coordenadas GPS y si es la imagen principal del reporte.
 */
class FotoReporte extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'fotos_reporte';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reporte_id',
        'ruta_imagen',
        'descripcion',
        'categoria',
        'es_principal',
        'latitud',
        'longitud',
        'tomada_el',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'latitud'      => 'float',
            'longitud'     => 'float',
            'tomada_el'    => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: una foto pertenece a un reporte diario.
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteDiario::class, 'reporte_id');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Retorna la URL pública completa de la imagen almacenada.
     *
     * Utiliza el accessor para que la URL siempre sea absoluta y accesible
     * desde la app móvil sin necesidad de construirla en el controlador.
     */
    public function getUrlImagenAttribute(): string
    {
        return Storage::disk('public')->url($this->ruta_imagen);
    }
}

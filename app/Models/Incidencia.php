<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para las incidencias reportadas en los proyectos del sistema SIGO.
 *
 * Una incidencia documenta un problema, riesgo o situación que requiere
 * atención durante la ejecución de la obra. Cuenta con un ciclo de vida
 * formal (abierta → en_progreso → resuelta → cerrada) y un historial
 * completo de todos los cambios realizados.
 */
class Incidencia extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'incidencias';

    // -------------------------------------------------------------------------
    // Constantes del dominio
    // -------------------------------------------------------------------------

    /** Estados posibles de una incidencia */
    public const ESTADO_ABIERTA     = 'abierta';
    public const ESTADO_EN_PROGRESO = 'en_progreso';
    public const ESTADO_RESUELTA    = 'resuelta';
    public const ESTADO_CERRADA     = 'cerrada';

    /** Severidades posibles de una incidencia */
    public const SEVERIDAD_BAJA    = 'baja';
    public const SEVERIDAD_MEDIA   = 'media';
    public const SEVERIDAD_ALTA    = 'alta';
    public const SEVERIDAD_CRITICA = 'critica';

    /** Categorías posibles de una incidencia */
    public const CATEGORIA_SEGURIDAD      = 'seguridad';
    public const CATEGORIA_FALTA_MATERIAL = 'falta_material';
    public const CATEGORIA_FALLA_EQUIPO   = 'falla_equipo';
    public const CATEGORIA_CLIMA          = 'clima';
    public const CATEGORIA_OTRO           = 'otro';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'proyecto_id',
        'reportado_por',
        'asignado_a',
        'titulo',
        'descripcion',
        'categoria',
        'severidad',
        'estado',
        'latitud',
        'longitud',
        'ubicacion_descripcion',
        'resuelta_el',
    ];

    /**
     * Casts de atributos para conversión de tipos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitud'     => 'float',
            'longitud'    => 'float',
            'resuelta_el' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: una incidencia pertenece a un proyecto.
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /**
     * Relación: usuario que reportó la incidencia.
     */
    public function reportante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'reportado_por');
    }

    /**
     * Relación: usuario asignado para resolver la incidencia (puede ser null).
     */
    public function asignado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asignado_a');
    }

    /**
     * Relación: una incidencia puede tener fotos de evidencia adjuntas.
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoIncidencia::class, 'incidencia_id');
    }

    /**
     * Relación: historial cronológico de acciones sobre la incidencia.
     */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialIncidencia::class, 'incidencia_id');
    }

    /**
     * Relación: comentarios realizados por miembros del equipo.
     */
    public function comentarios(): HasMany
    {
        return $this->hasMany(ComentarioIncidencia::class, 'incidencia_id');
    }
}

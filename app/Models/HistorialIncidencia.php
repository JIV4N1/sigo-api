<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para el historial de cambios de una incidencia del sistema SIGO.
 *
 * Cada entrada documenta una acción realizada sobre la incidencia (creación,
 * cambio de estado, asignación, comentario, resolución o cierre), con el
 * usuario responsable y metadatos adicionales en formato JSONB.
 */
class HistorialIncidencia extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'historial_incidencia';

    /**
     * No se actualiza la columna updated_at (el historial es inmutable).
     */
    public const UPDATED_AT = null;

    /**
     * Acciones posibles registrables en el historial.
     */
    public const ACCION_CREADA        = 'creada';
    public const ACCION_CAMBIO_ESTADO = 'cambio_estado';
    public const ACCION_ASIGNADA      = 'asignada';
    public const ACCION_COMENTADA     = 'comentada';
    public const ACCION_RESUELTA      = 'resuelta';
    public const ACCION_CERRADA       = 'cerrada';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incidencia_id',
        'usuario_id',
        'accion',
        'descripcion',
        'metadatos',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // El campo metadatos es JSONB en PostgreSQL; Laravel lo deserializa automáticamente
            'metadatos' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: una entrada de historial pertenece a una incidencia.
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class, 'incidencia_id');
    }

    /**
     * Relación: una entrada de historial fue generada por un usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

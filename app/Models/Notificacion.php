<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para las notificaciones de usuario del sistema SIGO.
 *
 * Cada notificación pertenece a un único usuario y se genera automáticamente
 * ante eventos de otros módulos (incidencias, reportes, proyectos, etc.).
 * `origen_id`/`origen_tipo` referencian de forma polimórfica el registro
 * relacionado, sin clave foránea (puede apuntar a distintas tablas).
 */
class Notificacion extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'notificaciones';

    // -------------------------------------------------------------------------
    // Constantes del dominio
    // -------------------------------------------------------------------------

    /** Tipos posibles de notificación */
    public const TIPO_INCIDENCIA_ASIGNADA = 'incidencia_asignada';
    public const TIPO_INCIDENCIA_RESUELTA = 'incidencia_resuelta';
    public const TIPO_REPORTE_VALIDADO    = 'reporte_validado';
    public const TIPO_PROYECTO_ASIGNADO   = 'proyecto_asignado';
    public const TIPO_RECORDATORIO        = 'recordatorio';

    public const TIPOS = [
        self::TIPO_INCIDENCIA_ASIGNADA,
        self::TIPO_INCIDENCIA_RESUELTA,
        self::TIPO_REPORTE_VALIDADO,
        self::TIPO_PROYECTO_ASIGNADO,
        self::TIPO_RECORDATORIO,
    ];

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'titulo',
        'descripcion',
        'tipo',
        'leida',
        'link',
        'origen_id',
        'origen_tipo',
    ];

    /**
     * Casts de atributos para conversión de tipos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leida' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: la notificación pertenece a un usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Crea una notificación para un usuario. Punto de entrada único usado por
     * otros módulos (incidencias, reportes, etc.) para generar notificaciones
     * ante eventos del sistema.
     */
    public static function crear(
        int $usuarioId,
        string $tipo,
        string $titulo,
        string $descripcion,
        ?string $link = null,
        ?int $origenId = null,
        ?string $origenTipo = null,
    ): self {
        return self::create([
            'usuario_id'  => $usuarioId,
            'tipo'        => $tipo,
            'titulo'      => $titulo,
            'descripcion' => $descripcion,
            'link'        => $link,
            'origen_id'   => $origenId,
            'origen_tipo' => $origenTipo,
            'leida'       => false,
        ]);
    }
}

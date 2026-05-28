<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para los comentarios de las incidencias del sistema SIGO.
 *
 * Los comentarios permiten a los miembros del proyecto comunicarse sobre
 * el avance de resolución de una incidencia sin modificar su estado formal.
 */
class ComentarioIncidencia extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'comentarios_incidencia';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incidencia_id',
        'usuario_id',
        'comentario',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: un comentario pertenece a una incidencia.
     */
    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class, 'incidencia_id');
    }

    /**
     * Relación: un comentario fue escrito por un usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla de proyectos del sistema SIGO.
 *
 * Representa una obra civil con su equipo de trabajo, reportes de avance
 * e incidencias registradas durante la ejecución.
 */
class Proyecto extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'proyectos';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'ubicacion',
        'descripcion',
        'avance',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'creador_id',
        'cliente_id',
    ];

    /**
     * Casts de atributos para conversión de tipos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
            'avance'       => 'float',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: un proyecto tiene muchos usuarios asignados.
     *
     * La tabla pivote expone los campos adicionales:
     * - rol_en_proyecto: el rol que desempeña el usuario en este proyecto
     * - asignado_el: fecha en que fue asignado
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'proyecto_usuario', 'proyecto_id', 'usuario_id')
                    ->withPivot('rol_en_proyecto', 'asignado_el')
                    ->withTimestamps();
    }

    /**
     * Relación: un proyecto tiene muchos reportes diarios de avance.
     */
    public function reportesDiarios(): HasMany
    {
        return $this->hasMany(ReporteDiario::class, 'proyecto_id');
    }

    /**
     * Relación: un proyecto tiene muchas incidencias reportadas.
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'proyecto_id');
    }

    /**
     * Relación: un proyecto fue creado por un usuario (administrador o supervisor).
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creador_id');
    }

    /**
     * Relación: un proyecto pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}

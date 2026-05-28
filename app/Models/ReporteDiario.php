<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo para los reportes diarios de avance de obra del sistema SIGO.
 *
 * Cada reporte corresponde a un día de trabajo en un proyecto específico
 * y es elaborado por un usuario (supervisor o residente). Puede ser
 * validado posteriormente por un usuario con permisos de validación.
 */
class ReporteDiario extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'reportes_diarios';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'proyecto_id',
        'usuario_id',
        'fecha_reporte',
        'turno',
        'categoria',
        'avance',
        'descripcion',
        'condiciones_climaticas',
        'personal_presente',
        // Campos de validación
        'validado',
        'validado_por',
        'validado_el',
        'notas_validacion',
        // Sincronización con app móvil
        'sincronizado',
    ];

    /**
     * Casts de atributos para conversión de tipos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_reporte'     => 'date',
            'avance'            => 'float',
            'personal_presente' => 'integer',
            'validado'          => 'boolean',
            'validado_el'       => 'datetime',
            'sincronizado'      => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: un reporte pertenece a un proyecto.
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /**
     * Relación: un reporte fue elaborado por un usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Relación: un reporte puede haber sido validado por otro usuario.
     */
    public function validador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'validado_por');
    }

    /**
     * Relación: un reporte puede tener muchas fotos adjuntas.
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoReporte::class, 'reporte_id');
    }

    /**
     * Relación: foto principal del reporte (la marcada como es_principal = true).
     *
     * Se usa en el listado para mostrar una miniatura sin cargar todas las fotos.
     */
    public function fotoPrincipal(): HasOne
    {
        return $this->hasOne(FotoReporte::class, 'reporte_id')
                    ->where('es_principal', true)
                    ->orderBy('id');
    }
}

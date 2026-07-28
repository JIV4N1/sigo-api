<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionHorario extends Model
{
    protected $table = 'configuracion_horarios';

    /**
     * Nombres de los días de la semana (formato ISO-8601: 1 = lunes ... 7 = domingo).
     *
     * @var array<int, string>
     */
    public const DIAS = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miércoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sábado',
        7 => 'domingo',
    ];

    protected $fillable = [
        'empresa_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'es_laboral',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'es_laboral' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

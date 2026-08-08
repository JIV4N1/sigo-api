<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'asistencia';

    protected $fillable = [
        'usuario_id',
        'proyecto_id',
        'empresa_id',
        'fecha',
        'entrada',
        'comida_inicio',
        'comida_fin',
        'salida',
        'latitud_entrada',
        'longitud_entrada',
        'latitud_salida',
        'longitud_salida',
        'sincronizado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'entrada' => 'datetime',
            'comida_inicio' => 'datetime',
            'comida_fin' => 'datetime',
            'salida' => 'datetime',
            'sincronizado' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /**
     * Horas trabajadas del registro (entrada-salida, descontando comida).
     * Retorna 0 si el registro está incompleto (sin entrada o sin salida).
     */
    public function horasTrabajadas(): float
    {
        if (! $this->entrada || ! $this->salida) {
            return 0.0;
        }

        $minutos = $this->entrada->diffInMinutes($this->salida);

        if ($this->comida_inicio && $this->comida_fin) {
            $minutos -= $this->comida_inicio->diffInMinutes($this->comida_fin);
        }

        return round(max(0, $minutos) / 60, 2);
    }
}

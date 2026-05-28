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
            'fecha' => 'date',
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'folio',
        'cliente_id',
        'usuario_id',
        'fecha',
        'subtotal',
        'iva',
        'total',
        'estado',
        'observaciones',
        'empresa_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha'    => 'date',
            'subtotal' => 'float',
            'iva'      => 'float',
            'total'    => 'float',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaCotizacion::class, 'cotizacion_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cotizacion_id');
    }
}

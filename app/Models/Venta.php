<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'folio',
        'cliente_id',
        'usuario_id',
        'cotizacion_id',
        'fecha',
        'subtotal',
        'iva',
        'total',
        'metodo_pago',
        'estado',
        'observaciones',
        'empresa_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha'    => 'date:Y-m-d',
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
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaVenta::class, 'venta_id');
    }
}

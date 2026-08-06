<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    protected $table = 'pedidos';

    public const ESTADOS = ['pendiente', 'aprobado', 'rechazado', 'entregado'];

    protected $fillable = [
        'empresa_id',
        'folio',
        'cliente_id',
        'usuario_id',
        'fecha_pedido',
        'fecha_entrega',
        'estado',
        'subtotal',
        'iva',
        'total',
        'observaciones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pedido'  => 'date',
            'fecha_entrega' => 'date',
            'subtotal'      => 'float',
            'iva'           => 'float',
            'total'         => 'float',
            'activo'        => 'boolean',
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

    public function detalles(): HasMany
    {
        return $this->hasMany(PartidaPedido::class, 'pedido_id');
    }
}

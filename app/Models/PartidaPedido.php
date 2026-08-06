<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartidaPedido extends Model
{
    protected $table = 'partidas_pedido';

    public $timestamps = false;

    protected $fillable = [
        'pedido_id',
        'material_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'float',
            'precio_unitario' => 'float',
            'subtotal'        => 'float',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}

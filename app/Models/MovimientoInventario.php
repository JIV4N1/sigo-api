<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';
    
    // Disable updated_at since it's a history table, only created_at is relevant if that's the design
    public const UPDATED_AT = null;

    protected $fillable = [
        'material_id',
        'tipo_movimiento',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'float',
            'stock_anterior' => 'float',
            'stock_nuevo'    => 'float',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

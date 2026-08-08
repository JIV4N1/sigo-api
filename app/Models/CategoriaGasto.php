<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaGasto extends Model
{
    protected $table = 'categorias_gasto';

    public const CATEGORIAS_DEFAULT = [
        'Materiales',
        'Mano de obra',
        'Equipo',
        'Transporte',
        'Otros',
    ];

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(GastoObra::class, 'categoria_id');
    }
}

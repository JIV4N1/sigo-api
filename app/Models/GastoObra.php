<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoObra extends Model
{
    protected $table = 'gastos_obra';

    protected $fillable = [
        'empresa_id',
        'proyecto_id',
        'categoria_id',
        'proveedor_id',
        'monto',
        'fecha',
        'descripcion',
        'comprobante_path',
        'usuario_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'monto'  => 'float',
            'fecha'  => 'date:Y-m-d',
            'activo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaGasto::class, 'categoria_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

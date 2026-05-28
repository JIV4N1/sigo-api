<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'razon_social',
        'rfc',
        'nombre_contacto',
        'telefono',
        'email',
        'direccion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'cliente_id');
    }
}

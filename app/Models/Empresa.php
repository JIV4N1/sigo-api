<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'razon_social',
        'rfc',
        'direccion',
        'telefono',
        'email',
        'logo_path',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'empresa_usuario')
                    ->withPivot('rol_en_empresa', 'asignado_el');
    }

    public function proyectos()
    {
        return $this->hasMany(Proyecto::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function proveedores()
    {
        return $this->hasMany(Proveedor::class);
    }

    public function materiales()
    {
        return $this->hasMany(Material::class);
    }
}

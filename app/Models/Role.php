<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla de roles del sistema.
 */
class Role extends Model
{
    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'roles';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Relación: un rol tiene muchos usuarios.
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'role_id');
    }
}

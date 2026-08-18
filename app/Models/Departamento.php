<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $table = 'departamentos';

    public const DEPARTAMENTOS_DEFAULT = [
        'BANCOS'        => 'Gestión bancaria',
        'TESORERIA'     => 'Finanzas y pagos',
        'OBRAS'         => 'Proyectos y construcción',
        'CONTABILIDAD'  => 'Contabilidad general',
        'FACTURACION'   => 'Emisión de facturas',
        'SEGURIDAD'     => 'Seguridad en obras',
        'APOYO'         => 'Administración y soporte',
    ];

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'activo',
        'rol_id',
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

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'departamento_id');
    }

    /**
     * Relación: rol por defecto que se asigna a los usuarios de este departamento
     * al aprobarse su solicitud de registro.
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }
}

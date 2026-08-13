<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo principal de usuarios del sistema SIGO.
 *
 * Representa la tabla "usuarios" en la base de datos.
 */
class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'usuarios';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
        'nombre',
        'email',
        'password',
        'telefono',
        'foto_perfil',
        'rol_id',
        'activo',
        'ultimo_acceso',
        'empresa_activa_id',
        'departamento_id',
    ];

    /**
     * Atributos ocultos en la serialización (JSON).
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activo'            => 'boolean',
            'ultimo_acceso'     => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: un usuario pertenece a un rol.
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    /**
     * Relación: un usuario pertenece a muchos proyectos (tabla pivote proyecto_usuario).
     *
     * Incluye los campos adicionales del pivote:
     * - rol_en_proyecto: el rol que desempeña en el proyecto
     * - asignado_el: fecha de asignación
     * - activo: si la asignación está vigente
     */
    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'proyecto_usuario', 'usuario_id', 'proyecto_id')
                    ->withPivot('rol_en_proyecto', 'asignado_el', 'activo');
    }

    /**
     * Solo los proyectos con asignación activa (activo = true en el pivote).
     */
    public function proyectosActivos(): BelongsToMany
    {
        return $this->proyectos()->wherePivot('activo', true);
    }

    /**
     * Verifica si el usuario tiene alguno de los roles especificados.
     *
     * Ejemplo: $user->tieneRol(['gerente', 'administrador'])
     *
     * @param  string|array  $roles
     * @return bool
     */
    public function tieneRol(string|array $roles): bool
    {
        if (! $this->relationLoaded('rol')) {
            $this->load('rol');
        }

        $nombreRol = $this->rol?->nombre;

        return in_array($nombreRol, (array) $roles, true);
    }

    /**
     * Verifica si el usuario tiene el rol "superadmin", con control total
     * sobre todas las empresas del sistema (fuera del scoping multi-tenant
     * normal basado en empresa_id / empresa_activa_id).
     */
    public function esSuperadmin(): bool
    {
        return $this->tieneRol('superadmin');
    }

    /**
     * Relación: un usuario ha elaborado muchos reportes diarios.
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(ReporteDiario::class, 'usuario_id');
    }

    /**
     * Relación: incidencias reportadas por este usuario.
     */
    public function incidenciasReportadas(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'reportado_por');
    }

    /**
     * Relación: incidencias asignadas a este usuario para su resolución.
     */
    public function incidenciasAsignadas(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'asignado_a');
    }

    /**
     * Relación: un usuario tiene muchas asistencias.
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'usuario_id');
    }

    /**
     * Relación: la empresa principal o actual del usuario.
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Relación: empresa actualmente seleccionada por un administrador multi-empresa.
     */
    public function empresaActiva(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_activa_id');
    }

    /**
     * Relación: departamento al que pertenece el usuario dentro de su empresa.
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /**
     * Relación: todas las empresas a las que el usuario tiene acceso (útil para roles admin/multi-empresa).
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_usuario', 'usuario_id', 'empresa_id')
                    ->withPivot('rol_en_empresa', 'asignado_el');
    }
}


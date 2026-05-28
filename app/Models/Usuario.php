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
        'nombre',
        'email',
        'password',
        'telefono',
        'foto_perfil',
        'role_id',
        'activo',
        'ultimo_acceso',
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
            'password'          => 'hashed',
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
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Relación: un usuario pertenece a muchos proyectos (tabla pivote proyecto_usuario).
     */
    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'proyecto_usuario', 'usuario_id', 'proyecto_id');
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
}


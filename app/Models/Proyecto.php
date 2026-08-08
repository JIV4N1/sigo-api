<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo para la tabla de proyectos (obras) del sistema SIGO.
 *
 * Representa una obra civil con su equipo de trabajo, reportes de avance
 * e incidencias registradas durante la ejecución.
 *
 * Usa SoftDeletes para "desactivar" proyectos sin borrarlos físicamente.
 *
 * @property int         $id
 * @property string      $codigo         Código único autogenerado (OBR-XXXXXX)
 * @property string      $nombre
 * @property string|null $descripcion
 * @property string|null $ubicacion
 * @property float|null  $latitud
 * @property float|null  $longitud
 * @property \Carbon\Carbon $fecha_inicio
 * @property \Carbon\Carbon $fecha_fin
 * @property float|null  $presupuesto
 * @property float       $avance         Porcentaje 0-100
 * @property string      $estado         planeado|activo|en_curso|pausado|finalizado|cancelado
 * @property int|null    $cliente_id
 * @property int|null    $empresa_id
 * @property string|null $imagen_portada
 * @property int|null    $creado_por     FK → usuarios.id
 */
class Proyecto extends Model
{
    use SoftDeletes;

    /**
     * Atributos agregados a las respuestas JSON.
     *
     * @var list<string>
     */
    protected $appends = [
        'mi_rol',
    ];

    /**
     * Nombre de la tabla en la base de datos.
     */
    protected $table = 'proyectos';

    /**
     * Estados válidos para el proyecto.
     */
    public const ESTADOS = [
        'planeado',
        'activo',
        'en_curso',
        'pausado',
        'finalizado',
        'cancelado',
    ];

    /**
     * Roles válidos en el proyecto.
     */
    public const ROLES_PROYECTO = [
        'gerente',
        'supervisor',
        'ingeniero',
        'trabajador',
    ];

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'ubicacion',
        'latitud',
        'longitud',
        'fecha_inicio',
        'fecha_fin',
        'presupuesto',
        'avance',
        'estado',
        'cliente_id',
        'empresa_id',
        'imagen_portada',
        'creado_por',
    ];

    /**
     * Casts de atributos para conversión de tipos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin'    => 'date:Y-m-d',
            'avance'       => 'float',
            'presupuesto'  => 'float',
            'latitud'      => 'float',
            'longitud'     => 'float',
            'deleted_at'   => 'datetime',
        ];
    }

    /**
     * Atributo virtual para obtener el rol del usuario actual en el proyecto.
     * 
     * @return string|null
     */
    public function getMiRolAttribute(): ?string
    {
        // Retornar si fue asignado dinámicamente en el controlador
        if (array_key_exists('mi_rol', $this->attributes)) {
            return $this->attributes['mi_rol'];
        }

        // Si hay una sesión activa, intentar obtener el rol automáticamente
        if (auth()->check()) {
            $asignacion = $this->usuariosActivos()->where('usuario_id', auth()->id())->first();
            return $asignacion ? $asignacion->pivot->rol_en_proyecto : null;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Métodos Helper: Generación de Código
    // -------------------------------------------------------------------------

    /**
     * Genera el próximo código único de proyecto con formato OBR-XXXXXX.
     *
     * Busca el ID más alto de proyectos (incluyendo eliminados con soft delete)
     * y suma 1 para garantizar unicidad. El padding con ceros lleva hasta
     * 999,999 proyectos sin colisiones.
     *
     * Ejemplo: OBR-000001, OBR-000042, OBR-001337
     *
     * @return string Código único para el nuevo proyecto
     */
    public static function generarCodigo(): string
    {
        // Incluir soft-deleted para evitar colisiones de código
        $ultimoId = static::withTrashed()->max('id') ?? 0;
        $siguiente = $ultimoId + 1;

        return 'PROY-' . str_pad($siguiente, 3, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Métodos Helper: Gestión de Personal
    // -------------------------------------------------------------------------

    /**
     * Asigna un usuario al proyecto con un rol específico.
     *
     * Si el usuario ya fue asignado previamente (incluso inactivo),
     * actualiza su rol y reactiva la asignación en lugar de duplicarla.
     *
     * @param  int    $usuarioId  ID del usuario a asignar
     * @param  string $rol        Rol en el proyecto (supervisor, ingeniero, etc.)
     * @return void
     */
    public function asignarUsuario(int $usuarioId, string $rol = 'trabajador'): void
    {
        $this->usuarios()->syncWithoutDetaching([
            $usuarioId => [
                'rol_en_proyecto' => $rol,
                'asignado_el'     => now(),
                'activo'          => true,
            ],
        ]);

        // Si ya existía inactivo, reactivar
        $this->usuarios()->updateExistingPivot($usuarioId, [
            'rol_en_proyecto' => $rol,
            'activo'          => true,
            'asignado_el'     => now(),
        ]);
    }

    /**
     * Desactiva la asignación de un usuario en el proyecto.
     *
     * No elimina el registro de la tabla pivote, solo pone activo=false
     * para mantener el historial de participación.
     *
     * @param  int  $usuarioId  ID del usuario a remover
     * @return bool True si se desactivó correctamente, false si no estaba asignado
     */
    public function removerUsuario(int $usuarioId): bool
    {
        $asignado = $this->usuarios()
            ->wherePivot('usuario_id', $usuarioId)
            ->wherePivot('activo', true)
            ->exists();

        if (! $asignado) {
            return false;
        }

        $this->usuarios()->updateExistingPivot($usuarioId, ['activo' => false]);

        return true;
    }

    /**
     * Retorna los usuarios activos del proyecto filtrados por rol.
     *
     * @param  string  $rol  El rol a filtrar (supervisor, ingeniero, etc.)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUsuariosPorRol(string $rol)
    {
        return $this->usuarios()
            ->wherePivot('rol_en_proyecto', $rol)
            ->wherePivot('activo', true)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Relación: un proyecto tiene muchos usuarios asignados (activos).
     *
     * La tabla pivote expone:
     * - rol_en_proyecto: rol del usuario en este proyecto
     * - asignado_el: fecha en que fue asignado
     * - activo: si la asignación está vigente
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'proyecto_usuario', 'proyecto_id', 'usuario_id')
                    ->withPivot('rol_en_proyecto', 'asignado_el', 'activo');
    }

    /**
     * Usuarios activamente asignados (pivot activo = true).
     */
    public function usuariosActivos(): BelongsToMany
    {
        return $this->usuarios()->wherePivot('activo', true);
    }

    /**
     * Relación: un proyecto tiene muchos reportes diarios de avance.
     */
    public function reportesDiarios(): HasMany
    {
        return $this->hasMany(ReporteDiario::class, 'proyecto_id');
    }

    /**
     * Relación: un proyecto tiene muchas incidencias reportadas.
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'proyecto_id');
    }

    /**
     * Relación: un proyecto fue creado por un usuario (administrador o gerente).
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    /**
     * Relación: un proyecto pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Relación: un proyecto pertenece a una empresa.
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

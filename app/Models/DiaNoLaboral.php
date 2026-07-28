<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaNoLaboral extends Model
{
    protected $table = 'dias_no_laborales';

    public const TIPOS = ['festivo', 'vacaciones', 'descanso'];

    protected $fillable = [
        'empresa_id',
        'fecha',
        'descripcion',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}

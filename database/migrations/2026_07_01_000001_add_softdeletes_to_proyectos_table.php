<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte de SoftDeletes a la tabla proyectos.
 *
 * Al usar el campo deleted_at, los proyectos "eliminados" no se borran
 * físicamente de la base de datos, sino que se marcan con una fecha.
 * Esto permite recuperarlos posteriormente y mantiene el historial completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->softDeletes(); // Agrega columna deleted_at nullable
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

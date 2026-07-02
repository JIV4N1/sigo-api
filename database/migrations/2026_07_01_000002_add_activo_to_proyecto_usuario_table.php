<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo 'activo' a la tabla pivote proyecto_usuario.
 *
 * Permite desactivar una asignación sin eliminarla físicamente,
 * conservando el historial de participación del usuario en el proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyecto_usuario', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('asignado_el');
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_usuario', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};

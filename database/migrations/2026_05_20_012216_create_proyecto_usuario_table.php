<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('rol_en_proyecto', 30)->default('trabajador');
            $table->timestamp('asignado_el')->useCurrent();
            $table->unique(['proyecto_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_usuario');
    }
};
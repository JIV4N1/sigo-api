<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->string('categoria', 30);
            $table->string('severidad', 20);
            $table->string('estado', 20)->default('abierta');
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->string('ubicacion_descripcion', 300)->nullable();
            $table->foreignId('reportado_por')->constrained('usuarios');
            $table->foreignId('asignado_a')->nullable()->constrained('usuarios');
            $table->timestamp('resuelta_el')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->string('ubicacion', 300)->nullable();
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('presupuesto', 12, 2)->nullable();
            $table->decimal('avance', 5, 2)->default(0);
            $table->string('estado', 20)->default('planeado');
            $table->unsignedBigInteger('cliente_id')->nullable(); // FK después
            $table->string('imagen_portada', 255)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('usuarios');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
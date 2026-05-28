<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asistencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->date('fecha');
            $table->dateTime('entrada')->nullable();
            $table->dateTime('comida_inicio')->nullable();
            $table->dateTime('comida_fin')->nullable();
            $table->dateTime('salida')->nullable();
            $table->decimal('latitud_entrada', 10, 8)->nullable();
            $table->decimal('longitud_entrada', 11, 8)->nullable();
            $table->decimal('latitud_salida', 10, 8)->nullable();
            $table->decimal('longitud_salida', 11, 8)->nullable();
            $table->boolean('sincronizado')->default(false);
            $table->timestamps();

            $table->unique(['usuario_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencia');
    }
};

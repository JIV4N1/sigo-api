<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->date('fecha_reporte');
            $table->string('turno', 20);
            $table->string('categoria', 30);
            $table->decimal('avance', 5, 2);
            $table->text('descripcion')->nullable();
            $table->boolean('validado')->default(false);
            $table->foreignId('validado_por')->nullable()->constrained('usuarios');
            $table->timestamp('validado_el')->nullable();
            $table->text('notas_validacion')->nullable();
            $table->boolean('sincronizado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_diarios');
    }
};
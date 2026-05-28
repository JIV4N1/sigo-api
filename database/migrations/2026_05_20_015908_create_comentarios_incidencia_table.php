<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comentarios_incidencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->text('comentario');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comentarios_incidencia');
    }
};
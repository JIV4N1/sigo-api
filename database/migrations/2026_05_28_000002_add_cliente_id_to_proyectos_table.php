<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primero verifica si la columna ya existe
        if (!Schema::hasColumn('proyectos', 'cliente_id')) {
            Schema::table('proyectos', function (Blueprint $table) {
                $table->unsignedBigInteger('cliente_id')->nullable();
            });
        }
        
        // Solo agrega la FK
        Schema::table('proyectos', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
        });
    }
};
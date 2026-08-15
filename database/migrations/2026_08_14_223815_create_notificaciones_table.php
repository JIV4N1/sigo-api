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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('titulo', 200);
            $table->text('descripcion');
            $table->string('tipo', 50);
            $table->boolean('leida')->default(false);
            $table->text('link')->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->string('origen_tipo')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'leida']);
            $table->index(['usuario_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};

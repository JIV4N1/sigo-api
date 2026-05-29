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
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materiales')->onDelete('restrict');
            $table->string('tipo_movimiento', 20); // entrada, salida, ajuste
            $table->decimal('cantidad', 10, 2);
            $table->decimal('stock_anterior', 10, 2)->nullable();
            $table->decimal('stock_nuevo', 10, 2)->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};

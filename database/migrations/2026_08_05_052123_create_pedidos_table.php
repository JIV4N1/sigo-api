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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('folio', 20)->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('restrict');
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->date('fecha_pedido');
            $table->date('fecha_entrega')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente, aprobado, rechazado, entregado
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};

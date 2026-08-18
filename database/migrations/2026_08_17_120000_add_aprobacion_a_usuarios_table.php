<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('aprobado')->default(false);
            $table->boolean('rechazado')->default(false);
            $table->timestamp('fecha_solicitud')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('rol_id')->nullable()->change();
        });

        // Ningún usuario existente debe quedar bloqueado por el nuevo flujo de aprobación.
        DB::table('usuarios')->update([
            'aprobado' => true,
            'fecha_solicitud' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['aprobado_por']);
            $table->dropColumn([
                'aprobado',
                'rechazado',
                'fecha_solicitud',
                'motivo_rechazo',
                'aprobado_por',
                'fecha_aprobacion',
            ]);
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('rol_id')->nullable(false)->change();
        });
    }
};

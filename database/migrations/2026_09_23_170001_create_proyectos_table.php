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
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // restrictOnDelete: la base de datos impide borrar un cliente que aún tiene proyectos.
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('estado')->default('propuesta');
            $table->decimal('tarifa_hora', 8, 2);
            $table->unsignedInteger('horas_estimadas');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};

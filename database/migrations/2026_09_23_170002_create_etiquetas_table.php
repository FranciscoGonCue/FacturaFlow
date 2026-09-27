<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Relación MUCHOS A MUCHOS: un proyecto puede tener varias etiquetas
 * y una etiqueta puede estar en muchos proyectos.
 * Se resuelve con una TABLA PIVOTE (etiqueta_proyecto) que solo guarda las parejas de ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etiquetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre', 40);
            $table->string('color', 20)->default('zinc');
            $table->timestamps();

            $table->unique(['user_id', 'nombre']);
        });

        // Por convención, Laravel nombra la pivote con los dos modelos en singular y por orden alfabético.
        Schema::create('etiqueta_proyecto', function (Blueprint $table) {
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['etiqueta_id', 'proyecto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiqueta_proyecto');
        Schema::dropIfExists('etiquetas');
    }
};

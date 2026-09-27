<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            // Cada cliente pertenece a un freelancer: así aislamos los datos entre usuarios (multiusuario).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('nombre');
            $table->string('email');
            $table->string('telefono', 30)->nullable();
            $table->string('nif', 20)->nullable();
            $table->string('ciudad')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            // El mismo email puede existir en cuentas distintas, pero no repetido dentro de una cuenta.
            $table->unique(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};

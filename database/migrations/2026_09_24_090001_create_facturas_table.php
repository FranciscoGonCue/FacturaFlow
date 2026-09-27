<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            // Opcional: una factura puede venir de un proyecto o ser suelta.
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();

            // La numeración solo se asigna al EMITIR (un borrador todavía no tiene número).
            $table->unsignedSmallInteger('anio')->nullable();
            $table->unsignedInteger('secuencia')->nullable();
            $table->string('numero', 20)->nullable();

            $table->string('concepto');
            $table->string('estado')->default('borrador');
            $table->unsignedSmallInteger('dias_pago')->default(30);
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->date('pagada_en')->nullable();

            // Importes "congelados" en céntimos: una factura emitida no puede cambiar
            // aunque luego cambie la tarifa del proyecto o el tipo de cliente.
            $table->unsignedBigInteger('base_centimos')->default(0);
            $table->unsignedBigInteger('iva_centimos')->default(0);
            $table->unsignedBigInteger('irpf_centimos')->default(0);
            $table->unsignedBigInteger('total_centimos')->default(0);

            $table->text('notas')->nullable();
            $table->timestamps();

            // Cada freelancer tiene su propia serie de numeración por año, sin huecos ni repetidos.
            $table->unique(['user_id', 'anio', 'secuencia']);
            $table->index(['user_id', 'estado']);
        });

        Schema::create('factura_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 8, 2);
            $table->unsignedBigInteger('precio_centimos');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_lineas');
        Schema::dropIfExists('facturas');
    }
};

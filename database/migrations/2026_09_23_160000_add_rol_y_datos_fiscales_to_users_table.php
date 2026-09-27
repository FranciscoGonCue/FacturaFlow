<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Amplía la tabla users del starter kit con:
 *  - el ROL (freelancer / admin) y si la cuenta está ACTIVA (el admin puede bloquearla);
 *  - los datos fiscales que aparecen como "emisor" en las facturas y el logo.
 * Es una migración nueva (y no un cambio en la original) porque nunca se reescribe
 * una migración que ya se ha ejecutado en otro entorno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('freelancer')->after('email');
            $table->boolean('activo')->default(true)->after('rol');
            $table->string('nif', 20)->nullable()->after('activo');
            $table->string('direccion')->nullable()->after('nif');
            $table->string('ciudad')->nullable()->after('direccion');
            $table->string('iban', 34)->nullable()->after('ciudad');
            $table->string('logo_path')->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol', 'activo', 'nif', 'direccion', 'ciudad', 'iban', 'logo_path']);
        });
    }
};

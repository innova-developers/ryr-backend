<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type', 20)->default('individual')->after('cuit');
            $table->string('razon_social')->nullable()->after('last_name');
        });

        // Los clientes empresa se identifican por CUIT, sin DNI obligatorio.
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('dni')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('dni')->nullable(false)->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['type', 'razon_social']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('iva_status', 20)->nullable()->default('auto')->after('auto_calculate_iva');
        });

        DB::table('customers')
            ->where('auto_calculate_iva', true)
            ->update(['iva_status' => 'auto']);

        DB::table('customers')
            ->where('auto_calculate_iva', false)
            ->update(['iva_status' => 'auto']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('iva_status');
        });
    }
};

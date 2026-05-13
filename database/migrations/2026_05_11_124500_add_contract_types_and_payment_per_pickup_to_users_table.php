<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('payment_per_pickup', 10, 2)->nullable()->after('commission_percentage');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN contract_type ENUM('fixed_salary', 'commission_based', 'per_pickup', 'fixed_plus_commission') DEFAULT NULL");
        }

        DB::table('users')
            ->where('contract_type', 'commission_based')
            ->update(['contract_type' => 'fixed_plus_commission']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN contract_type ENUM('fixed_salary', 'per_pickup', 'fixed_plus_commission') DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN contract_type ENUM('fixed_salary', 'per_pickup', 'fixed_plus_commission', 'commission_based') DEFAULT NULL");
        }

        DB::table('users')
            ->where('contract_type', 'fixed_plus_commission')
            ->update(['contract_type' => 'commission_based']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN contract_type ENUM('fixed_salary', 'commission_based') DEFAULT NULL");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('payment_per_pickup');
        });
    }
};

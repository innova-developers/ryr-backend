<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['branches', 'users', 'customers', 'commissions', 'expenses', 'incomes', 'current_accounts'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('franchise_id')->nullable()->after('id')->constrained('franchises')->nullOnDelete();
                $blueprint->index('franchise_id');
            });
        }
    }

    public function down(): void
    {
        $tables = ['current_accounts', 'incomes', 'expenses', 'commissions', 'customers', 'users', 'branches'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('franchise_id');
            });
        }
    }
};

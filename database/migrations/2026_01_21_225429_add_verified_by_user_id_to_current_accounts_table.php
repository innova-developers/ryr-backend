<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('current_accounts', function (Blueprint $table) {
            $table->foreignId('verified_by_user_id')->nullable()->after('user_id')->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('current_accounts', function (Blueprint $table) {
            $table->dropForeign(['verified_by_user_id']);
            $table->dropColumn(['verified_by_user_id', 'verified_at']);
        });
    }
};

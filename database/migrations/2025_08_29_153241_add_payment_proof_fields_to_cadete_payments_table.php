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
        Schema::table('cadete_payments', function (Blueprint $table) {
            $table->string('payment_proof_filename')->nullable()->after('paid_at');
            $table->string('payment_proof_path')->nullable()->after('payment_proof_filename');
            $table->string('payment_proof_mime_type')->nullable()->after('payment_proof_path');
            $table->unsignedBigInteger('payment_proof_size')->nullable()->after('payment_proof_mime_type');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cadete_payments', function (Blueprint $table) {
            $table->dropColumn([
                'payment_proof_filename',
                'payment_proof_path',
                'payment_proof_mime_type',
                'payment_proof_size',
                'payment_proof_uploaded_at'
            ]);
        });
    }
};

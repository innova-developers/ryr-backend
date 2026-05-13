<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('image_url', 500)->nullable()->after('message_template');
            $table->integer('message_delay_ms')->default(1000)->after('segment_filters');
            $table->time('send_time_start')->nullable()->after('message_delay_ms');
            $table->time('send_time_end')->nullable()->after('send_time_start');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn(['description', 'image_url', 'message_delay_ms', 'send_time_start', 'send_time_end']);
        });
    }
};

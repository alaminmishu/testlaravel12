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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pos_sync_id')->nullable()->after('promo_discount_amount');
            $table->boolean('is_pos_synced')->nullable()->default(false)->index()->after('pos_sync_id');
            $table->boolean('is_phone_order')->nullable()->default(false)->index()->after('is_pos_synced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pos_sync_id', 'is_pos_synced', 'is_phone_order']);
        });
    }
};

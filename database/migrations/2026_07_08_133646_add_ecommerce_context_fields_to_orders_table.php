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
            $table->string('platform_type')->nullable()->index()->after('environment');
            $table->string('device_platform_type')->nullable()->after('platform_type');

            $table->string('area_uid')->nullable()->after('customer_email');
            $table->string('area_name')->nullable()->index()->after('area_uid');
            $table->string('zone_uid')->nullable()->after('area_name');
            $table->string('zone_name')->nullable()->index()->after('zone_uid');
            $table->string('division_uid')->nullable()->after('zone_name');
            $table->string('division_name')->nullable()->index()->after('division_uid');

            $table->string('promo_code')->nullable()->after('division_name');
            $table->decimal('promo_discount_amount', 12, 2)->nullable()->after('promo_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'platform_type',
                'device_platform_type',
                'area_uid',
                'area_name',
                'zone_uid',
                'zone_name',
                'division_uid',
                'division_name',
                'promo_code',
                'promo_discount_amount',
            ]);
        });
    }
};

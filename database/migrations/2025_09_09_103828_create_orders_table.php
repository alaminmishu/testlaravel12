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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // External identity
            $table->string('uid');                 // unique order id from GraphQL
            $table->string('environment');         // 'dev' | 'prod'
            $table->unique(['uid', 'environment']);

            // Fast grid fields (indexed for sort/filter)
            $table->string('status')->nullable()->index();
            $table->string('payment_method')->nullable()->index();  // from payment.gateway
            $table->string('payment_status')->nullable()->index();  // from payment.status

            $table->timestamp('created_at_external')->nullable()->index(); // API createdAt (UTC)
            $table->timestamp('updated_at_external')->nullable()->index(); // API updatedAt (UTC)

            // Money & customer
            $table->string('currency', 3)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('customer_email')->nullable()->index();

            // Keep full source payload for detail/audit
            $table->json('raw')->nullable();

            // Laravel's own timestamps for this DB row
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

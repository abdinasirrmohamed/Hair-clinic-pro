<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->default('WaafiPay');
            $table->string('reference_id', 100)->index();
            $table->string('invoice_id', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('account_masked', 30)->nullable();
            $table->string('status', 20)->index();
            $table->string('response_code', 50)->nullable();
            $table->string('transaction_id', 150)->nullable();
            $table->text('message')->nullable();
            // Keep this compatible with legacy installations where users.id is signed INT.
            $table->integer('created_by')->nullable()->index();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
    }
};

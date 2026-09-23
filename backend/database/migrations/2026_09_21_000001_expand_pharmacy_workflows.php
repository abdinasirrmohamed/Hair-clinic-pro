<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $t) { $t->unsignedBigInteger('batch_group_id')->nullable()->index(); });
        Schema::create('pharmacy_orders', function (Blueprint $t) {
            $t->id(); $t->string('order_number')->unique(); $t->unsignedBigInteger('supplier_id');
            $t->string('status')->default('Ordered'); $t->date('expected_date')->nullable(); $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('pharmacy_order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('pharmacy_orders'); $t->unsignedBigInteger('medicine_id');
            $t->unsignedInteger('quantity'); $t->unsignedInteger('received_quantity')->default(0); $t->decimal('unit_cost',12,2);
        });
        Schema::table('pharmacy_purchases', function (Blueprint $t) {
            $t->unsignedBigInteger('order_id')->nullable()->index(); $t->date('due_date')->nullable();
            // Existing invoices have unknown payment history; do not invent a payable or a payment.
            $t->decimal('amount_paid',12,2)->nullable();
        });
        Schema::create('pharmacy_supplier_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_id')->constrained('pharmacy_purchases'); $t->decimal('amount',12,2);
            $t->string('payment_method'); $t->string('reference')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('pharmacy_registers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->decimal('opening_cash',12,2);
            $t->decimal('expected_cash',12,2)->nullable(); $t->decimal('counted_cash',12,2)->nullable();
            $t->decimal('difference',12,2)->nullable(); $t->text('notes')->nullable();
            $t->timestamp('opened_at'); $t->timestamp('closed_at')->nullable();
        });
        Schema::table('pharmacy_sale_payments', fn (Blueprint $t) => $t->unsignedBigInteger('register_id')->nullable()->index());
        Schema::create('pharmacy_refunds', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('sale_id')->index(); $t->unsignedBigInteger('register_id')->nullable()->index();
            $t->decimal('amount',12,2); $t->string('payment_method'); $t->timestamp('created_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_refunds');
        Schema::table('pharmacy_sale_payments', fn (Blueprint $t) => $t->dropIndex(['register_id']));
        Schema::table('pharmacy_sale_payments', fn (Blueprint $t) => $t->dropColumn('register_id'));
        Schema::dropIfExists('pharmacy_registers'); Schema::dropIfExists('pharmacy_supplier_payments');
        Schema::table('pharmacy_purchases', fn (Blueprint $t) => $t->dropIndex(['order_id']));
        Schema::table('pharmacy_purchases', fn (Blueprint $t) => $t->dropColumn(['order_id','due_date','amount_paid']));
        Schema::dropIfExists('pharmacy_order_items'); Schema::dropIfExists('pharmacy_orders');
        Schema::table('medicines', fn (Blueprint $t) => $t->dropIndex(['batch_group_id']));
        Schema::table('medicines', fn (Blueprint $t) => $t->dropColumn('batch_group_id'));
    }
};

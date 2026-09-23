<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pharmacy_customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('pharmacy_sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained('pharmacy_customers')->restrictOnDelete();
            $table->string('status', 30)->default('Paid')->change();
            $table->string('payment_status', 30)->default('Full Paid')->change();
        });
        $bigIds = Schema::getColumnType('patients', 'id') === 'bigint';
        Schema::table('prescriptions', function (Blueprint $table) use ($bigIds) {
            if ($bigIds) {
                $table->unsignedBigInteger('patient_id')->nullable()->change();
                $table->unsignedBigInteger('doctor_id')->nullable()->change();
            } else {
                $table->integer('patient_id')->nullable()->change();
                $table->integer('doctor_id')->nullable()->change();
            }
            $table->string('status', 30)->default('Pending')->change();
            $table->foreignId('customer_id')->nullable()->constrained('pharmacy_customers')->restrictOnDelete();
            $table->string('prescriber_name', 150)->nullable();
        });
        Schema::create('pharmacy_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 40);
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('pharmacy_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 50)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->string('invoice_number', 100);
            $table->date('received_date');
            $table->decimal('total_amount', 12, 2);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'invoice_number']);
        });
        Schema::create('pharmacy_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('pharmacy_purchases')->restrictOnDelete();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_purchase_items');
        Schema::dropIfExists('pharmacy_purchases');
        Schema::dropIfExists('pharmacy_sale_payments');
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('prescriber_name');
        });
        Schema::table('pharmacy_sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('pharmacy_customers');
    }
};

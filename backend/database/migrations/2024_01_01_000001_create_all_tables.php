<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hair Clinic Pro — Complete Database Schema
     * Maps exactly to the existing MySQL database tables.
     */
    public function up(): void
    {
        $existingTables = array_filter($this->expectedTables(), fn ($table) => Schema::hasTable($table));

        if (count($existingTables) === count($this->expectedTables())) {
            return;
        }

        if ($existingTables !== []) {
            $missingTables = array_values(array_diff($this->expectedTables(), $existingTables));

            throw new RuntimeException(
                'The database already contains part of the Hair Clinic Pro schema. '.
                'Missing tables: '.implode(', ', $missingTables).'. '.
                'Back up the database, then either complete the missing schema manually or run migrate:fresh for a clean install.'
            );
        }
        // ── USERS ──────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->string('full_name', 150);
            $table->string('role', 50)->default('Administrator');
            $table->string('status', 20)->default('Active');
            $table->timestamp('created_at')->useCurrent();
        });

        // ── DOCTORS ────────────────────────────────────────
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->string('full_name', 150);
            $table->string('specialization', 120);
            $table->string('qualification', 180)->nullable();
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->string('license_number', 80)->unique();
            $table->string('photo', 255)->nullable();
            $table->integer('experience_years')->default(0);
            $table->text('availability_schedule')->nullable();
            $table->text('bio')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // ── DOCTOR SCHEDULES ───────────────────────────────
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->string('day_of_week', 20);
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('16:00:00');
            $table->integer('slot_minutes')->default(30);
            $table->boolean('is_working')->default(true);
            $table->timestamps();
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
            $table->unique(['doctor_id', 'day_of_week']);
        });

        // ── DOCTOR BLOCKED DATES ───────────────────────────
        Schema::create('doctor_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->date('block_date');
            $table->string('block_type', 20)->default('Blocked');
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
            $table->unique(['doctor_id', 'block_date']);
        });

        // ── PATIENTS ───────────────────────────────────────
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('address', 255)->nullable();
            $table->text('medical_notes')->nullable();
            $table->integer('assigned_doctor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ── APPOINTMENTS ───────────────────────────────────
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->string('reason', 255);
            $table->string('status', 20)->default('Pending');
            $table->boolean('reminder_sent')->default(false);
            $table->text('notes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
        });

        // ── TREATMENTS ─────────────────────────────────────
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->string('treatment_name', 150);
            $table->date('treatment_date');
            $table->string('treatment_stage', 50)->default('Surgery');
            $table->string('progress', 20)->default('Started');
            $table->decimal('cost', 10, 2)->default(0);
            $table->integer('grafts_planned')->nullable();
            $table->integer('grafts_extracted')->nullable();
            $table->integer('grafts_implanted')->nullable();
            $table->string('donor_area_status', 255)->nullable();
            $table->string('recipient_area_status', 255)->nullable();
            $table->string('pre_op_photo', 255)->nullable();
            $table->string('post_op_photo', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });

        // ── FOLLOWUPS ──────────────────────────────────────
        Schema::create('followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('treatment_id')->nullable();
            $table->date('followup_date');
            $table->text('result')->nullable();
            $table->string('status', 20)->default('Scheduled');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('treatment_id')->references('id')->on('treatments')->nullOnDelete();
        });

        // ── INVENTORY ITEMS ────────────────────────────────
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name', 150);
            $table->string('category', 80);
            $table->integer('stock_level')->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('vendor', 150);
            $table->string('status', 20)->default('In Stock');
            $table->timestamp('created_at')->useCurrent();
        });

        // ── INVENTORY ORDERS ───────────────────────────────
        Schema::create('inventory_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('order_code', 30)->unique();
            $table->integer('quantity');
            $table->string('priority', 40)->default('Normal');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total_estimate', 10, 2)->default(0);
            $table->string('order_status', 20)->default('Pending');
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('item_id')->references('id')->on('inventory_items')->cascadeOnDelete();
        });

        // ── MEDICINES ──────────────────────────────────────
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('medicine_name', 150);
            $table->string('generic_name', 150)->nullable();
            $table->string('category', 100);
            $table->string('batch_number', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->integer('supplier_id')->nullable();
            $table->date('expiry_date');
            $table->date('manufacturing_date')->nullable();
            $table->integer('reorder_level')->default(10);
            $table->string('supplier', 150)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ── SUPPLIERS ──────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 150)->unique();
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 40);
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // ── INVENTORY MOVEMENTS ────────────────────────────
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50);
            $table->unsignedBigInteger('medicine_id');
            $table->string('movement_type', 50);
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('department', 120)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->integer('reference_id')->nullable();
            $table->string('invoice_path', 255)->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('movement_date')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->nullOnDelete();
            $table->index('movement_date');
            $table->index('movement_type');
        });

        // ── PAYMENTS ───────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30);
            $table->string('payment_status', 20)->default('Paid');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── RECEIPTS ───────────────────────────────────────
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->string('receipt_number', 40)->unique();
            $table->string('clinic_name', 150)->default('Hair Clinic Pro');
            $table->string('clinic_phone', 40)->default('+252 61 000 0000');
            $table->string('clinic_address', 255)->default('Mogadishu, Somalia');
            $table->timestamp('issued_at')->useCurrent();
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
        });

        // ── EXPENSES ───────────────────────────────────────
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);
            $table->date('expense_date');
            $table->decimal('amount', 10, 2);
            $table->string('vendor', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('receipt_path', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('expense_date');
        });

        // ── REPORTS ────────────────────────────────────────
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 80);
            $table->string('period_type', 20)->default('Daily');
            $table->string('title', 180);
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->text('summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── AUDIT LOGS ─────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 150);
            $table->string('user_role', 80);
            $table->string('action', 150);
            $table->string('module_name', 80);
            $table->string('record_id', 80)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
            $table->index('module_name');
        });

        // ── PRESCRIPTIONS ──────────────────────────────────
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number', 40)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->date('prescription_date');
            $table->string('status', 20)->default('Pending');
            $table->text('instructions')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
        });

        // ── PRESCRIPTION MEDICINES ─────────────────────────
        Schema::create('prescription_medicines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
            $table->string('instructions', 255)->nullable();
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->cascadeOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
        });

        // ── PHARMACY INVOICES ──────────────────────────────
        Schema::create('pharmacy_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->unsignedBigInteger('patient_id');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('payment_method', 30);
            $table->string('payment_status', 20)->default('Paid');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── PHARMACY INVOICE ITEMS ─────────────────────────
        Schema::create('pharmacy_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->foreign('invoice_id')->references('id')->on('pharmacy_invoices')->cascadeOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
        });

        // ── PHARMACY PAYMENTS ──────────────────────────────
        Schema::create('pharmacy_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30);
            $table->string('reference_number', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('invoice_id')->references('id')->on('pharmacy_invoices')->cascadeOnDelete();
        });

        // ── PHARMACY SALES ─────────────────────────────────
        Schema::create('pharmacy_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 40)->unique();
            $table->string('customer_name', 150)->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('prescription_id')->nullable();
            $table->integer('medicine_count')->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('discount_type', 20)->default('None');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percent', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('payment_method', 30);
            $table->string('payment_status', 20)->default('Paid');
            $table->string('status', 20)->default('Paid');
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('id')->on('patients')->nullOnDelete();
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // ── PHARMACY SALE MEDICINES ────────────────────────
        Schema::create('pharmacy_sale_medicines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->foreign('sale_id')->references('id')->on('pharmacy_sales')->cascadeOnDelete();
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
        });

        // ── SANCTUM: Personal Access Tokens ────────────────
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'personal_access_tokens',
            'pharmacy_sale_medicines', 'pharmacy_sales',
            'pharmacy_payments', 'pharmacy_invoice_items', 'pharmacy_invoices',
            'prescription_medicines', 'prescriptions',
            'audit_logs', 'reports', 'expenses',
            'receipts', 'payments',
            'inventory_movements', 'suppliers', 'medicines',
            'inventory_orders', 'inventory_items',
            'followups', 'treatments',
            'appointments', 'patients',
            'doctor_blocked_dates', 'doctor_schedules', 'doctors',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function expectedTables(): array
    {
        return [
            'users',
            'doctors',
            'doctor_schedules',
            'doctor_blocked_dates',
            'patients',
            'appointments',
            'treatments',
            'followups',
            'inventory_items',
            'inventory_orders',
            'medicines',
            'suppliers',
            'inventory_movements',
            'payments',
            'receipts',
            'expenses',
            'reports',
            'audit_logs',
            'prescriptions',
            'prescription_medicines',
            'pharmacy_invoices',
            'pharmacy_invoice_items',
            'pharmacy_payments',
            'pharmacy_sales',
            'pharmacy_sale_medicines',
            'personal_access_tokens',
        ];
    }
};

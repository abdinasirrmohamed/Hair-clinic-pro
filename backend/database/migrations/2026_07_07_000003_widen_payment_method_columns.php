<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY payment_method VARCHAR(30) NOT NULL");
        DB::statement("ALTER TABLE pharmacy_sales MODIFY payment_method VARCHAR(30) NOT NULL");
        DB::statement("ALTER TABLE pharmacy_invoices MODIFY payment_method VARCHAR(30) NOT NULL");
        DB::statement("ALTER TABLE pharmacy_payments MODIFY payment_method VARCHAR(30) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('Cash','EVC Plus','Sahal','Bank Transfer') NOT NULL");
        DB::statement("ALTER TABLE pharmacy_sales MODIFY payment_method ENUM('Cash','EVC Plus','Sahal','Bank Transfer') NOT NULL");
        DB::statement("ALTER TABLE pharmacy_invoices MODIFY payment_method ENUM('Cash','Mobile Money','Bank Transfer') NOT NULL");
        DB::statement("ALTER TABLE pharmacy_payments MODIFY payment_method VARCHAR(30) NOT NULL");
    }
};

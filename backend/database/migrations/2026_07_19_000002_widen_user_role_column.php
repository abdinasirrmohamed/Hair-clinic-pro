<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'Administrator'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('Administrator','Receptionist','Doctor','Inventory Officer','Pharmacy User') NOT NULL DEFAULT 'Administrator'"
        );
    }
};

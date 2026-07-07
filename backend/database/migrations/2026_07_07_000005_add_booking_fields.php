<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'consultation_fee')) {
                $table->decimal('consultation_fee', 10, 2)->default(0)->after('phone');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'age')) {
                $table->unsignedTinyInteger('age')->nullable()->after('gender');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'fee_at_booking')) {
                $table->decimal('fee_at_booking', 10, 2)->default(0)->after('appointment_time');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'fee_at_booking')) {
                $table->dropColumn('fee_at_booking');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'age')) {
                $table->dropColumn('age');
            }
        });

        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'consultation_fee')) {
                $table->dropColumn('consultation_fee');
            }
        });
    }
};

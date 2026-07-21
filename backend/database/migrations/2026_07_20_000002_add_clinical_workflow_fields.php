<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dayIndex = collect(Schema::getIndexes('doctor_schedules'))->first(
            fn ($index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['doctor_id', 'day_of_week']
        );
        if (!Schema::hasColumn('doctor_schedules', 'shift')) {
            Schema::table('doctor_schedules', function (Blueprint $table) {
                $table->string('shift', 20)->default('Morning')->after('day_of_week');
            });
        }
        if ($dayIndex) {
            $doctorIndex = collect(Schema::getIndexes('doctor_schedules'))->first(
                fn ($index) => ($index['columns'] ?? []) === ['doctor_id']
            );
            if (!$doctorIndex) {
                Schema::table('doctor_schedules', fn (Blueprint $table) => $table->index('doctor_id', 'doctor_schedules_doctor_fk_index'));
            }
            Schema::table('doctor_schedules', function (Blueprint $table) use ($dayIndex) {
                $table->dropUnique($dayIndex['name']);
            });
        }
        $shiftIndex = collect(Schema::getIndexes('doctor_schedules'))->first(
            fn ($index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['doctor_id', 'day_of_week', 'shift']
        );
        if (!$shiftIndex) {
            Schema::table('doctor_schedules', function (Blueprint $table) {
                $table->unique(['doctor_id', 'day_of_week', 'shift']);
            });
        }

        if (!Schema::hasColumn('payments', 'total_amount')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->decimal('total_amount', 10, 2)->default(0)->after('amount');
                $table->decimal('paid_amount', 10, 2)->default(0)->after('total_amount');
                $table->decimal('remaining_amount', 10, 2)->default(0)->after('paid_amount');
            });
        }
        DB::table('payments')->update([
            'total_amount' => DB::raw('amount'),
            'paid_amount' => DB::raw('amount'),
        ]);

        if (!Schema::hasColumn('prescription_medicines', 'frequency')) {
            Schema::table('prescription_medicines', function (Blueprint $table) {
                $table->string('frequency', 100)->default('As directed')->after('quantity');
                $table->unsignedInteger('dispensed_quantity')->default(0)->after('frequency');
            });
        }
        $prescriptionLineIndex = collect(Schema::getIndexes('prescription_medicines'))->first(
            fn ($index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['prescription_id', 'medicine_id']
        );
        if (!$prescriptionLineIndex) {
            Schema::table('prescription_medicines', fn (Blueprint $table) => $table->unique(['prescription_id', 'medicine_id']));
        }

        if (!Schema::hasColumn('pharmacy_sales', 'amount_paid')) {
            Schema::table('pharmacy_sales', function (Blueprint $table) {
                $table->decimal('amount_paid', 10, 2)->default(0)->after('total_amount');
                $table->decimal('remaining_balance', 10, 2)->default(0)->after('amount_paid');
            });
        }

        if (!Schema::hasColumn('pharmacy_sale_medicines', 'prescription_medicine_id')) {
            $usesBigIds = Schema::getColumnType('prescription_medicines', 'id') === 'bigint';
            Schema::table('pharmacy_sale_medicines', function (Blueprint $table) use ($usesBigIds) {
                if ($usesBigIds) {
                    $table->unsignedBigInteger('prescription_medicine_id')->nullable()->after('medicine_id');
                } else {
                    $table->integer('prescription_medicine_id')->nullable()->after('medicine_id');
                }
                $table->string('frequency', 100)->nullable()->after('quantity');
                $table->string('instructions', 255)->nullable()->after('frequency');
                $table->foreign('prescription_medicine_id')->references('id')->on('prescription_medicines')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('notification_logs')) {
            Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->index();
            $table->string('notification_type', 50)->index();
            $table->string('recipient', 180);
            $table->string('subject', 180)->nullable();
            $table->text('message');
            $table->string('status', 20)->index();
            $table->string('provider_reference', 180)->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->nullableMorphs('notifiable');
            $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::table('pharmacy_sale_medicines', function (Blueprint $table) {
            $table->dropForeign(['prescription_medicine_id']);
            $table->dropColumn(['prescription_medicine_id', 'frequency', 'instructions']);
        });
        Schema::table('pharmacy_sales', fn (Blueprint $table) => $table->dropColumn(['amount_paid', 'remaining_balance']));
        Schema::table('prescription_medicines', function (Blueprint $table) {
            $table->dropUnique(['prescription_id', 'medicine_id']);
            $table->dropColumn(['frequency', 'dispensed_quantity']);
        });
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['total_amount', 'paid_amount', 'remaining_amount']));
        Schema::table('doctor_schedules', function (Blueprint $table) {
            $table->dropUnique(['doctor_id', 'day_of_week', 'shift']);
            $table->dropColumn('shift');
            $table->unique(['doctor_id', 'day_of_week']);
        });
    }
};

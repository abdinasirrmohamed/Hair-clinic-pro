<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lab_tests')) {
            Schema::create('lab_tests', function (Blueprint $table) {
                $table->id();
                $table->string('test_name', 150);
                $table->string('category', 100)->default('General Lab');
                $table->decimal('price', 10, 2)->default(0);
                $table->string('sample_type', 80)->nullable();
                $table->string('status', 20)->default('Active');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lab_requests')) {
            Schema::create('lab_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 40)->unique();
                $table->unsignedBigInteger('patient_id');
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->unsignedBigInteger('doctor_id')->nullable();
                $table->unsignedBigInteger('lab_test_id');
                $table->date('request_date');
                $table->string('status', 30)->default('Requested');
                $table->text('result')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_requests');
        Schema::dropIfExists('lab_tests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pharmacy_sale_medicines', fn (Blueprint $t) => $t->decimal('unit_cost', 12, 2)->nullable());
        Schema::create('pharmacy_stocktakes', function (Blueprint $t) {
            $t->id(); $t->string('number')->unique(); $t->string('status')->default('Draft');
            $t->text('notes')->nullable(); $t->unsignedBigInteger('created_by'); $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable(); $t->timestamps();
        });
        Schema::create('pharmacy_stocktake_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('stocktake_id')->constrained('pharmacy_stocktakes');
            $t->unsignedBigInteger('medicine_id'); $t->string('medicine_name'); $t->string('batch_number')->nullable();
            $t->integer('expected_quantity'); $t->integer('counted_quantity')->nullable();
            $t->unsignedBigInteger('movement_id')->default(0); $t->string('reason', 255)->nullable();
            $t->unique(['stocktake_id', 'medicine_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_stocktake_items'); Schema::dropIfExists('pharmacy_stocktakes');
        Schema::table('pharmacy_sale_medicines', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
    }
};

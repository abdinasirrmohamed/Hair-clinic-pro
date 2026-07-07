<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (!Schema::hasColumn('medicines', 'brand')) {
                $table->string('brand', 150)->nullable()->after('generic_name');
            }

            if (!Schema::hasColumn('medicines', 'buying_price')) {
                $table->decimal('buying_price', 10, 2)->default(0)->after('quantity');
            }

            if (!Schema::hasColumn('medicines', 'image_path')) {
                $table->string('image_path', 255)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (Schema::hasColumn('medicines', 'image_path')) {
                $table->dropColumn('image_path');
            }

            if (Schema::hasColumn('medicines', 'buying_price')) {
                $table->dropColumn('buying_price');
            }

            if (Schema::hasColumn('medicines', 'brand')) {
                $table->dropColumn('brand');
            }
        });
    }
};

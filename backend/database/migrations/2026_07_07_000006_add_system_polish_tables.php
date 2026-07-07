<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_movements', 'old_quantity')) {
                $table->integer('old_quantity')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('inventory_movements', 'new_quantity')) {
                $table->integer('new_quantity')->nullable()->after('old_quantity');
            }
        });

        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 120)->unique();
                $table->text('setting_value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings')) {
            Schema::dropIfExists('system_settings');
        }

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'new_quantity')) {
                $table->dropColumn('new_quantity');
            }
            if (Schema::hasColumn('inventory_movements', 'old_quantity')) {
                $table->dropColumn('old_quantity');
            }
        });
    }
};

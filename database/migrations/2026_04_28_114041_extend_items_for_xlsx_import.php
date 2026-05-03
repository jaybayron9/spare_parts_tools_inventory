<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('vendor')->nullable()->after('name');
            $table->string('brand')->nullable()->after('vendor');
            $table->string('equipment_system')->nullable()->after('brand');
            $table->string('contract')->nullable()->after('equipment_system');
            $table->boolean('is_critical')->default(false)->after('contract');
            $table->string('uom', 16)->nullable()->after('is_critical');
            $table->text('install_remarks')->nullable()->after('uom');
            $table->string('leadtime')->nullable()->after('reorder_level');
            $table->date('date_purchased')->nullable()->after('location');
            $table->unsignedSmallInteger('service_life_yrs')->nullable()->after('date_purchased');
            $table->unsignedSmallInteger('eul_yrs')->nullable()->after('service_life_yrs');
            $table->string('replacement_frequency')->nullable()->after('eul_yrs');

            $table->index('equipment_system');
            $table->index('is_critical');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['equipment_system']);
            $table->dropIndex(['is_critical']);
            $table->dropColumn([
                'vendor',
                'brand',
                'equipment_system',
                'contract',
                'is_critical',
                'uom',
                'install_remarks',
                'leadtime',
                'date_purchased',
                'service_life_yrs',
                'eul_yrs',
                'replacement_frequency',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->timestamps();
        });

        DB::table('item_types')->insert([
            ['label' => 'Spare Part', 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Tool', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_type_id')->nullable()->after('type');
        });

        $sparePartId = DB::table('item_types')->where('label', 'Spare Part')->value('id');
        $toolId = DB::table('item_types')->where('label', 'Tool')->value('id');

        DB::table('items')->where('type', 'spare_part')->update(['item_type_id' => $sparePartId]);
        DB::table('items')->where('type', 'tool')->update(['item_type_id' => $toolId]);
        DB::table('items')->whereNull('item_type_id')->update(['item_type_id' => $sparePartId]);

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('item_type_id')->nullable(false)->change();
            $table->foreign('item_type_id')->references('id')->on('item_types')->restrictOnDelete();
            $table->index('item_type_id');
            $table->dropIndex('items_type_index');
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['item_type_id']);
            $table->dropIndex(['item_type_id']);
            $table->string('type')->nullable()->after('item_type_id');
        });

        DB::statement("
            UPDATE items i
            JOIN item_types t ON t.id = i.item_type_id
            SET i.type = CASE t.label
                WHEN 'Spare Part' THEN 'spare_part'
                WHEN 'Tool' THEN 'tool'
                ELSE LOWER(REPLACE(t.label, ' ', '_'))
            END
        ");

        Schema::table('items', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
            $table->index('type');
            $table->dropColumn('item_type_id');
        });

        Schema::dropIfExists('item_types');
    }
};

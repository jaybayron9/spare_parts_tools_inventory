<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('type');
            $table->string('category')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

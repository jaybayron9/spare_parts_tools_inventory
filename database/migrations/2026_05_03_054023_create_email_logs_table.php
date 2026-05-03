<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_schedule_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('email');
            $table->datetime('sent_at');

            $table->index(['notification_schedule_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

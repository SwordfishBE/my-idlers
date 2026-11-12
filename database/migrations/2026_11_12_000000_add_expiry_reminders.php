<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('expiry_reminders_enabled')->default(false);
            $table->string('expiry_reminder_email')->nullable();
            $table->string('expiry_reminder_timezone', 64)->default('Europe/Brussels');
        });

        Schema::create('expiry_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->char('service_id', 8);
            $table->date('due_date');
            $table->unsignedTinyInteger('days_before');
            $table->char('recipient_hash', 64);
            $table->string('status', 16);
            $table->string('claim_token', 36);
            $table->timestamp('claimed_at');
            $table->timestamp('sent_at')->nullable();
            $table->unique(['service_id', 'due_date', 'days_before', 'recipient_hash'], 'expiry_reminder_unique');
        });
    }

    public function down(): void
    {
        // Retain reminder settings and delivery history; use a verified backup for recovery.
    }
};

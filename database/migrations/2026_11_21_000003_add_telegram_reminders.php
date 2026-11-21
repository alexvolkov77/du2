<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habits', function (Blueprint $table): void {
            $table->boolean('reminder_enabled')->default(false);
            $table->string('reminder_time', 5)->default('20:00');
            $table->date('last_reminded_on')->nullable();
        });

        Schema::table('user_identities', function (Blueprint $table): void {
            $table->string('telegram_chat_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_identities', function (Blueprint $table): void {
            $table->dropColumn('telegram_chat_id');
        });

        Schema::table('habits', function (Blueprint $table): void {
            $table->dropColumn(['reminder_enabled', 'reminder_time', 'last_reminded_on']);
        });
    }
};

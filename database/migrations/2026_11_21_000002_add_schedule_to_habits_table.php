<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habits', function (Blueprint $table): void {
            $table->string('schedule_type', 16)->default('daily');
            $table->json('schedule_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('habits', function (Blueprint $table): void {
            $table->dropColumn(['schedule_type', 'schedule_days']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC');
        });

        Schema::create('habits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('habit_checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->timestamp('completed_at');
            $table->unique(['habit_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_checkins');
        Schema::dropIfExists('habits');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};

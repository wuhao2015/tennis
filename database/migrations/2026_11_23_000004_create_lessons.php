<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->dateTime('starts_at');
            $table->unique(['studio_id', 'user_id', 'starts_at']);
        });
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained();
            $table->string('type');
            $table->string('status');
            $table->dateTime('starts_at')->index();
            $table->timestamps();
        });
        Schema::create('lesson_coach', function (Blueprint $table) {
            $table->foreignId('lesson_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->primary(['lesson_id', 'user_id']);
        });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('status');
            $table->timestamps();
            $table->unique(['lesson_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('lesson_coach');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('coach_slots');
    }
};

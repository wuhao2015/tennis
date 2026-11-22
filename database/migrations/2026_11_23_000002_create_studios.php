<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('courts')->default(1);
            $table->timestamps();
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('status')->default('pending');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_coach')->default(false);
            $table->timestamps();
            $table->unique(['studio_id', 'user_id']);
        });
        DB::statement('CREATE UNIQUE INDEX coach_home ON memberships(user_id) WHERE is_coach');
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('studios');
    }
};

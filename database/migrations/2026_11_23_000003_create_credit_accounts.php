<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action');
            $table->uuid('token');
            $table->unique(['studio_id', 'token']);
            $table->timestamp('created_at');
        });
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unique(['studio_id', 'user_id']);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('frozen', 12, 2)->default(0);
            $table->timestamps();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE credit_accounts ADD CHECK (balance >= frozen AND frozen >= 0)');
        }
        Schema::create('credit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_account_id')->constrained();
            $table->foreignId('operation_id')->constrained();
            $table->string('reason');
            $table->decimal('balance_change', 12, 2);
            $table->decimal('frozen_change', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_entries');
        Schema::dropIfExists('credit_requests');
        Schema::dropIfExists('credit_accounts');
        Schema::dropIfExists('operations');
    }
};

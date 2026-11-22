<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->foreignId('cancel_requested_by')->nullable()->constrained('users'));
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->dropConstrainedForeignId('cancel_requested_by'));
    }
};

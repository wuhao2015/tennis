<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->renameColumn('name', 'nickname'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->renameColumn('nickname', 'name'));
    }
};

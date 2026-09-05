<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('messenger_name')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('public_phone', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('public_phone'));
        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn('messenger_name'));
    }
};

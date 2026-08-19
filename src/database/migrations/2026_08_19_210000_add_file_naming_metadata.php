<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_groups', function (Blueprint $table): void {
            $table->string('aktenzeichen', 30)->nullable()->after('name');
        });
        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->string('keyword', 255)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_units', fn (Blueprint $table) => $table->dropColumn('keyword'));
        Schema::table('teaching_groups', fn (Blueprint $table) => $table->dropColumn('aktenzeichen'));
    }
};

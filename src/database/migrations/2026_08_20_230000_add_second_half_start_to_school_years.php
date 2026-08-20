<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table): void {
            $table->date('second_half_start_on')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table): void {
            $table->dropColumn('second_half_start_on');
        });
    }
};

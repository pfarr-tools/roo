<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assessment_results', function (Blueprint $table): void {
            $table->decimal('points', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_assessment_results', function (Blueprint $table): void {
            $table->unsignedInteger('points')->nullable()->change();
        });
    }
};

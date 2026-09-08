<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_evaluation_competence_ratings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating')->nullable()->change();
            if (! Schema::hasColumn('student_evaluation_competence_ratings', 'include_in_text')) {
                $table->boolean('include_in_text')->default(true);
            }
            if (! Schema::hasColumn('student_evaluation_competence_ratings', 'include_in_grade')) {
                $table->boolean('include_in_grade')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_evaluation_competence_ratings', function (Blueprint $table): void {
            $table->dropColumn(['include_in_text', 'include_in_grade']);
            $table->unsignedTinyInteger('rating')->nullable(false)->change();
        });
    }
};

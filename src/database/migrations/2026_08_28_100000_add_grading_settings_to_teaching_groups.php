<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_groups', function (Blueprint $table): void {
            $table->string('grading_model', 40)->default('competency_texts_and_grades')->after('denomination');
            $table->boolean('numeric_grades_enabled')->default(false)->after('grading_model');
        });
        Schema::create('teaching_group_grade_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('label');
            $table->unsignedTinyInteger('percentage');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->index(['teaching_group_id', 'position']);
        });
        $now = now();
        foreach (DB::table('teaching_groups')->pluck('id') as $groupId) {
            DB::table('teaching_group_grade_components')->insert([
                ['teaching_group_id' => $groupId, 'type' => 'observations', 'label' => 'Beobachtungen im Unterricht', 'percentage' => 50, 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['teaching_group_id' => $groupId, 'type' => 'written_assessments', 'label' => 'Schriftliche Leistungen', 'percentage' => 50, 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_group_grade_components');
        Schema::table('teaching_groups', fn (Blueprint $table) => $table->dropColumn(['grading_model', 'numeric_grades_enabled']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phase_templates', function (Blueprint $table): void {
            $table->text('teacher_interaction')->nullable()->after('description');
            $table->text('learner_activity')->nullable()->after('teacher_interaction');
            $table->text('differentiation')->nullable()->after('learner_activity');
            $table->text('didactic_comment')->nullable()->after('differentiation');
            $table->text('media')->nullable()->after('material');
        });
    }

    public function down(): void
    {
        Schema::table('phase_templates', function (Blueprint $table): void {
            $table->dropColumn(['teacher_interaction', 'learner_activity', 'differentiation', 'didactic_comment', 'media']);
        });
    }
};

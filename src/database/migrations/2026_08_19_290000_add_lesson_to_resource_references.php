<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->nullable()->after('teaching_unit_id')->constrained()->nullOnDelete();
            $table->index(['teaching_unit_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->dropForeign(['lesson_id']);
            $table->dropIndex(['teaching_unit_id', 'lesson_id']);
            $table->dropColumn('lesson_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_topics', function (Blueprint $table): void {
            $table->foreignId('source_curriculum_version_id')->nullable()->after('curriculum_version_id')->constrained('curriculum_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_topics', function (Blueprint $table): void {
            $table->dropForeign(['source_curriculum_version_id']);
            $table->dropColumn('source_curriculum_version_id');
        });
    }
};

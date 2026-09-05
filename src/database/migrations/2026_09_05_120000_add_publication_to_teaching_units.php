<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->text('introduction_text')->nullable()->after('notes');
            $table->foreignId('created_by_user_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('resource_references', function (Blueprint $table): void {
            $table->string('publication_status', 32)->default('not_shared')->after('version');
        });

        Schema::table('resource_links', function (Blueprint $table): void {
            $table->string('publication_status', 32)->default('not_shared')->after('description');
        });

        Schema::table('lesson_phase_resources', function (Blueprint $table): void {
            $table->string('publication_status', 32)->default('not_shared')->after('resource_reference_id');
        });

        Schema::table('lesson_phase_resource_links', function (Blueprint $table): void {
            $table->string('publication_status', 32)->default('not_shared')->after('resource_link_id');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_phase_resource_links', function (Blueprint $table): void {
            $table->dropColumn('publication_status');
        });

        Schema::table('lesson_phase_resources', function (Blueprint $table): void {
            $table->dropColumn('publication_status');
        });

        Schema::table('resource_links', function (Blueprint $table): void {
            $table->dropColumn('publication_status');
        });

        Schema::table('resource_references', function (Blueprint $table): void {
            $table->dropColumn('publication_status');
        });

        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn(['created_by_user_id', 'introduction_text']);
        });
    }
};

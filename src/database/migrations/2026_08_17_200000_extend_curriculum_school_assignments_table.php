<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_school_assignments', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'school_id']);
        });

        Schema::table('curriculum_school_assignments', function (Blueprint $table): void {
            $table->renameColumn('note', 'notes');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_school_assignments', function (Blueprint $table): void {
            $table->renameColumn('notes', 'note');
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'school_id']);
            $table->dropColumn('organization_id');
        });
    }
};

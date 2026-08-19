<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->foreignId('copied_from_id')->nullable()->after('teaching_group_id')->constrained('teaching_units')->nullOnDelete();
            $table->index(['organization_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::table('teaching_units', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('copied_from_id');
            $table->dropIndex('teaching_units_organization_id_title_index');
        });
    }
};

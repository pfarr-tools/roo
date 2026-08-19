<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->foreignId('teaching_unit_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable()->after('original_name');
            $table->index(['teaching_unit_id', 'original_name']);
        });
    }

    public function down(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('teaching_unit_id');
            $table->dropColumn('description');
        });
    }
};

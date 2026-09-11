<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->dropForeign(['teaching_unit_id']);
            $table->foreign('teaching_unit_id')
                ->references('id')
                ->on('teaching_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->dropForeign(['teaching_unit_id']);
            $table->foreign('teaching_unit_id')
                ->references('id')
                ->on('teaching_units')
                ->cascadeOnDelete();
        });
    }
};

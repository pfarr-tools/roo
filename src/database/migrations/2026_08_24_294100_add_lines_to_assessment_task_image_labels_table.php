<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_task_image_labels', function (Blueprint $table): void {
            $table->unsignedInteger('lines')->default(1)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_task_image_labels', function (Blueprint $table): void {
            $table->dropColumn('lines');
        });
    }
};

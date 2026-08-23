<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_booklet_fragments', function (Blueprint $table): void {
            $table->unsignedInteger('end_page')->nullable()->after('page');
        });

        Schema::create('assessment_scan_materializations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 26);
            $table->json('booklet_ids');
            $table->timestamps();
            $table->unique(['assessment_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scan_materializations');

        Schema::table('assessment_booklet_fragments', function (Blueprint $table): void {
            $table->dropColumn('end_page');
        });
    }
};

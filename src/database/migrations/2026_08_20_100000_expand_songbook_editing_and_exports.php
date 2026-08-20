<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->json('layout_data')->nullable();
            $table->string('generated_sheet_path')->nullable();
            $table->timestamp('generated_sheet_at')->nullable();
        });

        Schema::create('song_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('song_version_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('content');
            $table->unsignedInteger('position');
            $table->boolean('is_refrain')->default(false);
            $table->timestamps();
            $table->unique(['song_version_id', 'position']);
        });

        Schema::create('song_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('song_version_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('group_songbook_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_songbook_id')->constrained()->cascadeOnDelete();
            $table->string('format');
            $table->date('through_date')->nullable();
            $table->string('storage_path');
            $table->unsignedInteger('entry_count')->default(0);
            $table->timestamps();
        });

        foreach (['teaching_unit_songbooks' => ['teaching_unit_id', 'teaching_units'], 'lesson_songbooks' => ['lesson_id', 'lessons'], 'phase_songbooks' => ['lesson_phase_id', 'lesson_phases']] as $tableName => [$foreign, $referenced]) {
            Schema::create($tableName, function (Blueprint $table) use ($foreign, $referenced): void {
                $table->foreignId($foreign)->constrained($referenced)->cascadeOnDelete();
                $table->foreignId('group_songbook_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->primary([$foreign, 'group_songbook_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_songbooks');
        Schema::dropIfExists('lesson_songbooks');
        Schema::dropIfExists('teaching_unit_songbooks');
        Schema::dropIfExists('group_songbook_exports');
        Schema::dropIfExists('song_images');
        Schema::dropIfExists('song_parts');
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->dropColumn(['layout_data', 'generated_sheet_path', 'generated_sheet_at']);
        });
    }
};

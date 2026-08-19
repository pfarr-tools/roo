<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('composer')->nullable();
            $table->string('author')->nullable();
            $table->string('copyright_notice')->nullable();
            $table->string('age_group')->nullable();
            $table->string('topics')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'title']);
        });

        Schema::create('song_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Standardfassung');
            $table->string('language', 10)->default('de');
            $table->text('lyrics')->nullable();
            $table->text('notation')->nullable();
            $table->text('chords')->nullable();
            $table->string('rights_status')->default('unknown');
            $table->text('rights_note')->nullable();
            $table->boolean('text_export_allowed')->default(false);
            $table->boolean('metadata_export_allowed')->default(true);
            $table->timestamps();
        });

        Schema::create('song_sheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('song_version_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        foreach (['unit_songs' => ['teaching_unit_id', 'teaching_units'], 'lesson_songs' => ['lesson_id', 'lessons'], 'phase_songs' => ['lesson_phase_id', 'lesson_phases']] as $tableName => [$foreign, $referenced]) {
            Schema::create($tableName, function (Blueprint $table) use ($foreign, $referenced): void {
                $table->foreignId($foreign)->constrained($referenced)->cascadeOnDelete();
                $table->foreignId('song_version_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('position')->default(1);
                $table->timestamps();
                $table->unique([$foreign, 'song_version_id']);
            });
        }

        Schema::create('group_songbooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_group_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title_page_path')->nullable();
            $table->string('title_page_original_name')->nullable();
            $table->string('title_page_mime_type')->nullable();
            $table->unsignedBigInteger('title_page_size')->nullable();
            $table->timestamps();
        });

        Schema::create('group_songbook_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_songbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('song_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('song_number');
            $table->timestamp('added_at')->nullable();
            $table->timestamps();
            $table->unique(['group_songbook_id', 'song_version_id']);
            $table->unique(['group_songbook_id', 'song_number']);
        });

        Schema::create('print_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_songbook_id')->constrained()->cascadeOnDelete();
            $table->timestamp('printed_at');
            $table->unsignedInteger('entry_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_checkpoints');
        Schema::dropIfExists('group_songbook_entries');
        Schema::dropIfExists('group_songbooks');
        Schema::dropIfExists('phase_songs');
        Schema::dropIfExists('lesson_songs');
        Schema::dropIfExists('unit_songs');
        Schema::dropIfExists('song_sheets');
        Schema::dropIfExists('song_versions');
        Schema::dropIfExists('songs');
    }
};

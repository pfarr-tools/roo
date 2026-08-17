<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('data_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->string('external_identifier')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('school_type')->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('school_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('timezone')->default('Europe/Berlin');
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('holiday_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('external_identifier')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('change_reason')->nullable();
            $table->timestamps();
            $table->unique(['school_year_id', 'external_identifier']);
        });

        Schema::create('calendar_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('kind');
            $table->string('label');
            $table->string('change_reason')->nullable();
            $table->timestamps();
            $table->unique(['school_year_id', 'date']);
        });

        Schema::create('school_year_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('kind');
            $table->string('label')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->unique(['school_year_id', 'date']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_year_days');
        Schema::dropIfExists('calendar_exceptions');
        Schema::dropIfExists('holiday_periods');
        Schema::dropIfExists('school_years');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('data_sources');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::dropIfExists('organizations');
    }
};

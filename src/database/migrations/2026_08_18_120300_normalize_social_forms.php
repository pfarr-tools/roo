<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('phase_templates', function (Blueprint $table): void {
            $table->foreignId('social_form_id')->nullable()->after('duration_minutes')->constrained()->nullOnDelete();
            $table->dropColumn('social_form');
        });
    }

    public function down(): void
    {
        Schema::table('phase_templates', function (Blueprint $table): void {
            $table->string('social_form')->nullable()->after('duration_minutes');
            $table->dropConstrainedForeignId('social_form_id');
        });
        Schema::dropIfExists('social_forms');
    }
};

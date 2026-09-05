<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_period_evaluation_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_period_id')->constrained()->cascadeOnDelete();
            $table->string('level', 1)->nullable();
            $table->text('original_text');
            $table->text('text');
            $table->timestamps();
            $table->unique(['report_period_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_period_evaluation_templates');
    }
};

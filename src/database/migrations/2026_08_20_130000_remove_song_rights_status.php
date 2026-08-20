<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->dropColumn(['rights_status', 'rights_note']);
        });
    }

    public function down(): void
    {
        Schema::table('song_versions', function (Blueprint $table): void {
            $table->string('rights_status')->default('unknown');
            $table->text('rights_note')->nullable();
        });
    }
};

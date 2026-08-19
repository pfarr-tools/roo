<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_lessons', function (Blueprint $table): void {
            $table->string('status', 30)->default('assigned')->change();
        });

        DB::table('scheduled_lessons')->where('status', 'planned')->update(['status' => 'assigned']);
    }

    public function down(): void
    {
        DB::table('scheduled_lessons')->whereIn('status', ['assigned', 'ready'])->update(['status' => 'planned']);

        Schema::table('scheduled_lessons', function (Blueprint $table): void {
            $table->string('status', 30)->default('planned')->change();
        });
    }
};

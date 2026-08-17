<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->unique(['organization_id', 'slug']);
        });

        DB::table('school_years')->select(['id', 'name'])->get()->each(function (object $year): void {
            DB::table('school_years')->where('id', $year->id)->update(['slug' => Str::slug($year->name)]);
        });
    }

    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table): void {
            $table->dropUnique('school_years_organization_id_slug_unique');
            $table->dropColumn('slug');
        });
    }
};

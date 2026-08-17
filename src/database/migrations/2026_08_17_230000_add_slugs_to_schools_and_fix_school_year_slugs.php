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
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        foreach (DB::table('schools')->orderBy('id')->get() as $school) {
            $base = Str::slug($school->name) ?: 'schule';
            $slug = $base;
            $suffix = 2;
            while (DB::table('schools')->where('organization_id', $school->organization_id)->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$suffix++;
            }
            DB::table('schools')->where('id', $school->id)->update(['slug' => $slug]);
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique(['organization_id', 'slug']);
        });

        foreach (DB::table('school_years')->get() as $year) {
            DB::table('school_years')->where('id', $year->id)->update(['slug' => Str::slug(str_replace('/', '-', $year->name))]);
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique('schools_organization_id_slug_unique');
            $table->dropColumn('slug');
        });
    }
};

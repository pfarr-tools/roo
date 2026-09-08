<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_periods', function (Blueprint $table): void {
            $table->boolean('whole_grades')->default(false);
            $table->boolean('include_full_school_year')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('report_periods', function (Blueprint $table): void {
            $table->dropColumn(['whole_grades', 'include_full_school_year']);
        });
    }
};

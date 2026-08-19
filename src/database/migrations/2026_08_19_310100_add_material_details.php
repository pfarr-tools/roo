<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_items', function (Blueprint $table): void {
            $table->string('material_number')->nullable()->after('name');
            $table->string('storage_location')->nullable()->after('material_number');
        });
    }

    public function down(): void
    {
        Schema::table('material_items', function (Blueprint $table): void {
            $table->dropColumn(['material_number', 'storage_location']);
        });
    }
};

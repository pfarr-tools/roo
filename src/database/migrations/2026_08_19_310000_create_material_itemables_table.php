<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_itemables', function (Blueprint $table): void {
            $table->foreignId('material_item_id')->constrained()->cascadeOnDelete();
            $table->morphs('material_itemable');
            $table->primary(['material_item_id', 'material_itemable_id', 'material_itemable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_itemables');
    }
};

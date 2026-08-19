<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->string('checksum', 64)->nullable()->after('size');
            $table->string('security_status', 32)->default('pending')->after('checksum');
            $table->string('source', 64)->default('user_upload')->after('security_status');
            $table->unsignedInteger('version')->default(1)->after('source');
            $table->index(['checksum', 'security_status']);
        });
    }

    public function down(): void
    {
        Schema::table('resource_references', function (Blueprint $table): void {
            $table->dropIndex(['checksum', 'security_status']);
            $table->dropColumn(['checksum', 'security_status', 'source', 'version']);
        });
    }
};

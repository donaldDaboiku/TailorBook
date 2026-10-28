<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedInteger('catalogue_free')->nullable()->after('catalogue_extra');
            $table->unsignedInteger('catalogue_pack_amount')->nullable()->after('catalogue_free');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['catalogue_free', 'catalogue_pack_amount']);
        });
    }
};

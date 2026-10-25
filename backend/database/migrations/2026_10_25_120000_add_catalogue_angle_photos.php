<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogue_items', function (Blueprint $table) {
            $table->string('image_back_path')->nullable()->after('image_path');
            $table->string('image_side_path')->nullable()->after('image_back_path');
        });
    }

    public function down(): void
    {
        Schema::table('catalogue_items', function (Blueprint $table) {
            $table->dropColumn(['image_back_path', 'image_side_path']);
        });
    }
};

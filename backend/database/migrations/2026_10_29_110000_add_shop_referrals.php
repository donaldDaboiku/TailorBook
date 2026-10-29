<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salespeople', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 12)->unique();
            $table->timestamps();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('invite_code', 12)->nullable()->unique();
            $table->foreignUuid('referred_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignUuid('referred_by_salesperson_id')->nullable()->constrained('salespeople')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_salesperson_id');
            $table->dropConstrainedForeignId('referred_by_business_id');
            $table->dropColumn('invite_code');
        });

        Schema::dropIfExists('salespeople');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_business_id')->constrained('businesses')->cascadeOnDelete();
            // ponytail: one row per completed set of paid referrals (1 = first five). A rejected row keeps that set from paying out twice.
            $table->unsignedInteger('milestone');
            $table->string('status', 20);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('granted_until')->nullable();
            $table->timestamps();

            $table->unique(['referrer_business_id', 'milestone']);
        });

        Schema::table('salespeople', function (Blueprint $table) {
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('salespeople', function (Blueprint $table) {
            $table->dropColumn('active');
        });

        Schema::dropIfExists('referral_grants');
    }
};

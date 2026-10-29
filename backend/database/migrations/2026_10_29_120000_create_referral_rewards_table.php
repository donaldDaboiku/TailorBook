<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('referred_business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('granted_until')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->foreignUuid('referrer_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignUuid('salesperson_id')->nullable()->constrained('salespeople')->nullOnDelete();
            $table->foreignUuid('referred_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignUuid('reward_id')->nullable()->constrained('referral_rewards')->nullOnDelete();
            $table->string('summary');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_activities');
        Schema::dropIfExists('referral_rewards');
    }
};

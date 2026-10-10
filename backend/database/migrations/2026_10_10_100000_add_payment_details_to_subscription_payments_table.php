<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('channel', 40)->nullable()->after('currency');
            $table->string('gateway_response')->nullable()->after('channel');
            $table->string('failure_message')->nullable()->after('gateway_response');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn([
                'channel',
                'gateway_response',
                'failure_message',
                'refunded_at',
            ]);
        });
    }
};

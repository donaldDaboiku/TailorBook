<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedInteger('catalogue_extra')->default(0);
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('purpose', 20)->default('plan');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('catalogue_extra');
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};

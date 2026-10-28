<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_jobs', function (Blueprint $table) {
            $table->unsignedSmallInteger('finish_days')->nullable()->after('service_date');
        });
    }

    public function down(): void
    {
        Schema::table('customer_jobs', function (Blueprint $table) {
            $table->dropColumn('finish_days');
        });
    }
};

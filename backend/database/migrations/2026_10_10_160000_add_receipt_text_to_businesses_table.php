<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('receipt_header', 240)->nullable()->after('currency');
            $table->string('receipt_footer', 240)->nullable()->after('receipt_header');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['receipt_header', 'receipt_footer']);
        });
    }
};

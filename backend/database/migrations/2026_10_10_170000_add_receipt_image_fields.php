<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('receipt_footer');
            $table->string('signature_name', 80)->nullable()->after('logo_path');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number', 20)->nullable()->after('note');
            $table->unique(['business_id', 'receipt_number']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'receipt_number']);
            $table->dropColumn('receipt_number');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'signature_name']);
        });
    }
};

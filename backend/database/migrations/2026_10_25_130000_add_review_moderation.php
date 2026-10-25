<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_reviews', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('body');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('moderated_at')->nullable()->after('submitted_at');
            $table->foreignId('moderated_by')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 200)->nullable()->after('moderated_by');
        });

        DB::table('shop_reviews')->where('approved', true)->update(['status' => 'published']);
        DB::table('shop_reviews')->update(['submitted_at' => DB::raw('updated_at')]);

        Schema::table('shop_reviews', function (Blueprint $table) {
            $table->dropColumn('approved');
        });
    }

    public function down(): void
    {
        Schema::table('shop_reviews', function (Blueprint $table) {
            $table->boolean('approved')->default(false);
        });

        DB::table('shop_reviews')->where('status', 'published')->update(['approved' => true]);

        Schema::table('shop_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['status', 'submitted_at', 'moderated_at', 'rejection_reason']);
        });
    }
};

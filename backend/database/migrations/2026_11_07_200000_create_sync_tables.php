<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('subject_type', 80);
            $table->uuid('subject_id');
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key']);
        });

        Schema::create('record_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('record_type', 80);
            $table->uuid('record_id');
            $table->json('previous');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_revisions');
        Schema::dropIfExists('sync_actions');
    }
};

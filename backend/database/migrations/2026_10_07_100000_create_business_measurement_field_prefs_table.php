<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_measurement_field_prefs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('measurement_field_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'measurement_field_id'], 'biz_measure_field_pref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_measurement_field_prefs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->date('notice_on');
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'notice_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_notices');
    }
};

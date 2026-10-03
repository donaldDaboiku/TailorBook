<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('whatsapp_phone')->nullable();
            $table->string('country', 2)->default('NG');
            $table->enum('measurement_unit', ['cm', 'in'])->default('cm');
            $table->string('currency', 3)->default('NGN');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('whatsapp_phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'name']);
        });

        Schema::create('measurement_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('measurement_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('measurement_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['measurement_template_id', 'key']);
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('measurement_template_id')->constrained()->restrictOnDelete();
            $table->date('taken_on');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'taken_on']);
        });

        Schema::create('measurement_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('measurement_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('measurement_field_id')->constrained()->restrictOnDelete();
            $table->decimal('value_cm', 8, 2);
            $table->timestamps();
            $table->unique(['measurement_id', 'measurement_field_id']);
        });

        Schema::create('customer_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('agreed_amount', 12, 2);
            $table->date('service_date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_job_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->enum('method', ['cash', 'bank_transfer', 'pos', 'other']);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'paid_on']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->foreignUuid('business_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->string('description')->nullable();
            $table->enum('method', ['cash', 'bank_transfer', 'pos', 'other']);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'spent_on']);
        });

        DB::statement('create unique index customers_business_phone_active_unique on customers (business_id, phone) where deleted_at is null');
        DB::statement('create unique index measurement_templates_system_slug_unique on measurement_templates (slug) where business_id is null');
        DB::statement('create unique index measurement_templates_business_slug_unique on measurement_templates (business_id, slug) where business_id is not null');
        DB::statement('create unique index expense_categories_system_slug_unique on expense_categories (slug) where business_id is null');
        DB::statement('create unique index expense_categories_business_slug_unique on expense_categories (business_id, slug) where business_id is not null');

        DB::statement('alter table measurement_values add constraint measurement_values_value_cm_positive check (value_cm > 0)');
        DB::statement('alter table customer_jobs add constraint customer_jobs_agreed_amount_positive check (agreed_amount > 0)');
        DB::statement('alter table payments add constraint payments_amount_positive check (amount > 0)');
        DB::statement('alter table expenses add constraint expenses_amount_positive check (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('customer_jobs');
        Schema::dropIfExists('measurement_values');
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('measurement_fields');
        Schema::dropIfExists('measurement_templates');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('businesses');
    }
};

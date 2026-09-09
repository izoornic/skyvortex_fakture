<?php

use App\Enums\BillingMode;
use App\Enums\ContractFrequency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->index()->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('reference')->nullable();

            $table->string('frequency', 20)->default(ContractFrequency::Monthly->value);

            // Whether the draft bills the month that has passed or the one that
            // is starting. It decides the period and the supply date.
            $table->string('billing_mode', 20)->default(BillingMode::Arrears->value);

            // Day of the month the draft appears. Clamped to the length of the
            // month, so 31 still works in February.
            $table->unsignedTinyInteger('generation_day')->default(1);

            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            $table->char('currency', 3)->default('RSD');
            $table->unsignedSmallInteger('payment_days')->default(15);

            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();

            // Copied onto every document the contract makes.
            $table->text('note')->nullable();
            $table->text('internal_note')->nullable();

            $table->boolean('is_active')->default(true)->index();

            // The last period already turned into a draft, so a second run on
            // the same month cannot produce a second document.
            $table->unsignedSmallInteger('last_generated_year')->nullable();
            $table->unsignedTinyInteger('last_generated_month')->nullable();
            $table->timestamp('last_generated_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

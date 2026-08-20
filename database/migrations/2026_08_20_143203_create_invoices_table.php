<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->index()->constrained()->restrictOnDelete();

            $table->string('type', 30)->index();
            $table->string('status', 30)->default(InvoiceStatus::Draft->value)->index();

            // Assigned only when the document is issued, so drafts never take
            // up a number they might not use.
            $table->string('number', 30)->nullable();
            $table->unsignedSmallInteger('number_year')->nullable();
            $table->unsignedInteger('number_sequence')->nullable();

            // Invoicing is organised by month; this is the period the document
            // belongs to, independent of when it was issued.
            $table->unsignedSmallInteger('period_year')->index();
            $table->unsignedTinyInteger('period_month')->index();

            $table->date('issue_date');
            $table->date('supply_date');
            $table->date('due_date');

            $table->char('currency', 3)->default('RSD');
            // Entered by hand, no exchange rate service. 1 for RSD documents.
            $table->decimal('exchange_rate', 15, 6)->default(1);

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            // Converted at the rate above, so reports do not have to redo it.
            $table->decimal('subtotal_rsd', 15, 2)->default(0);
            $table->decimal('vat_total_rsd', 15, 2)->default(0);
            $table->decimal('total_rsd', 15, 2)->default(0);

            $table->string('payment_reference', 30)->nullable()->index();
            $table->string('payment_reference_model', 2)->default('97');

            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vat_exemption_reason_id')->nullable()->constrained()->nullOnDelete();

            $table->string('place_of_issue')->nullable();
            $table->text('note')->nullable();
            $table->text('internal_note')->nullable();

            // Filled in M3 when the document comes from a contract, and when it
            // was copied from an earlier one.
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'type', 'number']);
            $table->index(['company_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

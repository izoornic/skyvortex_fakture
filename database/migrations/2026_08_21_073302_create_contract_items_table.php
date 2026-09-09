<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same shape as `invoice_items`, minus the computed amounts: those are
     * worked out per document, from the rates in force when it is made.
     */
    public function up(): void
    {
        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->index()->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('unit_code', 10)->default('H87');
            $table->string('unit_symbol', 20)->nullable();

            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->string('vat_category', 5)->default('S');
            $table->foreignId('vat_exemption_reason_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_items');
    }
};

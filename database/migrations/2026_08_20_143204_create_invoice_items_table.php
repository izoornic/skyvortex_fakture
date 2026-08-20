<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->index()->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->string('name');
            $table->text('description')->nullable();

            // The unit code is copied, not referenced: the codebook may change
            // later, an issued document may not.
            $table->string('unit_code', 10)->default('H87');
            $table->string('unit_symbol', 20)->nullable();

            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->string('vat_category', 5)->default('S');
            $table->foreignId('vat_exemption_reason_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('line_discount', 15, 2)->default(0);
            $table->decimal('line_vat', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

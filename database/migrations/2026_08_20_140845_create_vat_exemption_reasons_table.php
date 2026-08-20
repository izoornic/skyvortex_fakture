<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_exemption_reasons', function (Blueprint $table) {
            $table->id();

            // Code as required by SEF / UBL, e.g. PDV-RS-33. Deliberately not
            // seeded: the list comes from current tax regulation, not from us.
            $table->string('code', 30)->unique();

            $table->string('vat_category', 5)->index();
            $table->string('description');
            $table->string('legal_basis')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_exemption_reasons');
    }
};

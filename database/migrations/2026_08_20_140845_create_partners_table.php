<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();

            $table->string('type', 20)->index();
            $table->string('name');

            $table->char('pib', 9)->nullable()->index();
            $table->char('registration_number', 8)->nullable();

            // Personal data: encrypted at rest, so the column has to hold the
            // ciphertext rather than 13 characters.
            $table->text('jmbg')->nullable();

            $table->string('vat_id', 30)->nullable();
            $table->string('jbkjs', 5)->nullable();

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->char('country_code', 2)->default('RS');

            $table->boolean('in_vat_system')->default(false);

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('contact_person')->nullable();

            $table->unsignedSmallInteger('payment_days')->default(15);
            $table->char('default_currency', 3)->default('RSD');

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            // The same PIB may appear under different issuers, but only once
            // for a given one.
            $table->unique(['company_id', 'pib']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};

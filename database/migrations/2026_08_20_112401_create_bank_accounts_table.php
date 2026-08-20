<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();

            $table->string('bank_name');
            $table->string('account_number', 25);
            $table->string('iban', 34)->nullable();
            $table->string('swift', 11)->nullable();
            $table->char('currency', 3)->default('RSD');

            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['company_id', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};

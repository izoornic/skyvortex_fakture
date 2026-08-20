<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('short_name')->nullable();
            $table->char('pib', 9)->unique();
            $table->char('registration_number', 8)->index();

            $table->string('address');
            $table->string('city');
            $table->string('postal_code', 10)->nullable();
            $table->char('country_code', 2)->default('RS');

            $table->string('activity_code', 10)->nullable();
            $table->string('jbkjs', 5)->nullable();

            $table->boolean('in_vat_system')->default(true);
            $table->date('vat_registered_at')->nullable();

            $table->char('default_currency', 3)->default('RSD');

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

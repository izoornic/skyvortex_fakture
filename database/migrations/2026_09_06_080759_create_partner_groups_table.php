<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();

            $table->string('name');

            // The address the consolidated bundle is sent to. It belongs to the
            // group, not to any of its partners.
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_groups');
    }
};

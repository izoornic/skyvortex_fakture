<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();

            $table->string('type', 30);
            $table->unsignedSmallInteger('year');

            // Last number handed out. Set when the company is created, so numbering
            // continues after documents issued outside the application.
            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            $table->unique(['company_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};

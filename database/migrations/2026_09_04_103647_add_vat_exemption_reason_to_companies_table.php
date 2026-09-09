<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The legal basis a company issues under when it charges no VAT.
     *
     * A company outside the VAT system states the same article on every
     * document, so it belongs on the company, not on each invoice — left to be
     * picked by hand it stays empty, and an invoice without it is rejected by
     * SEF.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('vat_exemption_reason_id')
                ->nullable()
                ->after('vat_registered_at')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_exemption_reason_id');
        });
    }
};

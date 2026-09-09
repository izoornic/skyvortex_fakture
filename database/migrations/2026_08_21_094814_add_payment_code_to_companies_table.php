<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payment code printed into the NBS IPS QR code. 221 — a transfer for
     * goods and services — fits an invoice between companies, but it is the
     * issuer's call, so it lives on the issuer.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->char('payment_code', 3)->default(Company::DEFAULT_PAYMENT_CODE)->after('default_currency');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('payment_code');
        });
    }
};

<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents leave this house by mail, so being valid without a seal is the rule
 * and not the exception: the mark is on by default and taken off by hand.
 *
 * Contracts and drafts are brought along, because they carried `false` only by
 * accident of the column being introduced that way. Issued documents are left
 * alone — how a document already sent out prints must not change under it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('valid_without_signature')->default(true)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('valid_without_signature')->default(true)->change();
        });

        DB::table('contracts')->update(['valid_without_signature' => true]);

        DB::table('invoices')
            ->where('status', InvoiceStatus::Draft->value)
            ->update(['valid_without_signature' => true]);
    }

    public function down(): void
    {
        DB::table('contracts')->update(['valid_without_signature' => false]);

        DB::table('invoices')
            ->where('status', InvoiceStatus::Draft->value)
            ->update(['valid_without_signature' => false]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('valid_without_signature')->default(false)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('valid_without_signature')->default(false)->change();
        });
    }
};

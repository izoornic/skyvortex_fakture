<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A document sent by mail carries no seal and no signature, so it has to say
 * that it is valid without them. It is a per-document choice, and a contract
 * passes it on to every draft it makes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('valid_without_signature')->default(false)->after('note');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('valid_without_signature')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('valid_without_signature');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('valid_without_signature');
        });
    }
};

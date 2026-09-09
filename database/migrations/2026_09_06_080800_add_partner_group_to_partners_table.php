<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // Membership is optional and at most one: a partner is either in
            // exactly one group or in none, and then invoices go to the partner
            // as before. Deleting a group must not delete its partners.
            $table->foreignId('partner_group_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_group_id');
        });
    }
};

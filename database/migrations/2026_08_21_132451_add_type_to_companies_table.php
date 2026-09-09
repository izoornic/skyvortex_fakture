<?php

use App\Enums\CompanyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything issued so far came from an ordinary company, so that is what
     * the existing rows become.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('type', 30)->default(CompanyType::LegalEntity->value)->after('short_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

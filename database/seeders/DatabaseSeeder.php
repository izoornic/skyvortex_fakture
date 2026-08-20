<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\InvoiceNumberSequence;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Administrator',
            'email' => 'admin@skyvortex.test',
        ]);

        $bookkeeper = User::factory()->bookkeeper()->create([
            'name' => 'Knjigovođa',
            'email' => 'knjigovodja@skyvortex.test',
        ]);

        $companies = Company::factory()
            ->count(3)
            ->create()
            ->each(function (Company $company, int $index) {
                BankAccount::factory()->primary()->create([
                    'company_id' => $company->id,
                ]);

                // Numbering continues after documents issued outside the app.
                InvoiceNumberSequence::create([
                    'company_id' => $company->id,
                    'type' => DocumentType::Invoice,
                    'year' => now()->year,
                    'last_number' => ($index + 1) * 7,
                ]);
            });

        // The bookkeeper only reaches the first company; the admin reaches all.
        $bookkeeper->companies()->attach($companies->first());

        $this->command?->info("Admin: {$admin->email} / password");
        $this->command?->info("Knjigovođa: {$bookkeeper->email} / password");
    }
}

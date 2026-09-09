<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A working installation with no documents in it: codebooks, the real companies
 * with their bank accounts, partner groups, partners and standing contracts, and
 * the two accounts that sign in. Fakture come out of the contracts, so none are
 * seeded.
 *
 * This is what a fresh production installation is filled with, which is why every
 * seeder below matches its rows on a natural key and may be re-run.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            CompanySeeder::class,
            PartnerGroupSeeder::class,
            PartnerSeeder::class,
            ContractSeeder::class,
        ]);

        $admin = $this->user('Administrator', 'admin@skyvortex.test', UserRole::Admin);
        $bookkeeper = $this->user('Knjigovođa', 'knjigovodja@skyvortex.test', UserRole::Bookkeeper);

        // The bookkeeper only reaches Digital Skyvortex; the admin reaches all.
        $skyvortex = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->first();

        if ($skyvortex !== null) {
            $bookkeeper->companies()->syncWithoutDetaching($skyvortex);
        }

        $this->command?->info("Admin: {$admin->email} / password");
        $this->command?->info("Knjigovođa: {$bookkeeper->email} / password");
    }

    /**
     * The password is hashed by the model cast; `email_verified_at` is not
     * fillable, so it is written after the row exists.
     */
    private function user(string $name, string $email, UserRole $role): User
    {
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'role' => $role,
            'password' => 'password',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PartnerGroup;
use Illuminate\Database\Seeder;

/**
 * The managers Digital Skyvortex delivers to: one PDF with every invoice of the
 * housing communities they run. Matched on the name within the company, which
 * is the unique key the table already carries.
 */
class PartnerGroupSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->first();

        if ($company === null) {
            $this->command?->warn('Digital Skyvortex nije pronađen, grupe partnera preskočene.');

            return;
        }

        foreach ($this->digitalSkyvortexGroups() as $group) {
            PartnerGroup::updateOrCreate(
                ['company_id' => $company->getKey(), 'name' => $group['name']],
                $group + ['company_id' => $company->getKey()],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function digitalSkyvortexGroups(): array
    {
        return [
            ['name' => 'Miloš Stefanović', 'email' => 'dobar.komsija034@gmail.com', 'contact_person' => 'Miloš S', 'phone' => '+381606000434'],
            ['name' => 'Aleksandar Filopvić', 'email' => 'upravnikaca@gmail.com', 'contact_person' => 'Šaki', 'phone' => '+381608318133'],
            ['name' => 'Dejana Krivokuća', 'email' => 'dejana.krivokuca@gmail.com', 'contact_person' => 'Dejana Krivokuća', 'phone' => '+381641233426'],
            ['name' => 'Aleksandar Milić', 'email' => 'milic984kg@gmail.com', 'contact_person' => 'Aleksandar Milić', 'phone' => '+381653350000'],
            ['name' => 'Nebojša Aleksić', 'email' => 'n.aleksic@mts.rs', 'contact_person' => 'Nebojša Aleksić', 'phone' => '+38166374200'],
        ];
    }
}

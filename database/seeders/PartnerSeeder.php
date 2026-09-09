<?php

namespace Database\Seeders;

use App\Enums\PartnerType;
use App\Models\Company;
use App\Models\Partner;
use App\Models\PartnerGroup;
use Illuminate\Database\Seeder;

/**
 * Real buyers of Digital Skyvortex, mostly the stambene zajednice it invoices
 * every month. Matched on PIB within the company, so re-running the seeder
 * refreshes a partner instead of adding a second copy of it.
 *
 * `group` names a row of `PartnerGroupSeeder`; it is resolved to the group's id
 * here so the list below never carries a database key.
 */
class PartnerSeeder extends Seeder
{
    /**
     * What every partner below shares; only the differences are listed per row.
     */
    private const DEFAULTS = [
        'country_code' => 'RS',
        'in_vat_system' => false,
        'payment_days' => 30,
        'default_currency' => 'RSD',
        'is_active' => true,
    ];

    public function run(): void
    {
        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->first();

        if ($company === null) {
            $this->command?->warn('Digital Skyvortex nije pronađen, partneri preskočeni.');

            return;
        }

        $groups = PartnerGroup::where('company_id', $company->getKey())->pluck('id', 'name');

        foreach ($this->digitalSkyvortexPartners() as $partner) {
            $group = $partner['group'] ?? null;
            unset($partner['group']);

            if ($group !== null && ! $groups->has($group)) {
                $this->command?->warn("Grupa „{$group}\" ne postoji, partner „{$partner['name']}\" ostaje bez nje.");
            }

            $partner['partner_group_id'] = $group === null ? null : $groups->get($group);

            Partner::updateOrCreate(
                ['company_id' => $company->getKey(), 'pib' => $partner['pib']],
                $partner + self::DEFAULTS + ['company_id' => $company->getKey()],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function digitalSkyvortexPartners(): array
    {
        return [
            ['type' => PartnerType::LegalEntity, 'name' => 'Zeta System DOO', 'pib' => '102054577', 'registration_number' => '06967361', 'address' => 'Goldsvordijeva 1', 'city' => 'Beograd', 'postal_code' => '11000', 'email' => 'tjovanovic@zeta.rs', 'contact_person' => 'Tijana Jovanović'],
            ['type' => PartnerType::Entrepreneur, 'name' => 'MILOŠ STEFANOVIĆ PR DOBAR KOMŠIJA 34', 'pib' => '111947782', 'registration_number' => '65760100', 'address' => 'Ljubićska br.15/2', 'city' => 'Kragujevac', 'postal_code' => '34000', 'email' => 'dobar.komsija034@gmail.com', 'contact_person' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Kopaonička 1 V', 'pib' => '110498123', 'registration_number' => '18023253', 'address' => 'Kopaonička 1/4', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Božane Prpić 13A', 'pib' => '110553989', 'registration_number' => '18064561', 'address' => 'Božane Prpić 13A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'NA', 'group' => 'Nebojša Aleksić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Andre Marinkovića 3', 'pib' => '110806618', 'registration_number' => '18198851', 'address' => 'Andre Marinkovića 3', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Dragoljuba Milovanovića Bene 57', 'pib' => '110770237', 'registration_number' => '18191385', 'address' => 'Dragoljuba Milovanovića Bene 57', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Dr Zorana Đinđića 9', 'pib' => '104097288', 'registration_number' => '17637258', 'address' => 'Dr Zorana Đinđića 9', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Dragana Simića 7', 'pib' => '110976882', 'registration_number' => '18242028', 'address' => 'Dragana Simića 7', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Gavrila Principa 20', 'pib' => '109074600', 'registration_number' => '17875507', 'address' => 'Gavrila Principa 20', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Gavrila Principa 24', 'pib' => '112028241', 'registration_number' => '18327449', 'address' => 'Gavrila Principa 24', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Ilindenska 30', 'pib' => '110898337', 'registration_number' => '18219646', 'address' => 'Ilindenska 30', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Ilindenska 38, 38A', 'pib' => '110862914', 'registration_number' => '18207907', 'address' => 'Ilindenska 38', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Jovana Petrovića Kovača 2A', 'pib' => '110476860', 'registration_number' => '18026023', 'address' => 'Jovana Petrovića Kovača 2A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Janka Veselinovića 27', 'pib' => '111133965', 'registration_number' => '18264617', 'address' => 'Janka Veselinovića 27', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Jovana Cvijića 2', 'pib' => '112142080', 'registration_number' => '18333830', 'address' => 'Jovana Cvijića 2', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Kamenička 5', 'pib' => '110745428', 'registration_number' => '18182076', 'address' => 'Kamenička 5', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Kazimira Veljkovića 18A', 'pib' => '111110155', 'registration_number' => '18262126', 'address' => 'Kazimira Veljkovića 18A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Kralja Milana IV 11', 'pib' => '110933472', 'registration_number' => '18231611', 'address' => 'Kralja Milana IV 11', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Kralja Milana IV 3', 'pib' => '110911282', 'registration_number' => '18224470', 'address' => 'Kralja Milana IV 3', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Obilićeva 7', 'pib' => '111222736', 'registration_number' => '18277913', 'address' => 'Obilićeva 7', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Prvog maja 2A', 'pib' => '111362480', 'registration_number' => '18292947', 'address' => 'Prvog maja 2A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Rudnička 3', 'pib' => '112449142', 'registration_number' => '18352079', 'address' => 'Rudnička 3', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Rudnička 7', 'pib' => '112306648', 'registration_number' => '18345102', 'address' => 'Rudnička 7', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Skerlićeva 7', 'pib' => '111535643', 'registration_number' => '18306921', 'address' => 'Skerlićeva 7', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Visokog Stevana 20V', 'pib' => '111322955', 'registration_number' => '18288664', 'address' => 'Visokog Stevana 20V', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Zmaj Jovina 2, 2A', 'pib' => '111388447', 'registration_number' => '18295261', 'address' => 'Zmaj Jovina 2, 2A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Zmaj Jovina 26', 'pib' => '111356690', 'registration_number' => '18292505', 'address' => 'Zmaj Jovina 26', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Zmaj Jovina 4', 'pib' => '111324889', 'registration_number' => '18288877', 'address' => 'Zmaj Jovina 4', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Zmaj Jovina 53A', 'pib' => '111495878', 'registration_number' => '18303523', 'address' => 'Zmaj Jovina 53A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Laze Marinkovića 27', 'pib' => '112821023', 'registration_number' => '18365529', 'address' => 'Laze Marinkovića 27', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Slobodana Perovića 2A 2B', 'pib' => '112872998', 'registration_number' => '18368439', 'address' => 'Slobodana Perovića 2A 2B', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Rudnička 7A', 'pib' => '110862998', 'registration_number' => '18208369', 'address' => 'Rudnička 7A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Laze Marinkovića 27A', 'pib' => '113375200', 'registration_number' => '18384124', 'address' => 'Laze Marinkovića 27A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Neznanog junaka 1', 'pib' => '104857714', 'registration_number' => '17668030', 'address' => 'Neznanog junaka 1', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'LP'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Jovana Petrovića Kovača 7V', 'pib' => '110635058', 'registration_number' => '17958801', 'address' => 'Jovana Petrovića Kovača 7V', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Karađorđeva 26, 26A, 26B, 26V', 'pib' => '113595319', 'registration_number' => '18393085', 'address' => 'Karađorđeva 26', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Vojvode Putnika 33, 35, 37', 'pib' => '113726748', 'registration_number' => '18397161', 'address' => 'Vojvode Putnika 33', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MM'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Dr Zorana Đinđića 7A', 'pib' => '113689544', 'registration_number' => '18396211', 'address' => 'Dr Zorana Đinđića 7A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'ZK'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Laze Marinkovića 27B', 'pib' => '114013537', 'registration_number' => '18406721', 'address' => 'Laze Marinkovića 27B', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Branislava Nušića 14', 'pib' => '113048564', 'registration_number' => '18375109', 'address' => 'Branislava Nušića 14', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'SR'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Slovačkih pobunjenika 9', 'pib' => '111101443', 'registration_number' => '18260719', 'address' => 'Slovačkih pobunjenika 9', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'ZD'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Ibarskih rudara 9', 'pib' => '111221166', 'registration_number' => '18068338', 'address' => 'Ibarskih rudara 9', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Dr Zorana Đinđića 6, 6A', 'pib' => '110785724', 'registration_number' => '18193337', 'address' => 'Dr Zorana Đinđića 6', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Tanaska Rajića 68, 68A', 'pib' => '107005791', 'registration_number' => '17816799', 'address' => 'Tanaska Rajića 68, 68A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Kazimira Veljkovića 4', 'pib' => '110829494', 'registration_number' => '18092697', 'address' => 'Kazimira Veljkovića 4', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Oplenačka 1', 'pib' => '110660083', 'registration_number' => '18140519', 'address' => 'Oplenačka 1', 'city' => 'Rača Kragujevačka', 'postal_code' => '34210', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Đure Jakšića 10', 'pib' => '110660026', 'registration_number' => '18140489', 'address' => 'Đure Jakšića 10', 'city' => 'Rača Kragujevačka', 'postal_code' => '34210', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Đure Jakšića 8', 'pib' => '110659956', 'registration_number' => '18140438', 'address' => 'Đure Jakšića 8', 'city' => 'Rača Kragujevačka', 'postal_code' => '34210', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Karađorđeva 34', 'pib' => '110659534', 'registration_number' => '18140292', 'address' => 'Karađorđeva 34', 'city' => 'Rača Kragujevačka', 'postal_code' => '34210', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Karađorđeva 45A, 45V, 45G, 45D', 'pib' => '110719563', 'registration_number' => '18169703', 'address' => 'Karađorđeva 45A, 45V, 45G, 45D', 'city' => 'Rača Kragujevačka', 'postal_code' => '34210', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Zmaj Jovina 22', 'pib' => '111276780', 'registration_number' => '18284430', 'address' => 'Zmaj Jovina 22', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Pariske komune 3', 'pib' => '112150100', 'registration_number' => '18334470', 'address' => 'Pariske komune 3', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Gavrila Principa 36', 'pib' => '110696856', 'registration_number' => '18157349', 'address' => 'Gavrila Principa 36', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AM', 'group' => 'Aleksandar Milić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Lovćenska 10', 'pib' => '112474186', 'registration_number' => '18353539', 'address' => 'Lovćenska 10', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Vojvode Putnika 62', 'pib' => '112733929', 'registration_number' => '18362414', 'address' => 'Vojvode Putnika 62', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Vojvode Putnika 64', 'pib' => '112706913', 'registration_number' => '18361531', 'address' => 'Vojvode Putnika 64', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Milovana Gušića 87/B', 'pib' => '112884022', 'registration_number' => '18368846', 'address' => 'Milovana Gušića 87/B', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Daničićeva 69', 'pib' => '113256514', 'registration_number' => '18361892', 'address' => 'Daničićeva 69', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Tanaska Rajića 60', 'pib' => '109960521', 'registration_number' => '17901362', 'address' => 'Tanaska Rajića 60', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Visokog Stevana 8a', 'pib' => '110549755', 'registration_number' => '18064383', 'address' => 'Visokog Stevana 8a', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Visokog Stevana 3', 'pib' => '110746919', 'registration_number' => '18182696', 'address' => 'Visokog Stevana 3', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Janka Veselinovića 93', 'pib' => '113262826', 'registration_number' => '18363666', 'address' => 'Janka Veselinovića 93', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Ilindenska 34', 'pib' => '110976489', 'registration_number' => '18241889', 'address' => 'Ilindenska 34', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Karađorđeva 44', 'pib' => '112426269', 'registration_number' => '18350866', 'address' => 'Karađorđeva 44', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Cara Lazara 1', 'pib' => '110498107', 'registration_number' => '18023024', 'address' => 'Cara Lazara 1', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Ilindenska 40', 'pib' => '110780517', 'registration_number' => '18192764', 'address' => 'Ilindenska 40', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Božane Prpić 10', 'pib' => '114697495', 'registration_number' => '18429292', 'address' => 'Božane Prpić 10', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Kneza Mihaila 170', 'pib' => '110384279', 'registration_number' => '17973193', 'address' => 'Kneza Mihaila 170', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DJ'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Dimitrija Tucovića 29', 'pib' => '114881092', 'registration_number' => '18435373', 'address' => 'Dimitrija Tucovića 29', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'VM'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Daničićeva 30', 'pib' => '110746951', 'registration_number' => '18182734', 'address' => 'Daničićeva 30', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Pavla Beljanskog 1', 'pib' => '111548422', 'registration_number' => '21500348', 'address' => 'Pavla Beljanskog 1', 'city' => 'Veliko Gradište', 'postal_code' => '12220', 'notes' => 'NA', 'group' => 'Nebojša Aleksić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Cara Lazara 8', 'pib' => '115148219', 'registration_number' => '18442884', 'address' => 'Cara Lazara 8', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Husinjska 1', 'pib' => '115319214', 'registration_number' => '18396661', 'address' => 'Husinjska 1', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Obilićeva 10A', 'pib' => '110488620', 'registration_number' => '18034972', 'address' => 'Obilićeva 10A', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Milovana Gušića 87', 'pib' => '112878108', 'registration_number' => '18368579', 'address' => 'Milovana Gušića 87', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'DK', 'group' => 'Dejana Krivokuća'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Atinska 44', 'pib' => '110636544', 'registration_number' => '18124823', 'address' => 'Atinska 44', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Bregalnička 49', 'pib' => '110417370', 'registration_number' => '18003112', 'address' => 'Bregalnička 49', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'AF', 'group' => 'Aleksandar Filopvić'],
            ['type' => PartnerType::HousingCommunity, 'name' => 'SZ Prvoslava Stojanovića 16', 'pib' => '115072116', 'registration_number' => '18441179', 'address' => 'Prvoslava Stojanovića 16', 'city' => 'Kragujevac', 'postal_code' => '34000', 'notes' => 'MS', 'group' => 'Miloš Stefanović'],
        ];
    }
}

<?php

use App\Http\Controllers\CompanyLogoController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PartnerGroupBundlePdfController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Volt::route('companies', 'companies.index')->name('companies.index');
    Volt::route('companies/create', 'companies.form')->name('companies.create');
    Volt::route('companies/{company}/edit', 'companies.form')->name('companies.edit');
    Route::get('companies/{company}/logo', CompanyLogoController::class)->name('companies.logo');

    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.form')->name('users.create');
    Volt::route('users/{user}/edit', 'users.form')->name('users.edit');

    Volt::route('partners', 'partners.index')->name('partners.index');
    Volt::route('partners/create', 'partners.form')->name('partners.create');
    Volt::route('partners/import', 'partners.import')->name('partners.import');
    Volt::route('partners/{partner}/edit', 'partners.form')->name('partners.edit');

    Volt::route('grupe-partnera', 'partner-groups.index')->name('partner-groups.index');
    Volt::route('grupe-partnera/nova', 'partner-groups.form')->name('partner-groups.create');
    Volt::route('grupe-partnera/{partnerGroup}/izmena', 'partner-groups.form')->name('partner-groups.edit');

    Volt::route('ugovori', 'contracts.index')->name('contracts.index');
    Volt::route('ugovori/novi', 'contracts.form')->name('contracts.create');
    Volt::route('ugovori/{contract}/izmena', 'contracts.form')->name('contracts.edit');

    Volt::route('fakture', 'invoices.index')->name('invoices.index');
    Volt::route('fakture/mesecno', 'invoices.monthly-run')->name('invoices.monthly');

    // Before fakture/{invoice}: a static segment only wins if it is registered first.
    Volt::route('fakture/objedinjena-posiljka', 'partner-groups.bundle')->name('partner-groups.bundle');
    Route::get('fakture/grupa/{partnerGroup}/pdf', PartnerGroupBundlePdfController::class)
        ->name('partner-groups.pdf');

    Volt::route('fakture/nova', 'invoices.form')->name('invoices.create');
    Volt::route('fakture/{invoice}', 'invoices.show')->name('invoices.show');
    Volt::route('fakture/{invoice}/izmena', 'invoices.form')->name('invoices.edit');
    Route::get('fakture/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');

    Volt::route('sifarnici/valute', 'codebooks.currencies')->name('codebooks.currencies');
    Volt::route('sifarnici/jedinice-mere', 'codebooks.units')->name('codebooks.units');
    Volt::route('sifarnici/pdv-stope', 'codebooks.vat-rates')->name('codebooks.vat-rates');
    Volt::route('sifarnici/osnovi-oslobodjenja', 'codebooks.vat-exemptions')->name('codebooks.vat-exemptions');
});

require __DIR__.'/auth.php';

<?php

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

    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.form')->name('users.create');
    Volt::route('users/{user}/edit', 'users.form')->name('users.edit');

    Volt::route('partners', 'partners.index')->name('partners.index');
    Volt::route('partners/create', 'partners.form')->name('partners.create');
    Volt::route('partners/import', 'partners.import')->name('partners.import');
    Volt::route('partners/{partner}/edit', 'partners.form')->name('partners.edit');

    Volt::route('sifarnici/valute', 'codebooks.currencies')->name('codebooks.currencies');
    Volt::route('sifarnici/jedinice-mere', 'codebooks.units')->name('codebooks.units');
    Volt::route('sifarnici/pdv-stope', 'codebooks.vat-rates')->name('codebooks.vat-rates');
    Volt::route('sifarnici/osnovi-oslobodjenja', 'codebooks.vat-exemptions')->name('codebooks.vat-exemptions');
});

require __DIR__.'/auth.php';

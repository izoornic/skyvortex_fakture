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
});

require __DIR__.'/auth.php';

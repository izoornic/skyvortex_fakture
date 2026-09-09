<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * There is no self-registration: accounts are opened by an administrator on
 * `users.create`. The tests below are what keeps the starter kit's screen from
 * quietly coming back.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_screen_is_gone(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_nothing_links_to_a_registration_route(): void
    {
        $this->assertFalse(Route::has('register'));
    }

    public function test_the_login_screen_offers_no_way_to_sign_up(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('register')
            ->assertDontSee('Sign up');
    }
}

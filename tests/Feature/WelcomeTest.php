<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_is_offered_the_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Skyvortex Fakture')
            ->assertSee('Prijava')
            ->assertSee('Nalog otvara administrator.')
            ->assertSee(route('login'));
    }

    public function test_a_signed_in_user_is_pointed_at_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/')
            ->assertOk()
            ->assertSee('Kontrolna tabla')
            ->assertSee(route('dashboard'))
            ->assertDontSee('Nalog otvara administrator.');
    }
}

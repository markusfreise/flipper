<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Die SPA schickt einen Referer; nur dann ist die Anfrage für Sanctum "stateful" (Session).
        $this->withHeader('Referer', 'http://localhost/');
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'anna@freise.de', 'password' => 'geheim123']);

        $this->postJson('/api/v1/login', ['email' => 'anna@freise.de', 'password' => 'geheim123'])
            ->assertOk()
            ->assertJsonPath('user.email', 'anna@freise.de');

        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.id', $user->id);

        $this->postJson('/api/v1/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_wrong_password_is_rejected_in_german(): void
    {
        User::factory()->create(['email' => 'anna@freise.de', 'password' => 'geheim123']);

        $this->postJson('/api/v1/login', ['email' => 'anna@freise.de', 'password' => 'falsch'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'E-Mail oder Passwort stimmt nicht.');
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        // Ohne eingefrorene Zeit kippt der Test, wenn die sechs Anfragen über eine Minutengrenze laufen.
        $this->freezeTime();
        User::factory()->create(['email' => 'anna@freise.de']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => 'anna@freise.de', 'password' => 'falsch'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/login', ['email' => 'anna@freise.de', 'password' => 'falsch'])->assertStatus(429);
    }

    public function test_api_requires_login(): void
    {
        $this->getJson('/api/v1/listings')->assertUnauthorized();
        $this->get('/files/listings/x/download.zip')->assertRedirect('/login');
    }

    public function test_foreign_listing_is_forbidden(): void
    {
        $listing = Listing::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->getJson("/api/v1/listings/{$listing->id}")->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/v1/listings/{$listing->id}")->assertForbidden();
        $this->actingAs($other)->getJson('/api/v1/listings')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->get("/files/listings/{$listing->id}/download.zip")->assertForbidden();
    }

    public function test_user_create_command_prints_password(): void
    {
        $this->artisan('user:create', ['name' => 'Ben', 'email' => 'Ben@Freise.de'])
            ->expectsOutputToContain('Passwort:')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'ben@freise.de']);

        $this->artisan('user:password', ['email' => 'ben@freise.de', '--password' => 'neu12345'])->assertSuccessful();
        $this->postJson('/api/v1/login', ['email' => 'ben@freise.de', 'password' => 'neu12345'])->assertOk();
    }
}

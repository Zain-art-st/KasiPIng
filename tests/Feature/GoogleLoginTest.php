<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.allowed_emails' => 'admin@example.com']);
    }

    private function googleReturns(?string $email): void
    {
        $provider = Mockery::mock();

        if ($email === null) {
            $provider->shouldReceive('user')->andThrow(new \RuntimeException('cancelled'));
        } else {
            $user = (new GoogleUser)->map(['id' => 'g-1', 'name' => 'Someone', 'email' => $email]);
            $provider->shouldReceive('user')->andReturn($user);
        }

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_admin_shows_a_login_page_instead_of_jumping_to_google(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->get('/admin/login')->assertOk()
            ->assertSee('Sign in with Google')
            ->assertSee('Only authorised secretariat Google accounts can sign in')
            ->assertSee(route('auth.google.redirect'), false)
            ->assertDontSee('Sign-in did not work');
    }

    public function test_unauthorised_account_returns_to_login_with_a_message(): void
    {
        $this->googleReturns('stranger@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/admin/login');
        $this->get('/admin/login')
            ->assertSee('Sign-in did not work')
            ->assertSee('stranger@gmail.com is not authorised');
        $this->assertGuest();
    }

    public function test_cancelled_sign_in_returns_to_login_with_a_message(): void
    {
        $this->googleReturns(null);

        $this->get('/auth/google/callback')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertSee('cancelled or did not finish');
    }

    public function test_authorised_account_signs_in_and_skips_the_login_page(): void
    {
        $this->googleReturns('admin@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/admin');
        $this->assertAuthenticatedAs(User::where('email', 'admin@example.com')->first());

        $this->get('/admin/login')->assertRedirect('/admin');
    }
}

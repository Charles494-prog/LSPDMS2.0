<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_cannot_be_framed_by_other_sites(): void
    {
        $this->assertHardened($this->get(route('login.form'))->assertOk());
    }

    public function test_signed_in_pages_and_json_carry_the_headers(): void
    {
        $headSecretary = User::query()->create([
            'gmail' => 'headers@example.test',
            'password' => 'valid-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        $this->assertHardened(
            $this->withSession(['login_user' => ['id' => $headSecretary->id]])
                ->get(route('superadmin-dashboard'))
                ->assertOk()
        );
        $this->assertHardened(
            $this->withSession(['login_user' => ['id' => $headSecretary->id]])
                ->getJson(route('session.status'))
                ->assertOk()
        );
    }

    public function test_route_specific_values_are_kept(): void
    {
        $this->get(route('it-recovery.login'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
    }

    private function assertHardened(TestResponse $response): void
    {
        $response
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    }
}

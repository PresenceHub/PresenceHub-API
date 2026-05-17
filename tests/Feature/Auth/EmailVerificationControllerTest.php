<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_verify_email_with_valid_signed_link(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        $url = URL::temporarySignedRoute(
            'v1.auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
            absolute: false,
        );

        $this
            ->getJson($url)
            ->assertOk()
            ->assertJson([
                'message' => 'Email address verified successfully.',
                'isEmailVerified' => true,
            ]);

        $this->assertTrue($user->fresh()?->hasVerifiedEmail());
    }

    public function test_verify_email_rejects_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        $this
            ->getJson("/api/v1/auth/email/verify/{$user->id}/".sha1($user->getEmailForVerification()))
            ->assertForbidden();
    }

    public function test_verify_email_rejects_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        $url = URL::temporarySignedRoute(
            'v1.auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => 'invalid-hash',
            ],
            absolute: false,
        );

        $this
            ->getJson($url)
            ->assertForbidden();

        $this->assertFalse($user->fresh()?->hasVerifiedEmail());
    }

    public function test_verify_email_returns_success_when_already_verified(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $url = URL::temporarySignedRoute(
            'v1.auth.email.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
            absolute: false,
        );

        $this
            ->getJson($url)
            ->assertOk()
            ->assertJson([
                'message' => 'Email address is already verified.',
                'isEmailVerified' => true,
            ]);
    }

    public function test_authenticated_user_can_resend_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        Sanctum::actingAs($user);

        $this
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk()
            ->assertJson([
                'message' => 'Verification link sent.',
            ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_rejects_already_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        Sanctum::actingAs($user);

        $this
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk()
            ->assertJson([
                'message' => 'Email address is already verified.',
                'isEmailVerified' => true,
            ]);

        Notification::assertNothingSent();
    }

    public function test_resend_verification_requires_authentication(): void
    {
        $this
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertUnauthorized();
    }

    public function test_verification_email_link_points_to_frontend(): void
    {
        config(['app.ph_web_url' => 'http://localhost:3000']);

        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $actionUrl = $mail->actionUrl;

            return is_string($actionUrl)
                && str_starts_with($actionUrl, 'http://localhost:3000/verify-email?')
                && str_contains($actionUrl, 'id='.$user->id)
                && str_contains($actionUrl, 'hash=')
                && str_contains($actionUrl, 'expires=')
                && str_contains($actionUrl, 'signature=');
        });
    }

    public function test_unverified_user_cannot_access_protected_routes(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'jane@example.com',
        ]);

        Sanctum::actingAs($user);

        $this
            ->getJson('/api/v1/platforms')
            ->assertForbidden();
    }

    public function test_verified_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        Sanctum::actingAs($user);

        $this
            ->getJson('/api/v1/platforms')
            ->assertOk();
    }
}

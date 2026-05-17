<?php

namespace Tests\Feature\Auth;

use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_forgot_password_requires_valid_email(): void
    {
        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_sends_notification_for_existing_user(): void
    {
        Notification::fake();

        $user = UserFactory::new()->create([
            'email' => 'jane@example.com',
        ]);

        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => 'jane@example.com',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'If that email address is in our system, we have emailed a password reset link.',
            ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_returns_generic_message_for_unknown_email(): void
    {
        Notification::fake();

        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => 'missing@example.com',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'If that email address is in our system, we have emailed a password reset link.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_normalizes_email(): void
    {
        Notification::fake();

        $user = UserFactory::new()->create([
            'email' => 'jane@example.com',
        ]);

        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => '  JANE@EXAMPLE.COM  ',
            ])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }
}

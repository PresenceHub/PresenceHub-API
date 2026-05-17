<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_forgot_password_returns_generic_success_for_existing_email(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => 'jane@example.com',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'We have sent an email with a password reset link.',
            ]);

        Notification::assertSentTo(
            User::query()->where('email', 'jane@example.com')->firstOrFail(),
            ResetPassword::class,
        );
    }

    public function test_forgot_password_returns_same_generic_success_for_unknown_email(): void
    {
        Notification::fake();

        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => 'nobody@example.com',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'We have sent an email with a password reset link.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_normalizes_email(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => '  JANE@EXAMPLE.COM  ',
            ])
            ->assertOk();

        Notification::assertSentTo(
            User::query()->where('email', 'jane@example.com')->firstOrFail(),
            ResetPassword::class,
        );
    }

    public function test_forgot_password_reset_link_points_to_frontend(): void
    {
        config(['app.ph_web_url' => 'http://localhost:3000']);

        Notification::fake();

        $user = User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'jane@example.com',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $actionUrl = $mail->actionUrl;

            return is_string($actionUrl)
                && str_starts_with($actionUrl, 'http://localhost:3000/reset-password?')
                && str_contains($actionUrl, 'token=')
                && str_contains($actionUrl, 'email=jane%40example.com');
        });
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $this
            ->postJson('/api/v1/auth/forgot-password', [
                'email' => 'not-an-email',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_requires_email(): void
    {
        $this
            ->postJson('/api/v1/auth/forgot-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}

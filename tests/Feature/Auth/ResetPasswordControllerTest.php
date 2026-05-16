<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'old-password1234',
        ]);

        $token = Password::broker()->createToken($user);

        $this
            ->postJson('/api/v1/auth/reset-password', [
                'token' => $token,
                'email' => 'jane@example.com',
                'password' => 'new-password1234',
                'password_confirmation' => 'new-password1234',
            ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();

        $this->assertTrue(Hash::check('new-password1234', $user->password));
        $this->assertFalse(Hash::check('old-password1234', $user->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'old-password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/reset-password', [
                'token' => 'invalid-token',
                'email' => 'jane@example.com',
                'password' => 'new-password1234',
                'password_confirmation' => 'new-password1234',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'old-password1234',
        ]);

        $token = Password::broker()->createToken($user);

        $this
            ->postJson('/api/v1/auth/reset-password', [
                'token' => $token,
                'email' => 'jane@example.com',
                'password' => 'new-password1234',
                'password_confirmation' => 'different-password1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_token_email_and_password(): void
    {
        $this
            ->postJson('/api/v1/auth/reset-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function test_reset_password_normalizes_email(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'old-password1234',
        ]);

        $token = Password::broker()->createToken($user);

        $this
            ->postJson('/api/v1/auth/reset-password', [
                'token' => $token,
                'email' => '  JANE@EXAMPLE.COM  ',
                'password' => 'new-password1234',
                'password_confirmation' => 'new-password1234',
            ])
            ->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password1234', $user->password));
    }
}

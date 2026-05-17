<?php

namespace Tests\Feature\Auth;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reset_password_requires_fields(): void
    {
        $this
            ->postJson('/api/v1/auth/reset-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('password1234'),
        ]);

        $this
            ->postJson('/api/v1/auth/reset-password', [
                'token' => 'wrong-token',
                'email' => $user->email,
                'password' => 'new-password9999',
                'password_confirmation' => 'new-password9999',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    public function test_reset_password_updates_password_when_token_valid(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('password1234'),
        ]);

        $plainToken = 'plain-reset-token';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ],
        );

        $this
            ->postJson('/api/v1/auth/reset-password', [
                'token' => $plainToken,
                'email' => $user->email,
                'password' => 'new-password9999',
                'password_confirmation' => 'new-password9999',
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Your password has been reset.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password9999', $user->password));

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'new-password9999',
            ])
            ->assertOk();
    }
}

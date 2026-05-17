<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Events\UserLoggedIn;
use App\Models\Event;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_login_and_receives_token(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $workspace = Workspace::factory()->create([
            'name' => 'Jane Workspace',
            'owner_id' => $user->id,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'uuid',
                    'name',
                    'email',
                    'isEmailVerified',
                    'emailVerifiedAt',
                    'role' => ['uuid', 'slug', 'name', 'description'],
                    'workspaces' => [
                        '*' => [
                            'uuid',
                            'name',
                            'createdAt',
                            'updatedAt',
                        ],
                    ],
                    'createdAt',
                    'updatedAt',
                ],
                'token',
            ]);

        $this->assertTrue(Str::isUuid($response->json('user.uuid')));
        $this->assertSame($user->uuid, $response->json('user.uuid'));
        $this->assertSame('customer', $response->json('user.role.slug'));
        $response->assertJsonCount(1, 'user.workspaces');
        $this->assertSame($workspace->uuid, $response->json('user.workspaces.0.uuid'));
        $this->assertSame('Jane Workspace', $response->json('user.workspaces.0.name'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => $user::class,
            'tokenable_id' => $user->id,
            'name' => 'api',
        ]);
    }

    public function test_login_records_user_logged_in_event(): void
    {
        $user = UserFactory::new()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $workspace = Workspace::factory()->create([
            'owner_id' => $user->id,
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'password1234',
            ])
            ->assertOk();

        $event = Event::query()
            ->where('name', UserLoggedIn::NAME)
            ->where('user_uuid', $user->uuid)
            ->firstOrFail();

        $this->assertSame($workspace->uuid, $event->workspace_uuid);
        $this->assertSame('jane@example.com', $event->properties['email']);
    }

    public function test_failed_login_does_not_record_event(): void
    {
        UserFactory::new()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Event::query()->where('name', UserLoggedIn::NAME)->count());
    }

    public function test_login_normalizes_email(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => '  JANE@EXAMPLE.COM  ',
                'password' => 'password1234',
            ])
            ->assertOk()
            ->assertJsonPath('user.email', 'jane@example.com');
    }

    public function test_login_rejects_wrong_password_with_generic_message(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJson(['message' => 'The provided credentials are incorrect.']);
    }

    public function test_login_rejects_unknown_email_with_same_message(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'password1234',
            ])
            ->assertUnprocessable()
            ->assertJson(['message' => 'The provided credentials are incorrect.']);
    }

    public function test_login_requires_valid_email(): void
    {
        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'not-an-email',
                'password' => 'password1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unverified_user_can_login_and_receives_verification_flags(): void
    {
        UserFactory::new()->unverified()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'password1234',
            ])
            ->assertOk()
            ->assertJsonPath('user.isEmailVerified', false)
            ->assertJsonPath('user.emailVerifiedAt', null);
    }

    public function test_unverified_user_login_sends_verification_notification(): void
    {
        Notification::fake();

        $user = UserFactory::new()->unverified()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'password1234',
            ])
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_login_does_not_send_verification_notification(): void
    {
        Notification::fake();

        $user = UserFactory::new()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'password1234',
            ])
            ->assertOk();

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this
            ->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                    'password',
                ],
            ])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_validation_returns_json_when_body_is_json_without_json_accept_header(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([])
        );

        $response
            ->assertStatus(422)
            ->assertHeaderContains('content-type', 'application/json')
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                    'password',
                ],
            ])
            ->assertJsonValidationErrors(['email', 'password']);
    }
}

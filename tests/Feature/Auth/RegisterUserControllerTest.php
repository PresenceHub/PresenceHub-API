<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Events\UserRegistered;
use App\Enums\Role as RoleSlug;
use App\Mail\RegistrationWelcomeMail;
use App\Models\Event as EventRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VALID_PASSWORD = 'ValidPassw0rd!14';

    public function test_user_can_register_and_receives_token(): void
    {
        $creatorRole = Role::findBySlugOrFail(RoleSlug::CUSTOMER->value);

        $response = $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => self::VALID_PASSWORD,
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'user' => [
                    'uuid',
                    'name',
                    'email',
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

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue(Str::isUuid($response->json('user.uuid')));
        $this->assertSame($user->uuid, $response->json('user.uuid'));
        $this->assertSame('customer', $response->json('user.role.slug'));

        $this->assertSame($creatorRole->uuid, $user->role->uuid);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'uuid' => $user->uuid,
            'role_id' => $creatorRole->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => $user::class,
            'tokenable_id' => $user->id,
            'name' => 'api',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $user->id,
            'name' => 'Jane Doe',
        ]);
    }

    public function test_registration_normalizes_name_and_email(): void
    {
        $this
            ->postJson('/api/v1/auth/register', [
                'name' => '   Jane    Doe   ',
                'email' => '  JANE@EXAMPLE.COM  ',
                'password' => self::VALID_PASSWORD,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_registration_rejects_weak_password(): void
    {
        $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => self::VALID_PASSWORD,
            ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                ],
            ])
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_records_user_registered_event(): void
    {
        $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => self::VALID_PASSWORD,
            ])
            ->assertCreated();

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
        $workspace = $user->ownedWorkspaces()->firstOrFail();

        $event = EventRecord::query()
            ->where('name', UserRegistered::NAME)
            ->where('user_uuid', $user->uuid)
            ->firstOrFail();

        $this->assertSame($workspace->uuid, $event->workspace_uuid);
        $this->assertSame('jane@example.com', $event->properties['email']);
        $this->assertSame('Jane Doe', $event->properties['name']);
        $this->assertSame('customer', $event->properties['role_slug']);
    }

    public function test_successful_registration_queues_welcome_email(): void
    {
        Mail::fake();

        $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => self::VALID_PASSWORD,
            ])
            ->assertCreated();

        Mail::assertQueued(RegistrationWelcomeMail::class, function (RegistrationWelcomeMail $mail): bool {
            return $mail->hasTo('jane@example.com')
                && $mail->user->email === 'jane@example.com'
                && $mail->user->name === 'Jane Doe'
                && $mail->senderAddress === config('mail.from.address')
                && $mail->senderName === config('mail.from.name');
        });
    }

    public function test_failed_registration_does_not_queue_welcome_email(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => self::VALID_PASSWORD,
            ])
            ->assertUnprocessable();

        Mail::assertNothingQueued();
    }

    public function test_failed_registration_does_not_record_event(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => self::VALID_PASSWORD,
            ])
            ->assertUnprocessable();

        $this->assertSame(0, EventRecord::query()->where('name', UserRegistered::NAME)->count());
    }

    public function test_registration_validation_returns_json_when_body_is_json_without_json_accept_header(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $response = $this->call(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => self::VALID_PASSWORD,
            ])
        );

        $response
            ->assertStatus(422)
            ->assertHeaderContains('content-type', 'application/json')
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'email',
                ],
            ])
            ->assertJsonValidationErrors(['email']);
    }
}

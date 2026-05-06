<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Events\UserLoggedOut;
use App\Models\Event as EventRecord;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_logout_and_token_is_invalidated(): void
    {
        $user = UserFactory::new()->create();

        $plainToken = $user->createToken('api')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this
            ->withToken($plainToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // PHPUnit reuses the app; clear cached sanctum guard so the next request re-resolves the Bearer token.
        Auth::forgetGuards();

        $this
            ->withToken($plainToken)
            ->getJson('/api/v1/platforms')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_logout_records_user_logged_out_event(): void
    {
        $user = UserFactory::new()->create();
        $plainToken = $user->createToken('api')->plainTextToken;

        $this
            ->withToken($plainToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $event = EventRecord::query()
            ->where('name', UserLoggedOut::NAME)
            ->where('user_uuid', $user->uuid)
            ->firstOrFail();

        $this->assertNull($event->workspace_uuid);
        $this->assertSame($user->email, $event->properties['email']);
    }
}

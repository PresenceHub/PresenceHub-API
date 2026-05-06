<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Events\ChannelConnected;
use App\Domain\Content\Events\ChannelDisconnected;
use App\Domain\Content\Models\Channel;
use App\Models\Event;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_list_channels(): void
    {
        [$workspace] = $this->workspaceChannelAndOwner();

        $this
            ->getJson('/api/v1/channels', $this->workspaceHeader($workspace->uuid))
            ->assertUnauthorized();
    }

    public function test_channels_require_workspace_header(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/channels')->assertStatus(400);
    }

    public function test_non_member_cannot_list_channels(): void
    {
        [$workspace] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs(User::factory()->create());

        $this
            ->getJson('/api/v1/channels', $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();
    }

    public function test_workspace_member_can_list_channels(): void
    {
        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->getJson('/api/v1/channels')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $channel->uuid)
            ->assertJsonPath('data.0.handle', $channel->handle)
            ->assertJsonPath('data.0.platform.slug', 'instagram')
            ->assertJsonMissingPath('data.0.access_token')
            ->assertJsonMissingPath('data.0.refresh_token');
    }

    public function test_non_member_cannot_create_channel(): void
    {
        [$workspace] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs(User::factory()->create());

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/channels', $this->validStorePayload())
            ->assertForbidden();
    }

    public function test_owner_can_create_channel(): void
    {
        [$workspace, $_existing, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $payload = $this->validStorePayload([
            'platform_account_id' => 'acct-new-001',
            'handle' => 'new_handle',
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/channels', $payload)
            ->assertCreated()
            ->assertJsonPath('data.handle', 'new_handle')
            ->assertJsonPath('data.platform.slug', 'instagram')
            ->assertJsonMissingPath('data.access_token');

        $this->assertDatabaseHas('channels', [
            'workspace_id' => $workspace->id,
            'handle' => 'new_handle',
            'platform_account_id' => 'acct-new-001',
        ]);

        $this->assertDatabaseHas('events', [
            'name' => ChannelConnected::NAME,
            'user_uuid' => $owner->uuid,
            'workspace_uuid' => $workspace->uuid,
        ]);
    }

    public function test_store_rejects_unknown_platform_slug(): void
    {
        [$workspace, $_c, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $payload = $this->validStorePayload(['platform_slug' => 'unknown-platform']);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/channels', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform_slug']);
    }

    public function test_store_rejects_duplicate_platform_account_in_workspace(): void
    {
        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $payload = $this->validStorePayload([
            'platform_slug' => 'instagram',
            'platform_account_id' => $channel->platform_account_id,
            'handle' => 'other',
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/channels', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform_account_id']);
    }

    public function test_owner_can_disconnect_channel(): void
    {
        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->deleteJson('/api/v1/channels/'.$channel->uuid)
            ->assertNoContent();

        $this->assertSoftDeleted('channels', ['id' => $channel->id]);

        $event = Event::query()
            ->where('name', ChannelDisconnected::NAME)
            ->where('user_uuid', $owner->uuid)
            ->where('workspace_uuid', $workspace->uuid)
            ->firstOrFail();

        $this->assertSame($channel->uuid, $event->properties['channel_uuid']);
        $this->assertSame('instagram', $event->properties['platform_slug']);
    }

    public function test_non_member_cannot_delete_channel(): void
    {
        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs(User::factory()->create());

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->deleteJson('/api/v1/channels/'.$channel->uuid)
            ->assertForbidden();
    }

    public function test_delete_channel_from_other_workspace_returns_not_found(): void
    {
        [$workspaceA, $channelA, $ownerA] = $this->workspaceChannelAndOwner();
        [$workspaceB, $_channelB, $ownerB] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($ownerB);

        $this
            ->withHeaders($this->workspaceHeader($workspaceB->uuid))
            ->deleteJson('/api/v1/channels/'.$channelA->uuid)
            ->assertNotFound();
    }

    public function test_guest_cannot_list_platforms(): void
    {
        $this->getJson('/api/v1/platforms')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_platforms(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this
            ->getJson('/api/v1/platforms')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'instagram'])
            ->assertJsonFragment(['slug' => 'facebook']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'platform_slug' => 'instagram',
            'handle' => 'presencehub_test',
            'platform_account_id' => '123456789',
            'access_token' => 'test-access-token-secret',
            'refresh_token' => null,
            'token_expires_at' => null,
        ], $overrides);
    }

    /**
     * @return array{Workspace, Channel, User}
     */
    private function workspaceChannelAndOwner(): array
    {
        $workspace = Workspace::factory()->create();
        $platform = Platform::query()->where('slug', 'instagram')->firstOrFail();

        $channel = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $platform->id,
            'created_by' => $workspace->owner_id,
        ]);
        $owner = User::query()->findOrFail($workspace->owner_id);

        return [$workspace, $channel, $owner];
    }
}

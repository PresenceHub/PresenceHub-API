<?php

namespace Tests\Feature\Timeline;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Enums\PostTargetStatus;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\PlatformOAuthConnection;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostMedia;
use App\Domain\Content\Models\PostTarget;
use App\Domain\Timeline\Models\Timeline;
use App\Enums\WorkspaceMemberRole;
use App\Models\Event;
use App\Models\Platform;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_actor_is_stored_as_causer(): void
    {
        $actor = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->actingAs($actor);

        $workspace->update(['name' => 'Updated Workspace']);

        $activity = Timeline::query()
            ->forSubject($workspace)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($actor->getMorphClass(), $activity->causer_type);
        $this->assertSame($actor->id, $activity->causer_id);
        $this->assertSame('Updated Workspace', $activity->changes['attributes']['name']);
    }

    public function test_masked_and_excluded_fields_are_applied_for_user_model(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor);

        $user = User::factory()->create([
            'password' => 'plain-text-secret',
        ]);

        $activity = Timeline::query()
            ->forSubject($user)
            ->forEvent('created')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('*****', $activity->changes['attributes']['password']);
        $this->assertArrayNotHasKey('remember_token', $activity->changes['attributes']);
        $this->assertSame($actor->id, $activity->causer_id);
    }

    public function test_system_actor_label_is_stored_when_no_authenticated_user_exists(): void
    {
        $workspace = Workspace::factory()->create();

        $workspace->update(['name' => 'System Updated Workspace']);

        $activity = Timeline::query()
            ->forSubject($workspace)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($activity->causer_type);
        $this->assertNull($activity->causer_id);
        $this->assertSame('system', $activity->getExtraProperty('meta.actor_label'));
    }

    public function test_models_without_has_timeline_do_not_write_timeline_entries(): void
    {
        $event = Event::query()->create([
            'name' => 'test.debug',
            'user_uuid' => null,
            'workspace_uuid' => null,
            'properties' => ['sample' => true],
            'occurred_at' => now(),
        ]);

        $this->assertSame(0, Timeline::query()->forSubject($event)->count());
    }

    public function test_workspace_member_update_is_logged(): void
    {
        $workspace = Workspace::factory()->create();
        $memberUser = User::factory()->create();
        $replacement = User::factory()->create();

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $memberUser->id,
            'role' => WorkspaceMemberRole::Owner,
        ]);

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $memberUser->id)
            ->firstOrFail();

        $this->actingAs($workspace->owner);
        $member->update(['user_id' => $replacement->id]);

        $activity = Timeline::query()
            ->forSubject($member)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($replacement->id, $activity->changes['attributes']['user_id']);
        $this->assertSame($memberUser->id, $activity->changes['old']['user_id']);
    }

    public function test_channel_masks_tokens_and_logs_soft_delete(): void
    {
        $actor = User::factory()->create();
        $channel = Channel::factory()->create([
            'access_token' => 'super-secret-access',
            'refresh_token' => 'super-secret-refresh',
        ]);

        $this->actingAs($actor);
        $channel->update([
            'handle' => 'newhandle',
            'access_token' => 'rotated-access',
            'refresh_token' => 'rotated-refresh',
        ]);

        $updateActivity = Timeline::query()
            ->forSubject($channel)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('newhandle', $updateActivity->changes['attributes']['handle']);
        $this->assertSame('*****', $updateActivity->changes['attributes']['access_token']);
        $this->assertSame('*****', $updateActivity->changes['attributes']['refresh_token']);

        $channel->delete();

        $deleteActivity = Timeline::query()
            ->forSubject($channel)
            ->forEvent('deleted')
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayHasKey('old', $deleteActivity->changes->toArray());
    }

    public function test_platform_oauth_connection_masks_access_token(): void
    {
        $actor = User::factory()->create();
        $connection = PlatformOAuthConnection::factory()->create([
            'access_token' => 'oauth-plain-secret',
        ]);

        $this->actingAs($actor);
        $connection->update([
            'provider_user_id' => '999999999',
            'access_token' => 'rotated-oauth-token',
        ]);

        $activity = Timeline::query()
            ->forSubject($connection)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('999999999', $activity->changes['attributes']['provider_user_id']);
        $this->assertSame('*****', $activity->changes['attributes']['access_token']);
    }

    public function test_post_update_excludes_content_from_timeline_entry(): void
    {
        $actor = User::factory()->create();
        $post = Post::factory()->create([
            'content' => 'Large body content that should not appear in timeline payload.',
        ]);

        $this->actingAs($actor);
        $post->update(['status' => PostStatus::Published]);

        $activity = Timeline::query()
            ->forSubject($post)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayHasKey('status', $activity->changes['attributes']);
        $this->assertArrayNotHasKey('content', $activity->changes['attributes']);
    }

    public function test_post_target_update_does_not_include_platform_options(): void
    {
        $actor = User::factory()->create();
        $target = PostTarget::factory()->create([
            'platform_options' => ['should_not_log' => true],
        ]);

        $this->actingAs($actor);
        $target->update(['status' => PostTargetStatus::Completed]);

        $activity = Timeline::query()
            ->forSubject($target)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayHasKey('status', $activity->changes['attributes']);
        $this->assertArrayNotHasKey('platform_options', $activity->changes['attributes']);
    }

    public function test_post_media_update_and_delete_are_logged(): void
    {
        Storage::fake('public');

        $actor = User::factory()->create();
        $media = PostMedia::factory()->create([
            'disk' => 'public',
            'path' => '',
        ]);

        $this->actingAs($actor);
        $media->update(['order' => 5]);

        $updateActivity = Timeline::query()
            ->forSubject($media)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(5, $updateActivity->changes['attributes']['order']);

        $media->delete();

        $this->assertSame(1, Timeline::query()->forSubject($media)->forEvent('deleted')->count());
    }

    public function test_role_does_not_log_created_but_logs_updated(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $role = Role::factory()->create([
            'name' => 'Auditor Role',
            'description' => 'Initial',
        ]);

        $this->assertSame(0, Timeline::query()->forSubject($role)->count());

        $role->update(['description' => 'Changed description']);

        $activity = Timeline::query()
            ->forSubject($role)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Changed description', $activity->changes['attributes']['description']);
    }

    public function test_platform_does_not_log_created_but_logs_updated(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $platform = Platform::factory()->create([
            'name' => 'Original Platform Name',
        ]);

        $this->assertSame(0, Timeline::query()->forSubject($platform)->count());

        $platform->update(['name' => 'Renamed Platform']);

        $activity = Timeline::query()
            ->forSubject($platform)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Renamed Platform', $activity->changes['attributes']['name']);
    }
}

<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Enums\PostTargetStatus;
use App\Domain\Content\Events\PostFailed;
use App\Domain\Content\Events\PostPartiallyPublished;
use App\Domain\Content\Events\PostPublished;
use App\Domain\Content\Events\PostTargetPublishFailed;
use App\Domain\Content\Events\PostTargetPublishSucceeded;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostMedia;
use App\Domain\Content\Models\PostTarget;
use App\Jobs\PublishPostTargetJob;
use App\Models\Event;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublishPostTargetJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_store_dispatches_publish_job_per_target(): void
    {
        Queue::fake();

        [$workspace, $owner] = $this->workspaceAndOwner();
        $instagramPlatform = Platform::query()->where('slug', 'instagram')->firstOrFail();
        $facebookPlatform = Platform::query()->where('slug', 'facebook')->firstOrFail();
        $instagramChannel = $this->makeChannel($workspace, $owner, $instagramPlatform);
        $facebookChannel = $this->makeChannel($workspace, $owner, $facebookPlatform);

        Sanctum::actingAs($owner);

        $scheduledAt = now()->addMinute()->startOfSecond()->toISOString();

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Publish now',
                'targets' => [
                    [
                        'channel_uuid' => $instagramChannel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                    [
                        'channel_uuid' => $facebookChannel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
            ])
            ->assertCreated();

        Queue::assertPushed(PublishPostTargetJob::class, 2);
    }

    public function test_job_records_successful_attempt_and_marks_target_completed(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $platform = Platform::query()->where('slug', 'instagram')->firstOrFail();
        $channel = $this->makeChannel($workspace, $owner, $platform);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'status' => PostStatus::Scheduled,
        ]);

        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
            'status' => PostTargetStatus::Pending,
        ]);

        PostMedia::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'post_id' => $post->id,
            'mime_type' => 'image/jpeg',
        ]);

        Http::fake(function (Request $request) use ($channel) {
            if (str_contains($request->url(), '/'.$channel->platform_account_id.'/media_publish')) {
                return Http::response(['id' => 'ig-media-123'], 200);
            }

            if (str_contains($request->url(), '/'.$channel->platform_account_id.'/media')) {
                return Http::response(['id' => 'ig-container-1'], 200);
            }

            return Http::response([], 500);
        });

        PublishPostTargetJob::dispatchSync($target->id);

        $target->refresh();
        $post->refresh();

        $this->assertSame(PostTargetStatus::Completed, $target->status);
        $this->assertNotNull($target->published_at);
        $this->assertSame(1, $target->attempt_count);
        $this->assertNotNull($target->external_post_id);
        $this->assertSame(PostStatus::Published, $post->status);

        $this->assertDatabaseHas('post_target_publish_attempts', [
            'post_target_id' => $target->id,
            'attempt_number' => 1,
            'status' => 'completed',
        ]);

        $event = Event::query()
            ->where('name', PostTargetPublishSucceeded::NAME)
            ->where('workspace_uuid', $workspace->uuid)
            ->firstOrFail();

        $this->assertSame($post->uuid, $event->properties['post_uuid']);
        $this->assertSame($target->uuid, $event->properties['target_uuid']);
        $this->assertSame($channel->uuid, $event->properties['channel_uuid']);

        $this->assertDatabaseHas('events', [
            'name' => PostPublished::NAME,
            'workspace_uuid' => $workspace->uuid,
        ]);
    }

    public function test_job_records_failure_and_sets_post_as_failed_when_all_targets_fail(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $platform = Platform::query()->where('slug', 'facebook')->firstOrFail();
        $channel = $this->makeChannel($workspace, $owner, $platform);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'status' => PostStatus::Scheduled,
        ]);

        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
            'status' => PostTargetStatus::Pending,
        ]);

        Http::fake(function (Request $request) use ($channel) {
            if (str_contains($request->url(), '/'.$channel->platform_account_id.'/feed')) {
                return Http::response([
                    'error' => [
                        'message' => 'Invalid parameter',
                    ],
                ], 400);
            }

            return Http::response([], 500);
        });

        PublishPostTargetJob::dispatchSync($target->id);

        $target->refresh();
        $post->refresh();

        $this->assertSame(PostTargetStatus::Failed, $target->status);
        $this->assertNull($target->published_at);
        $this->assertSame(1, $target->attempt_count);
        $this->assertSame(PostStatus::Failed, $post->status);

        $this->assertDatabaseHas('post_target_publish_attempts', [
            'post_target_id' => $target->id,
            'attempt_number' => 1,
            'status' => 'failed',
            'error_code' => 'FACEBOOK_HTTP_400',
        ]);

        $event = Event::query()
            ->where('name', PostTargetPublishFailed::NAME)
            ->where('workspace_uuid', $workspace->uuid)
            ->firstOrFail();

        $this->assertSame($post->uuid, $event->properties['post_uuid']);
        $this->assertSame($target->uuid, $event->properties['target_uuid']);
        $this->assertSame('FACEBOOK_HTTP_400', $event->properties['error_code']);
        $this->assertFalse($event->properties['recoverable']);

        $this->assertDatabaseHas('events', [
            'name' => PostFailed::NAME,
            'workspace_uuid' => $workspace->uuid,
        ]);
    }

    public function test_post_becomes_partially_published_when_targets_have_mixed_outcomes(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $platform = Platform::query()->where('slug', 'facebook')->firstOrFail();
        $channelA = $this->makeChannel($workspace, $owner, $platform);
        $channelB = $this->makeChannel($workspace, $owner, $platform);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'status' => PostStatus::Scheduled,
        ]);

        $successfulTarget = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channelA->id,
            'status' => PostTargetStatus::Pending,
        ]);

        $failedTarget = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channelB->id,
            'status' => PostTargetStatus::Pending,
        ]);

        Http::fake(function (Request $request) use ($channelA, $channelB) {
            if (str_contains($request->url(), '/'.$channelA->platform_account_id.'/feed')) {
                return Http::response([
                    'id' => 'page-success-post-1',
                ], 200);
            }

            if (str_contains($request->url(), '/'.$channelB->platform_account_id.'/feed')) {
                return Http::response([
                    'error' => [
                        'message' => 'Invalid parameter',
                    ],
                ], 400);
            }

            return Http::response([], 500);
        });

        PublishPostTargetJob::dispatchSync($successfulTarget->id);
        PublishPostTargetJob::dispatchSync($failedTarget->id);

        $post->refresh();
        $successfulTarget->refresh();
        $failedTarget->refresh();

        $this->assertSame(PostTargetStatus::Completed, $successfulTarget->status);
        $this->assertSame(PostTargetStatus::Failed, $failedTarget->status);
        $this->assertSame(PostStatus::PartiallyPublished, $post->status);

        $this->assertDatabaseHas('post_target_publish_attempts', [
            'post_target_id' => $successfulTarget->id,
            'attempt_number' => 1,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('post_target_publish_attempts', [
            'post_target_id' => $failedTarget->id,
            'attempt_number' => 1,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('events', [
            'name' => PostPartiallyPublished::NAME,
            'workspace_uuid' => $workspace->uuid,
        ]);
    }

    public function test_job_uses_facebook_graph_api_and_records_external_post_id(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $platform = Platform::query()->where('slug', 'facebook')->firstOrFail();
        $channel = $this->makeChannel($workspace, $owner, $platform);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'status' => PostStatus::Scheduled,
        ]);

        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
            'status' => PostTargetStatus::Pending,
        ]);

        Http::fake(function (Request $request) use ($channel) {
            if (str_contains($request->url(), '/'.$channel->platform_account_id.'/feed')) {
                return Http::response([
                    'id' => 'page-1_post-123',
                ], 200);
            }

            return Http::response([], 500);
        });

        PublishPostTargetJob::dispatchSync($target->id);

        $target->refresh();

        $this->assertSame(PostTargetStatus::Completed, $target->status);
        $this->assertSame('page-1_post-123', $target->external_post_id);

        $this->assertDatabaseHas('post_target_publish_attempts', [
            'post_target_id' => $target->id,
            'attempt_number' => 1,
            'status' => 'completed',
        ]);
    }

    public function test_job_uses_instagram_graph_api_and_records_external_post_id(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $platform = Platform::query()->where('slug', 'instagram')->firstOrFail();
        $channel = $this->makeChannel($workspace, $owner, $platform);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'status' => PostStatus::Scheduled,
        ]);

        PostMedia::factory()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'post_id' => $post->id,
            'mime_type' => 'image/jpeg',
        ]);

        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
            'status' => PostTargetStatus::Pending,
        ]);

        Http::fake(function (Request $request) use ($channel) {
            if (str_contains($request->url(), '/'.$channel->platform_account_id.'/media_publish')) {
                return Http::response([
                    'id' => 'ig-media-777',
                ], 200);
            }

            if (str_contains($request->url(), '/'.$channel->platform_account_id.'/media')) {
                return Http::response([
                    'id' => 'ig-container-777',
                ], 200);
            }

            return Http::response([], 500);
        });

        PublishPostTargetJob::dispatchSync($target->id);

        $target->refresh();

        $this->assertSame(PostTargetStatus::Completed, $target->status);
        $this->assertSame('ig-media-777', $target->external_post_id);

        $this->assertDatabaseHas('post_target_publish_attempts', [
            'post_target_id' => $target->id,
            'attempt_number' => 1,
            'status' => 'completed',
        ]);
    }

    /**
     * @return array{Workspace, User}
     */
    private function workspaceAndOwner(): array
    {
        $workspace = Workspace::factory()->create();
        $owner = User::query()->findOrFail($workspace->owner_id);

        return [$workspace, $owner];
    }

    private function makeChannel(Workspace $workspace, User $owner, Platform $platform): Channel
    {
        return Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $platform->id,
            'created_by' => $owner->id,
        ]);
    }
}

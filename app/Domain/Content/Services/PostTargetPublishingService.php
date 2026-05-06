<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Enums\PostTargetPublishAttemptStatus;
use App\Domain\Content\Enums\PostTargetStatus;
use App\Domain\Content\Events\PostFailed;
use App\Domain\Content\Events\PostPartiallyPublished;
use App\Domain\Content\Events\PostPublished;
use App\Domain\Content\Events\PostTargetPublishFailed;
use App\Domain\Content\Events\PostTargetPublishSucceeded;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
use App\Domain\Content\Models\PostTargetPublishAttempt;
use App\Services\SocialPlatforms\Data\PlatformPublishResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class PostTargetPublishingService
{
    public function beginAttempt(PostTarget $target, ?string $jobUuid): PostTargetPublishAttempt
    {
        return DB::transaction(function () use ($target, $jobUuid): PostTargetPublishAttempt {
            /** @var PostTarget $lockedTarget */
            $lockedTarget = PostTarget::query()
                ->lockForUpdate()
                ->findOrFail($target->id);

            $attemptNumber = $lockedTarget->attempt_count + 1;
            $now = CarbonImmutable::now();

            $lockedTarget->update([
                'status' => PostTargetStatus::Processing,
                'attempt_count' => $attemptNumber,
                'last_attempt_at' => $now,
            ]);

            return PostTargetPublishAttempt::query()->create([
                'post_target_id' => $lockedTarget->id,
                'attempt_number' => $attemptNumber,
                'status' => PostTargetPublishAttemptStatus::Processing,
                'started_at' => $now,
                'job_uuid' => $jobUuid,
            ]);
        });
    }

    public function completeAttempt(PostTarget $target, PostTargetPublishAttempt $attempt, PlatformPublishResponse $result): void
    {
        DB::transaction(function () use ($target, $attempt, $result): void {
            $now = CarbonImmutable::now();

            /** @var PostTarget $lockedTarget */
            $lockedTarget = PostTarget::query()
                ->with('post.targets')
                ->lockForUpdate()
                ->findOrFail($target->id);

            /** @var PostTargetPublishAttempt $lockedAttempt */
            $lockedAttempt = PostTargetPublishAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            $lockedAttempt->update([
                'status' => $result->successful
                    ? PostTargetPublishAttemptStatus::Completed
                    : PostTargetPublishAttemptStatus::Failed,
                'finished_at' => $now,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'provider_response' => $result->providerResponse,
            ]);

            $lockedTarget->update([
                'status' => $result->successful ? PostTargetStatus::Completed : PostTargetStatus::Failed,
                'published_at' => $result->successful ? $now : null,
                'external_post_id' => $result->externalPostId,
            ]);

            $postStatus = $this->syncPostStatus($lockedTarget->post);

            $lockedTarget->loadMissing(['post.workspace', 'post.creator', 'channel.platform']);

            if ($result->successful) {
                Event::dispatch(new PostTargetPublishSucceeded($lockedTarget->post, $lockedTarget, $lockedAttempt));
            } else {
                Event::dispatch(new PostTargetPublishFailed($lockedTarget->post, $lockedTarget, $lockedAttempt, $result->recoverable));
            }

            $postStatus && Event::dispatch(match ($postStatus) {
                PostStatus::Published => new PostPublished($lockedTarget->post),
                PostStatus::PartiallyPublished => new PostPartiallyPublished($lockedTarget->post),
                PostStatus::Failed => new PostFailed($lockedTarget->post),
            });
        });
    }

    public function syncPostStatus(Post $post): ?PostStatus
    {
        $statuses = $post->targets()
            ->pluck('status')
            ->map(function (mixed $status): ?string {
                if ($status instanceof PostTargetStatus) {
                    return $status->value;
                }

                if (is_string($status)) {
                    return $status;
                }

                return null;
            })
            ->filter(fn (?string $status): bool => $status !== null)
            ->all();

        if ($statuses === []) {
            return null;
        }

        $hasCompleted = in_array(PostTargetStatus::Completed->value, $statuses, true);
        $hasFailed = in_array(PostTargetStatus::Failed->value, $statuses, true);
        $hasPendingOrProcessing = in_array(PostTargetStatus::Pending->value, $statuses, true)
            || in_array(PostTargetStatus::Processing->value, $statuses, true);

        if ($hasPendingOrProcessing) {
            return null;
        }

        if ($hasCompleted && ! $hasFailed) {
            return $this->updatePostStatus($post, PostStatus::Published);
        }

        if (! $hasCompleted && $hasFailed) {
            return $this->updatePostStatus($post, PostStatus::Failed);
        }

        if ($hasCompleted && $hasFailed) {
            return $this->updatePostStatus($post, PostStatus::PartiallyPublished);
        }

        return null;
    }

    private function updatePostStatus(Post $post, PostStatus $nextStatus): ?PostStatus
    {
        if ($post->status === $nextStatus) {
            return null;
        }

        $post->update(['status' => $nextStatus]);

        return $nextStatus;
    }
}

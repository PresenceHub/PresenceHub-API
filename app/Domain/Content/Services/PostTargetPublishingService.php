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
use App\Services\SocialPlatforms\Contracts\SocialPlatformPublisher;
use App\Services\SocialPlatforms\Data\PlatformPublishResponse;
use App\Services\SocialPlatforms\Exceptions\RecoverablePublishException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use Throwable;

class PostTargetPublishingService
{
    public function publishTarget(PostTarget $target, SocialPlatformPublisher $publisher, ?string $jobUuid): void
    {
        $attempt = $this->beginAttempt($target, $jobUuid);

        if ($attempt === null) {
            return;
        }

        try {
            $result = $publisher->publish($target->post, $target);
        } catch (Throwable $exception) {
            $this->failAttemptFromException($target, $attempt, $exception);

            throw $exception;
        }

        $this->completeAttempt($target, $attempt, $result);

        if (! $result->successful && $result->recoverable) {
            throw new RecoverablePublishException($result->errorMessage ?? 'Recoverable publish failure.');
        }
    }

    public function beginAttempt(PostTarget $target, ?string $jobUuid): ?PostTargetPublishAttempt
    {
        return DB::transaction(function () use ($target, $jobUuid): ?PostTargetPublishAttempt {
            /** @var PostTarget $lockedTarget */
            $lockedTarget = PostTarget::query()
                ->lockForUpdate()
                ->findOrFail($target->id);

            if (in_array($lockedTarget->status, [PostTargetStatus::Processing, PostTargetStatus::Completed], true)) {
                return null;
            }

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
                ->with('post')
                ->lockForUpdate()
                ->findOrFail($target->id);

            /** @var PostTargetPublishAttempt $lockedAttempt */
            $lockedAttempt = PostTargetPublishAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            if ($lockedAttempt->post_target_id !== $lockedTarget->id) {
                throw new LogicException('Publish attempt does not belong to the provided post target.');
            }

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

    public function failAttemptFromException(PostTarget $target, PostTargetPublishAttempt $attempt, Throwable $exception): void
    {
        DB::transaction(function () use ($target, $attempt, $exception): void {
            $now = CarbonImmutable::now();

            /** @var PostTarget $lockedTarget */
            $lockedTarget = PostTarget::query()
                ->with('post')
                ->lockForUpdate()
                ->findOrFail($target->id);

            /** @var PostTargetPublishAttempt $lockedAttempt */
            $lockedAttempt = PostTargetPublishAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            if ($lockedAttempt->post_target_id !== $lockedTarget->id) {
                throw new LogicException('Publish attempt does not belong to the provided post target.');
            }

            $lockedAttempt->update([
                'status' => PostTargetPublishAttemptStatus::Failed,
                'finished_at' => $now,
                'error_code' => 'UNEXPECTED_PUBLISH_EXCEPTION',
                'error_message' => $exception->getMessage(),
                'provider_response' => null,
            ]);

            $lockedTarget->update([
                'status' => PostTargetStatus::Failed,
                'published_at' => null,
                'external_post_id' => null,
            ]);

            $this->syncPostStatus($lockedTarget->post);
        });
    }

    public function syncPostStatus(Post $post): ?PostStatus
    {
        /** @var Collection<int, string> $statuses */
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
            ->values();

        $resolvedStatus = $this->resolvePostStatus($statuses);

        if ($resolvedStatus === null) {
            return null;
        }

        $post->update(['status' => $resolvedStatus]);

        return $resolvedStatus;
    }

    private function resolvePostStatus(Collection $statuses): ?PostStatus
    {
        if ($statuses->isEmpty()) {
            return null;
        }

        $normalizedStatuses = $statuses->all();

        $allTerminal = ! empty($normalizedStatuses)
            && collect($normalizedStatuses)->every(fn (string $status): bool => in_array(
                $status,
                [PostTargetStatus::Completed->value, PostTargetStatus::Failed->value],
                true
            ));

        if (! $allTerminal) {
            return null;
        }

        $hasCompleted = in_array(PostTargetStatus::Completed->value, $normalizedStatuses, true);
        $hasFailed = in_array(PostTargetStatus::Failed->value, $normalizedStatuses, true);

        if ($hasCompleted && ! $hasFailed) {
            return PostStatus::Published;
        }

        if (! $hasCompleted && $hasFailed) {
            return PostStatus::Failed;
        }

        return PostStatus::PartiallyPublished;
    }
}

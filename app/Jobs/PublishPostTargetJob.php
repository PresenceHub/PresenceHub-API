<?php

namespace App\Jobs;

use App\Domain\Content\Enums\PostTargetStatus;
use App\Domain\Content\Models\PostTarget;
use App\Domain\Content\Services\PostTargetPublishingService;
use App\Services\SocialPlatforms\PlatformPublishManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class PublishPostTargetJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function __construct(public int $postTargetId) {}

    public function handle(
        PlatformPublishManager $publishManager,
        PostTargetPublishingService $publishingService,
    ): void {
        $target = PostTarget::query()
            ->with(['post', 'channel.platform'])
            ->find($this->postTargetId);

        if ($target === null || $target->status === PostTargetStatus::Completed) {
            return;
        }

        $platformSlug = $target->channel->platform->slug;
        $publisher = $publishManager->publisher($platformSlug);
        $publishingService->publishTarget($target, $publisher, $this->resolveJobUuid());
    }

    public function failed(?Throwable $exception): void
    {
        // Attempts and failure state are persisted in the publishing service.
    }

    private function resolveJobUuid(): ?string
    {
        return is_object($this->job) && method_exists($this->job, 'uuid')
            ? $this->job->uuid()
            : null;
    }
}

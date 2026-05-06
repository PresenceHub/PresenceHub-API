<?php

namespace App\Domain\Content\Events;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
use App\Domain\Content\Models\PostTargetPublishAttempt;
use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class PostTargetPublishFailed extends DomainEvent implements ShouldBeRecorded, ShouldDispatchAfterCommit
{
    public const string NAME = 'post_target.publish.failed';

    public function __construct(
        public readonly Post $post,
        public readonly PostTarget $target,
        public readonly PostTargetPublishAttempt $attempt,
        public readonly bool $recoverable,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getUserUuid(): ?string
    {
        return $this->post->creator?->uuid;
    }

    public function getWorkspaceUuid(): ?string
    {
        return $this->post->workspace?->uuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return [
            'post_uuid' => $this->post->uuid,
            'target_uuid' => $this->target->uuid,
            'channel_uuid' => $this->target->channel?->uuid,
            'platform_slug' => $this->target->channel?->platform?->slug,
            'attempt_number' => $this->attempt->attempt_number,
            'error_code' => $this->attempt->error_code,
            'error_message' => $this->attempt->error_message,
            'recoverable' => $this->recoverable,
        ];
    }
}

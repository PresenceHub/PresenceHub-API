<?php

namespace App\Domain\Content\Events;

use App\Domain\Content\Models\Post;
use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class PostPartiallyPublished extends DomainEvent implements ShouldBeRecorded, ShouldDispatchAfterCommit
{
    public const string NAME = 'post.partially_published';

    public function __construct(public readonly Post $post)
    {
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
            'status' => $this->post->status->value,
        ];
    }
}

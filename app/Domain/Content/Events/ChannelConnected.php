<?php

namespace App\Domain\Content\Events;

use App\Domain\Content\Models\Channel;
use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use App\Models\User;
use App\Models\Workspace;

class ChannelConnected extends DomainEvent implements ShouldBeRecorded
{
    public const string NAME = 'channel.connected';

    public function __construct(
        public readonly Channel $channel,
        public readonly Workspace $workspace,
        public readonly User $actor,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getUserUuid(): ?string
    {
        return $this->actor->uuid;
    }

    public function getWorkspaceUuid(): ?string
    {
        return $this->workspace->uuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return [
            'channel_uuid' => $this->channel->uuid,
            'platform_slug' => $this->channel->platform?->slug,
            'platform_account_id' => $this->channel->platform_account_id,
            'handle' => $this->channel->handle,
        ];
    }
}

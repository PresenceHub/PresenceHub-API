<?php

namespace App\Domain\Auth\Events;

use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use App\Models\User;
use App\Models\Workspace;

class UserRegistered extends DomainEvent implements ShouldBeRecorded
{
    public const string NAME = 'auth.user_registered';

    public function __construct(
        public readonly User $user,
        public readonly Workspace $workspace,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getUserUuid(): ?string
    {
        return $this->user->uuid;
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
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role_slug' => $this->user->role?->slug,
        ];
    }
}

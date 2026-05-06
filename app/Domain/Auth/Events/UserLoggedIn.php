<?php

namespace App\Domain\Auth\Events;

use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use App\Models\User;

class UserLoggedIn extends DomainEvent implements ShouldBeRecorded
{
    public const string NAME = 'auth.user_logged_in';

    public function __construct(public readonly User $user)
    {
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
        return $this->user->currentWorkspace?->uuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return [
            'email' => $this->user->email,
        ];
    }

    public function getContext(): array
    {
        return [];
    }
}

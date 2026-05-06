<?php

namespace App\Domain\Auth\Events;

use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use App\Models\User;

class UserLoggedOut extends DomainEvent implements ShouldBeRecorded
{
    public const string NAME = 'auth.user_logged_out';

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
        return null;
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
}

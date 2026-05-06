<?php

namespace App\Events;

use App\Domain\Auth\Events\UserRegistered;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for domain events. Provides the normalized payload shape
 * (event name, properties, user/workspace UUIDs, occurrence timestamp)
 * required by the events table and downstream listeners.
 */
abstract class DomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $context;

    public readonly CarbonImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = CarbonImmutable::now();
    }

    /**
     * Stable, dot-namespaced identifier (e.g. {@see UserRegistered::$NAME}).
     */
    abstract public function getName(): string;

    /**
     * UUID of the user who triggered the event, if known.
     */
    abstract public function getUserUuid(): ?string;

    /**
     * UUID of the workspace where the event occurred, if applicable.
     */
    abstract public function getWorkspaceUuid(): ?string;

    /**
     * Structured payload persisted to the events table as JSON.
     *
     * @return array<string, mixed>
     */
    abstract public function getProperties(): array;
}

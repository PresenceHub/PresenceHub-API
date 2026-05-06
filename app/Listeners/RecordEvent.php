<?php

namespace App\Listeners;

use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use App\Models\Event;

/**
 * Persists any dispatched event implementing ShouldBeRecorded into the events table.
 *
 * Registered against the marker interface so a single listener handles every
 * recordable domain event without per-event wiring. Runs after the active DB
 * transaction commits to avoid recording events that were rolled back.
 */
class RecordEvent
{
    public function handle(ShouldBeRecorded $event): void
    {
        if (! $event instanceof DomainEvent) {
            return;
        }

        Event::create([
            'name' => $event->getName(),
            'user_uuid' => $event->getUserUuid(),
            'workspace_uuid' => $event->getWorkspaceUuid(),
            'properties' => $event->getProperties(),
            'occurred_at' => $event->occurredAt,
        ]);
    }
}

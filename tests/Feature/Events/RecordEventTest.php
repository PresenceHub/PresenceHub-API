<?php

namespace Tests\Feature\Events;

use App\Events\Contracts\ShouldBeRecorded;
use App\Events\DomainEvent;
use App\Models\Event as EventRecord;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dispatching_a_recordable_event_persists_a_normalized_row(): void
    {
        $userUuid = (string) Str::uuid();
        $workspaceUuid = (string) Str::uuid();

        Event::dispatch(new RecordableTestEvent(
            $userUuid,
            $workspaceUuid,
            ['foo' => 'bar', 'count' => 7],
        ));

        $row = EventRecord::query()->firstOrFail();

        $this->assertSame('test.recordable', $row->name);
        $this->assertSame($userUuid, $row->user_uuid);
        $this->assertSame($workspaceUuid, $row->workspace_uuid);
        $this->assertSame(['foo' => 'bar', 'count' => 7], $row->properties);
        $this->assertNotNull($row->occurred_at);
        $this->assertTrue(Str::isUuid($row->uuid));
    }

    public function test_dispatching_an_event_without_the_marker_interface_does_not_persist(): void
    {
        Event::dispatch(new NonRecordableTestEvent);

        $this->assertSame(0, EventRecord::query()->count());
    }
}

class RecordableTestEvent extends DomainEvent implements ShouldBeRecorded
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly ?string $userUuid,
        private readonly ?string $workspaceUuid,
        private readonly array $payload,
    ) {
        parent::__construct();
    }

    public function getName(): string
    {
        return 'test.recordable';
    }

    public function getUserUuid(): ?string
    {
        return $this->userUuid;
    }

    public function getWorkspaceUuid(): ?string
    {
        return $this->workspaceUuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->payload;
    }
}

class NonRecordableTestEvent {}

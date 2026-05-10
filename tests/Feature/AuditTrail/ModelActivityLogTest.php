<?php

namespace Tests\Feature\AuditTrail;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ModelActivityLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_actor_is_stored_as_causer(): void
    {
        $actor = User::factory()->create();
        $workspace = Workspace::factory()->create();

        $this->actingAs($actor);

        $workspace->update(['name' => 'Updated Workspace']);

        $activity = Activity::query()
            ->forSubject($workspace)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($actor->getMorphClass(), $activity->causer_type);
        $this->assertSame($actor->id, $activity->causer_id);
        $this->assertSame('Updated Workspace', $activity->changes['attributes']['name']);
    }

    public function test_masked_and_excluded_fields_are_applied_for_user_model(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor);

        $user = User::factory()->create([
            'password' => 'plain-text-secret',
        ]);

        $activity = Activity::query()
            ->forSubject($user)
            ->forEvent('created')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('*****', $activity->changes['attributes']['password']);
        $this->assertArrayNotHasKey('remember_token', $activity->changes['attributes']);
        $this->assertSame($actor->id, $activity->causer_id);
    }

    public function test_system_actor_label_is_stored_when_no_authenticated_user_exists(): void
    {
        $workspace = Workspace::factory()->create();

        $workspace->update(['name' => 'System Updated Workspace']);

        $activity = Activity::query()
            ->forSubject($workspace)
            ->forEvent('updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($activity->causer_type);
        $this->assertNull($activity->causer_id);
        $this->assertSame('system', $activity->getExtraProperty('meta.actor_label'));
    }

    public function test_non_audited_models_do_not_generate_activity_logs(): void
    {
        $member = WorkspaceMember::factory()->create();

        $activityCount = Activity::query()
            ->forSubject($member)
            ->count();

        $this->assertSame(0, $activityCount);
    }
}

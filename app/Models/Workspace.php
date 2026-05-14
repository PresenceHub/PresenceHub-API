<?php

namespace App\Models;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Domain\Timeline\Concerns\HasTimeline;
use App\Enums\WorkspaceMemberRole;
use App\Models\Concerns\HasUuid;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int $owner_id
 */
#[UseFactory(WorkspaceFactory::class)]
class Workspace extends Model
{
    use HasFactory, HasTimeline, HasUuid;

    /**
     * @var list<string>
     */
    protected array $timelineInclude = [
        'name',
        'owner_id',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'owner_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function workspaceMembers(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['role', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function memberRoleFor(User $user): ?WorkspaceMemberRole
    {
        $member = $this->workspaceMembers()
            ->where('user_id', $user->id)
            ->first();

        if ($member === null) {
            return null;
        }

        return $member->role;
    }

    /**
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

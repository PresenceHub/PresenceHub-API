<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Content\Models\Post;
use App\Domain\Timeline\Concerns\HasTimeline;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $uuid
 * @property int $role_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property-read ?Role $role
 * @property-read ?Workspace $currentWorkspace
 * @property-read Collection<int, Workspace> $ownedWorkspaces
 * @property-read Collection<int, Workspace> $workspaces
 * @property-read Collection<int, Post> $posts
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasTimeline, HasUuid, Notifiable;

    /**
     * @var list<string>
     */
    protected array $timelineInclude = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * @var list<string>
     */
    protected array $timelineExclude = [
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected array $timelineMasked = [
        'password',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasOne<Workspace, $this>
     */
    public function currentWorkspace(): HasOne
    {
        return $this->hasOne(Workspace::class, 'owner_id')->latest();
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['role', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'created_by');
    }
}

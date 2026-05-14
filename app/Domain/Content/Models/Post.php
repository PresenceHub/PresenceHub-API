<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Timeline\Concerns\HasTimeline;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int $created_by
 * @property string $content
 * @property PostType $type
 * @property PostStatus $status
 * @property-read Workspace $workspace
 * @property-read User $creator
 * @property-read PostMedia[] $media
 */
#[UseFactory(PostFactory::class)]
class Post extends Model
{
    use HasFactory, HasTimeline, HasUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected array $timelineInclude = [
        'workspace_id',
        'created_by',
        'type',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'created_by',
        'content',
        'type',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'status' => PostStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PostTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }

    /**
     * @return HasMany<PostMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('order');
    }
}

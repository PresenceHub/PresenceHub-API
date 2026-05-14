<?php

namespace App\Domain\Content\Models;

use App\Domain\Timeline\Concerns\HasTimeline;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\PostMediaFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int $owner_id
 * @property int|null $post_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property int $order
 * @property Post|null $post
 * @property Workspace $workspace
 * @property User $owner
 */
#[UseFactory(PostMediaFactory::class)]
class PostMedia extends Model
{
    use HasFactory, HasTimeline, HasUuid;

    /**
     * @var string
     */
    protected $table = 'post_media';

    /**
     * @var list<string>
     */
    protected array $timelineInclude = [
        'workspace_id',
        'owner_id',
        'post_id',
        'disk',
        'path',
        'mime_type',
        'size',
        'order',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'workspace_id',
        'owner_id',
        'post_id',
        'disk',
        'path',
        'mime_type',
        'size',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (PostMedia $postMedia): void {
            if ($postMedia->path !== '') {
                Storage::disk($postMedia->disk)->delete($postMedia->path);
            }
        });
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}

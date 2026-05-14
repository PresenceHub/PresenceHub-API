<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\PostTargetStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Database\Factories\PostTargetFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $post_id
 * @property int $channel_id
 * @property PostTargetStatus $status
 * @property Carbon $scheduled_at
 * @property Carbon|null $published_at
 * @property ?string $external_post_id
 * @property int $attempt_count
 * @property ?Carbon $last_attempt_at
 * @property array<string, mixed>|null $platform_options
 * @property-read Post $post
 * @property-read Channel $channel
 * @property-read PostTargetPublishAttempt[] $publishAttempts
 */
#[UseFactory(PostTargetFactory::class)]
class PostTarget extends Model
{
    use Auditable, HasFactory, HasUuid;

    /**
     * @var list<string>
     */
    protected array $auditInclude = [
        'post_id',
        'channel_id',
        'status',
        'scheduled_at',
        'published_at',
        'external_post_id',
        'attempt_count',
        'last_attempt_at',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'channel_id',
        'status',
        'scheduled_at',
        'published_at',
        'external_post_id',
        'attempt_count',
        'last_attempt_at',
        'platform_options',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostTargetStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'platform_options' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return HasMany<PostTargetPublishAttempt, $this>
     */
    public function publishAttempts(): HasMany
    {
        return $this->hasMany(PostTargetPublishAttempt::class)->orderByDesc('attempt_number');
    }
}

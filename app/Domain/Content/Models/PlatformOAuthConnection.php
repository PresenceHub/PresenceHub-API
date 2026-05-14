<?php

namespace App\Domain\Content\Models;

use App\Domain\Timeline\Concerns\HasTimeline;
use App\Models\Concerns\HasUuid;
use Database\Factories\PlatformOAuthConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int $platform_id
 * @property string $provider_user_id
 * @property string $access_token
 * @property Carbon|null $expires_at
 * @property int $created_by
 */
#[UseFactory(PlatformOAuthConnectionFactory::class)]
class PlatformOAuthConnection extends Model
{
    /** @use HasFactory<PlatformOAuthConnectionFactory> */
    use HasFactory, HasTimeline, HasUuid;

    /**
     * @var string
     */
    protected $table = 'platform_oauth_connections';

    /**
     * @var list<string>
     */
    protected array $timelineInclude = [
        'workspace_id',
        'platform_id',
        'provider_user_id',
        'access_token',
        'expires_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected array $timelineMasked = [
        'access_token',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'platform_id',
        'provider_user_id',
        'access_token',
        'expires_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }
}

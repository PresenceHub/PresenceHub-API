<?php

namespace App\Domain\Content\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use App\Models\Platform;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int $platform_id
 * @property string $platform_account_id
 * @property string $handle
 * @property string $access_token
 * @property ?string $refresh_token
 * @property ?Carbon $token_expires_at
 * @property int $created_by
 * @property-read Platform $platform
 */
#[UseFactory(ChannelFactory::class)]
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected array $auditInclude = [
        'workspace_id',
        'platform_id',
        'platform_account_id',
        'handle',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected array $auditMasked = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'platform_id',
        'platform_account_id',
        'handle',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}

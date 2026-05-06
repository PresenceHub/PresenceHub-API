<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $user_uuid
 * @property ?string $workspace_uuid
 * @property array<string, mixed>|null $properties
 * @property Carbon $occurred_at
 */
class Event extends Model
{
    use HasUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'user_uuid',
        'workspace_uuid',
        'properties',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}

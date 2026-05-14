<?php

namespace App\Models;

use App\Domain\Timeline\Concerns\HasTimeline;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasUuid;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property string|null $description
 */
#[UseFactory(RoleFactory::class)]
class Role extends Model
{
    use HasFactory, HasSlug, HasTimeline, HasUuid;

    /**
     * @var list<string>
     */
    protected static $recordEvents = [
        'updated',
    ];

    /**
     * @var list<string>
     */
    protected array $timelineInclude = [
        'slug',
        'name',
        'description',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

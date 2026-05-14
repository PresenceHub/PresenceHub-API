<?php

declare(strict_types=1);

namespace App\Domain\Timeline\Models;

use Spatie\Activitylog\Models\Activity;

class Timeline extends Activity
{
    protected $table = 'timeline';
}

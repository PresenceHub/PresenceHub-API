<?php

declare(strict_types=1);

namespace App\Domain\Timeline\Concerns;

use Spatie\Activitylog\Contracts\LoggablePipe;
use Spatie\Activitylog\EventLogBag;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasTimeline
{
    use LogsActivity {
        shouldLogEvent as protected shouldLogEventFromLogsActivity;
    }

    protected static function bootHasTimeline(): void
    {
        static::addLogChange(new class implements LoggablePipe
        {
            public function handle(EventLogBag $event, \Closure $next): EventLogBag
            {
                $model = $event->model;

                if (! method_exists($model, 'timelineMaskedAttributes')) {
                    return $next($event);
                }

                /** @var array<string> $maskedAttributes */
                $maskedAttributes = $model->timelineMaskedAttributes();

                if ($maskedAttributes !== []) {
                    foreach (['attributes', 'old'] as $changeSet) {
                        if (! isset($event->changes[$changeSet]) || ! is_array($event->changes[$changeSet])) {
                            continue;
                        }

                        foreach ($maskedAttributes as $attribute) {
                            if (array_key_exists($attribute, $event->changes[$changeSet])) {
                                $event->changes[$changeSet][$attribute] = '*****';
                            }
                        }
                    }
                }

                if (! auth()->check()) {
                    $event->changes['meta']['actor_label'] = 'system';
                }

                return $next($event);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly($this->timelineIgnoredOnlyAttributes());

        $include = $this->timelineIncludedAttributes();
        if ($include !== []) {
            $options->logOnly($include);
        } else {
            $options->logFillable();
        }

        $exclude = $this->timelineExcludedAttributes();
        if ($exclude !== []) {
            $options->logExcept($exclude);
        }

        return $options;
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        if (! $this->timelineShouldRecord()) {
            return false;
        }

        return $this->shouldLogEventFromLogsActivity($eventName);
    }

    /**
     * @return list<string>
     */
    protected function timelineIncludedAttributes(): array
    {
        /** @var array<string> $include */
        $include = property_exists($this, 'timelineInclude') ? $this->timelineInclude : [];

        return $include;
    }

    /**
     * @return list<string>
     */
    protected function timelineExcludedAttributes(): array
    {
        /** @var array<string> $exclude */
        $exclude = property_exists($this, 'timelineExclude') ? $this->timelineExclude : [];

        return $exclude;
    }

    /**
     * @return list<string>
     */
    public function timelineMaskedAttributes(): array
    {
        /** @var array<string> $masked */
        $masked = property_exists($this, 'timelineMasked') ? $this->timelineMasked : [];

        return $masked;
    }

    /**
     * @return list<string>
     */
    protected function timelineIgnoredOnlyAttributes(): array
    {
        /** @var array<string> $ignored */
        $ignored = property_exists($this, 'timelineIgnoreIfOnly') ? $this->timelineIgnoreIfOnly : ['updated_at'];

        return $ignored;
    }

    protected function timelineShouldRecord(): bool
    {
        /** @var bool $shouldRecord */
        $shouldRecord = property_exists($this, 'shouldRecordTimeline') ? $this->shouldRecordTimeline : true;

        return $shouldRecord;
    }
}

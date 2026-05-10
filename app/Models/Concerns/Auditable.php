<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Contracts\LoggablePipe;
use Spatie\Activitylog\EventLogBag;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait Auditable
{
    use LogsActivity {
        shouldLogEvent as protected shouldLogEventFromLogsActivity;
    }

    protected static function bootAuditable(): void
    {
        static::addLogChange(new class implements LoggablePipe
        {
            public function handle(EventLogBag $event, \Closure $next): EventLogBag
            {
                $model = $event->model;

                if (! method_exists($model, 'auditMaskedAttributes')) {
                    return $next($event);
                }

                /** @var array<string> $maskedAttributes */
                $maskedAttributes = $model->auditMaskedAttributes();

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
            ->dontLogIfAttributesChangedOnly($this->auditIgnoredOnlyAttributes());

        $include = $this->auditIncludedAttributes();
        if ($include !== []) {
            $options->logOnly($include);
        } else {
            $options->logFillable();
        }

        $exclude = $this->auditExcludedAttributes();
        if ($exclude !== []) {
            $options->logExcept($exclude);
        }

        return $options;
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        if (! $this->auditShouldRecordActivity()) {
            return false;
        }

        return $this->shouldLogEventFromLogsActivity($eventName);
    }

    /**
     * @return list<string>
     */
    protected function auditIncludedAttributes(): array
    {
        /** @var array<string> $include */
        $include = property_exists($this, 'auditInclude') ? $this->auditInclude : [];

        return $include;
    }

    /**
     * @return list<string>
     */
    protected function auditExcludedAttributes(): array
    {
        /** @var array<string> $exclude */
        $exclude = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        return $exclude;
    }

    /**
     * @return list<string>
     */
    public function auditMaskedAttributes(): array
    {
        /** @var array<string> $masked */
        $masked = property_exists($this, 'auditMasked') ? $this->auditMasked : [];

        return $masked;
    }

    /**
     * @return list<string>
     */
    protected function auditIgnoredOnlyAttributes(): array
    {
        /** @var array<string> $ignored */
        $ignored = property_exists($this, 'auditIgnoreIfOnly') ? $this->auditIgnoreIfOnly : ['updated_at'];

        return $ignored;
    }

    protected function auditShouldRecordActivity(): bool
    {
        /** @var bool $shouldRecord */
        $shouldRecord = property_exists($this, 'shouldRecordActivity') ? $this->shouldRecordActivity : true;

        return $shouldRecord;
    }
}

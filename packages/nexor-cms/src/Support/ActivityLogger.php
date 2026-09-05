<?php

namespace Nexor\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Nexor\Cms\Models\ActivityLog;

/**
 * Writes the admin audit trail.
 *
 * Records what changed rather than full snapshots: only attributes that actually
 * differ are stored, and password-like values never reach the log.
 */
class ActivityLogger
{
    /** @var array<int, string> */
    protected const HIDDEN = ['password', 'remember_token', 'password_confirmation'];

    public static function log(string $action, ?Model $subject = null, ?string $description = null, ?array $changes = null): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description ?? self::describe($action, $subject),
            'changes' => $changes ? self::scrub($changes) : null,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    public static function created(Model $subject, ?string $description = null): ActivityLog
    {
        return self::log('created', $subject, $description);
    }

    public static function updated(Model $subject, ?string $description = null): ActivityLog
    {
        $changes = collect($subject->getChanges())
            ->except(['updated_at'])
            ->mapWithKeys(fn ($new, $key) => [$key => [
                'from' => $subject->getOriginal($key),
                'to' => $new,
            ]])
            ->all();

        return self::log('updated', $subject, $description, $changes ?: null);
    }

    public static function deleted(Model $subject, ?string $description = null): ActivityLog
    {
        return self::log('deleted', $subject, $description);
    }

    protected static function describe(string $action, ?Model $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        $label = $subject->getAttribute('name') ?? $subject->getAttribute('title') ?? $subject->getKey();

        return class_basename($subject).': '.$label;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected static function scrub(array $changes): array
    {
        foreach (self::HIDDEN as $key) {
            if (array_key_exists($key, $changes)) {
                $changes[$key] = ['from' => '••••', 'to' => '••••'];
            }
        }

        return $changes;
    }
}

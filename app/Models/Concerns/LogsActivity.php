<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

trait LogsActivity
{
    public const IGNORED_ATTRIBUTES = ['created_at', 'updated_at', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->recordActivity('created', static::loggableValues($model, $model->getAttributes())));

        static::updated(function (Model $model): void {
            $changes = collect(static::loggableValues($model, $model->getChanges()))
                ->mapWithKeys(fn (mixed $value, string $key) => [$key => [
                    'old' => static::loggableValue($model->getOriginal($key)),
                    'new' => $value,
                ]])
                ->all();

            if ($changes !== []) {
                $model->recordActivity('updated', $changes);
            }
        });

        static::deleted(fn (Model $model) => $model->recordActivity('deleted', null));
    }

    public static function activityActor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User && ($user->is_admin || $user->is_operator) ? $user : null;
    }

    protected static function loggableValues(Model $model, array $values): array
    {
        $ignored = [...self::IGNORED_ATTRIBUTES, ...($model->activityIgnored ?? []), $model->getKeyName()];

        return collect($values)
            ->except($ignored)
            ->map(fn (mixed $value) => static::loggableValue($value))
            ->all();
    }

    protected static function loggableValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_string($value) && mb_strlen($value) > 300) {
            return Str::limit(strip_tags($value), 300);
        }

        return $value;
    }

    public function activityLabel(): string
    {
        foreach (['number', 'name', 'code', 'title', 'sku', 'key', 'email', 'from_path'] as $attribute) {
            $value = $this->getAttribute($attribute);

            if (is_scalar($value) && filled($value)) {
                return Str::limit((string) $value, 200);
            }
        }

        return '#'.$this->getKey();
    }

    public function recordActivity(string $event, ?array $changes): void
    {
        $actor = static::activityActor();

        if (! $actor) {
            return;
        }

        try {
            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'subject_type' => $this->getMorphClass(),
                'subject_id' => $this->getKey(),
                'subject_label' => $this->activityLabel(),
                'event' => $event,
                'changes' => $changes ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

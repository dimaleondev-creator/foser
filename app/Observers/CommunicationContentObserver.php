<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CommunicationContentObserver
{
    public function saving(Model $model): void
    {
        if ($model->isDirty('status') && in_array('published', [$model->getRawOriginal('status'), $model->getAttribute('status')], true) && Auth::check()) {
            Gate::authorize('content.publish');
        }
    }

    public function created(Model $model): void
    {
        $this->record('communication.content.created', $model, [], $this->auditableValues($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $oldValues = [];
        foreach (array_keys($changes) as $field) {
            $oldValues[$field] = $model->getRawOriginal($field);
        }

        $this->record('communication.content.updated', $model, $this->auditableValues($model, $oldValues), $this->auditableValues($model, array_intersect_key($model->getAttributes(), $changes)));
    }

    public function deleted(Model $model): void
    {
        $this->record('communication.content.deleted', $model, $this->auditableValues($model, $model->getOriginal()), []);
    }

    private function auditableValues(Model $model, array $values): array
    {
        return array_filter($values, fn (mixed $value, string $field): bool => ! Str::contains(Str::lower($field), ['token', 'password', 'secret']), ARRAY_FILTER_USE_BOTH);
    }

    private function record(string $event, Model $model, array $oldValues, array $newValues): void
    {
        app(AuditLogger::class)->record($event, $model->getTable(), (string) $model->getKey(),
            $oldValues,
            $newValues);
    }
}

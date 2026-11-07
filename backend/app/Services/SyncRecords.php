<?php

namespace App\Services;

use App\Models\RecordRevision;
use App\Models\SyncAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SyncRecords
{
    public function replayCreate(string $class, string $businessId, mixed $id): ?Model
    {
        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        /** @var Model|null $row */
        $row = $class::withTrashed()
            ->where(function ($query) use ($id): void {
                $query->where('id', $id)->orWhere('client_uuid', $id);
            })
            ->first();

        if ($row === null) {
            return null;
        }

        abort_unless($row->getAttribute('business_id') === $businessId, 404);

        if (method_exists($row, 'trashed') && $row->trashed()) {
            abort(409, 'That record was removed. It was not saved again.');
        }

        return $row;
    }

    public function replayUpdate(string $businessId, mixed $key, string $subjectId): bool
    {
        if (! is_string($key) || ! Str::isUuid($key)) {
            return false;
        }

        $prior = SyncAction::query()
            ->where('business_id', $businessId)
            ->where('idempotency_key', $key)
            ->first();

        if ($prior === null) {
            return false;
        }

        abort_unless($prior->subject_id === $subjectId, 409, 'That sync key was already used.');

        return true;
    }

    public function remember(string $businessId, string $key, Model $subject): void
    {
        if (! Str::isUuid($key)) {
            return;
        }

        SyncAction::query()->create([
            'business_id' => $businessId,
            'idempotency_key' => $key,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    public function logPrevious(string $businessId, Model $subject): void
    {
        RecordRevision::query()->create([
            'business_id' => $businessId,
            'record_type' => $subject->getMorphClass(),
            'record_id' => $subject->getKey(),
            'previous' => $subject->attributesToArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function withClientId(array $attributes, mixed $id): array
    {
        unset($attributes['id']);

        if (is_string($id) && Str::isUuid($id)) {
            $attributes['id'] = $id;
            $attributes['client_uuid'] = $id;
        }

        return $attributes;
    }
}

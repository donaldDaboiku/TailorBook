<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait AssignsClientUuid
{
    protected static function bootAssignsClientUuid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('client_uuid'))) {
                $model->setAttribute('client_uuid', $model->getKey());
            }
        });
    }
}

<?php

namespace App\Models\Concerns;

use App\Enums\OfferTargetType;
use App\Models\OfferTarget;
use Illuminate\Database\Eloquent\Model;

trait CleansOfferTargets
{
    abstract protected function offerTargetType(): OfferTargetType;

    protected static function bootCleansOfferTargets(): void
    {
        static::deleting(function (Model $model): void {
            OfferTarget::query()
                ->where('target_type', $model->offerTargetType())
                ->where('target_id', $model->getKey())
                ->delete();
        });
    }
}

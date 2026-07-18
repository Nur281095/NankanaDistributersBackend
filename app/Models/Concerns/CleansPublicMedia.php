<?php

namespace App\Models\Concerns;

use App\Support\PublicStorageCleanup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

trait CleansPublicMedia
{
    /**
     * @return list<string>
     */
    abstract protected function publicMediaAttributes(): array;

    protected static function bootCleansPublicMedia(): void
    {
        static::updating(function (Model $model): void {
            foreach ($model->publicMediaAttributes() as $attribute) {
                if (! $model->isDirty($attribute)) {
                    continue;
                }

                PublicStorageCleanup::deleteReplaced(
                    $model->getOriginal($attribute),
                    $model->getAttribute($attribute),
                );
            }
        });

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive(static::class), true);

        if ($usesSoftDeletes) {
            static::forceDeleted(function (Model $model): void {
                foreach ($model->publicMediaAttributes() as $attribute) {
                    PublicStorageCleanup::deleteIfExists($model->getAttribute($attribute));
                }
            });

            return;
        }

        static::deleted(function (Model $model): void {
            foreach ($model->publicMediaAttributes() as $attribute) {
                PublicStorageCleanup::deleteIfExists($model->getAttribute($attribute));
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $user = auth()->user();

            if ($user !== null && isset($user->tenant_id)) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    $user->tenant_id
                );
            }
        });

        static::creating(function (Model $model): void {
            $user = auth()->user();

            if ($user !== null && empty($model->getAttribute('tenant_id'))) {
                $model->setAttribute('tenant_id', $user->tenant_id);
            }
        });
    }
}

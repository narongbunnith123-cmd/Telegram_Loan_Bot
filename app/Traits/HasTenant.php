<?php

namespace App\Traits;

use App\Scopes\TenantScope;

trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (!$model->tenant_id && auth()->check() && auth()->user()->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}

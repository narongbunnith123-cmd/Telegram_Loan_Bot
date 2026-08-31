<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && !auth()->user()->is_super_admin) {
            $tenantId = auth()->user()->tenant_id;

            if ($tenantId) {
                $builder->where($model->getTable() . '.tenant_id', $tenantId);
            }
        }
    }
}

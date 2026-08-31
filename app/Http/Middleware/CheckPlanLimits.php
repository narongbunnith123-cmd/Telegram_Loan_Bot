<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPlanLimits
{
    public function handle(Request $request, Closure $next, string $resource)
    {
        $tenant = auth()->user()->tenant;
        $plan   = $tenant->subscription?->plan;

        if (!$plan) {
            return response()->json(['error' => 'No active subscription'], 403);
        }

        $limit   = $plan->{'max_' . $resource};
        $current = match ($resource) {
            'groups'    => $tenant->groups()->count(),
            'loans'     => $tenant->loans()->whereIn('status', ['active', 'overdue'])->count(),
            'borrowers' => $tenant->borrowers()->count(),
            default     => 0,
        };

        if ($limit !== -1 && $current >= $limit) {
            return response()->json([
                'error'   => "Plan limit reached for {$resource}.",
                'limit'   => $limit,
                'current' => $current,
                'upgrade' => route('billing.upgrade'),
            ], 402);
        }

        return $next($request);
    }
}

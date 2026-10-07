<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->isSuspended()) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'This account is suspended.',
            ], 403);
        }

        if (! $user->hasSubscriptionAccess() && ! $this->allowsExpiredAccess($request)) {
            return response()->json([
                'message' => 'This subscription has expired.',
            ], 403);
        }

        return $next($request);
    }

    private function allowsExpiredAccess(Request $request): bool
    {
        if ($request->isMethod('GET') && $request->is('api/auth/me')) {
            return true;
        }

        if ($request->isMethod('POST') && $request->is('api/auth/logout')) {
            return true;
        }

        return $request->is('api/billing/*');
    }
}

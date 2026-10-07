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

        if ($user !== null && $user->suspended_at !== null) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'This account is suspended.',
            ], 403);
        }

        return $next($request);
    }
}

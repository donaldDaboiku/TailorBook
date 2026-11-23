<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoAccountIsReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isLogout = $request->isMethod('POST') && $request->is('api/auth/logout');

        if (
            $user instanceof User
            && $user->isDemoAccount()
            && ! $request->isMethodSafe()
            && ! $isLogout
        ) {
            abort(403, 'Demo accounts are read-only.');
        }

        return $next($request);
    }
}

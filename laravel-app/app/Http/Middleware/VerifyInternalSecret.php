<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.python_agent.secret');
        $provided = $request->bearerToken();

        if(!$expected || $provided !== $expected) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}

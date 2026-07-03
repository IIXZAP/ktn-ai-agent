<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ยามหน้าประตูฝั่ง Laravel (คู่แฝดของ security.py ฝั่ง Python - Step 2.2)
 * ตรวจว่า request ที่เข้ามาที่ /api/internal/* มาจาก Python Agent จริง
 * โดยเช็ค Header Authorization: Bearer <PYTHON_AGENT_SECRET>
 */
class VerifyPythonAgentSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.python_agent.secret');
        $token = $request->bearerToken(); // ตัด "Bearer " ให้อัตโนมัติ

        if (! $token || ! $expected || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

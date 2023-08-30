<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Closure;

class AuthBasic
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (trim(config('constants.api_key.api_username')) === trim($request->getUser())) {
            if (trim(config('constants.app.api_key.api_password')) === trim($request->getPassword())) {
                return $next($request);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => [
                        'Invalid Credential [0]'
                    ]
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 0,
                'message' => [
                    'Invalid Credential [1]'
                ]
            ], 401);
        }
    }
}

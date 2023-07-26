<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class CheckLogin
{
    public function handle($request, Closure $next)
    {
        try {
            $token = $request->header('Authorization');
            if(empty($token)) {
                return ApiResponse::responseWarning('Token tidak ditemukan, lakukan login ulang');
            }

            $formatToken = explode(" ", $token);
            $access_token = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')
                ->selectRaw("isnull(session_id, '') as session_id")
                ->where('user_api_sessions.session_id', $access_token)
                ->first();

            if(empty($sql->session_id)) {
                return ApiResponse::responseWarning('Anda belum login');
            }

            return $next($request);

        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

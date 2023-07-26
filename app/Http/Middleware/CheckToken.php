<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use App\UserApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class CheckToken
{
    public function handle($request, Closure $next)
    {
        try {
            $token = $request->header('Authorization');

            if(empty($token)) {
                return ApiResponse::responseWarning('Authorization anda salah, lakukan login ulang [3]');
            }

            $formatToken = explode(" ", $token);
            if(count($formatToken) <> 2) {
                return ApiResponse::responseWarning('Authorization anda salah, lakukan login ulang [2]');
            }

            if(trim($formatToken[0]) <> 'Bearer') {
                return ApiResponse::responseWarning('Authorization anda salah, lakukan login ulang [1]');
            }

            $access_token = trim($formatToken[1]);

            $sql = DB::table('user_api_tokens')
                    ->selectRaw("isnull(user_api_tokens.token, '') as token,
                                isnull(user_api_tokens.expired_at, 0) as expired_at")
                    ->where('user_api_tokens.token', $access_token)
                    ->orderBy('user_api_tokens.expired_at', 'desc')
                    ->first();

            if(empty($sql->token)) {
                return ApiResponse::responseWarning('Token tidak ditemukan, lakukan login ulang');
            }

            if((int)$sql->expired_at < time()) {
                $date_expired = date('Y-m-d H:i:s', strtotime('+1 day'));
                $time_expired = time() + 24*60*60;

                DB::update('update  user_api_tokens
                            set     date_expired=?, expired_at=?
                            where   token=?', [
                                $date_expired, $time_expired, $access_token
                            ]);
            }

            return $next($request);

        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

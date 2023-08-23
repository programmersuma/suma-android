<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use App\UserApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
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

            $sql = DB::connection($request->get('divisi'))
                    ->table('user_api_tokens')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_tokens.token, '') as token,
                                isnull(user_api_tokens.expired_at, 0) as expired_at,
                                isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(user_api_sessions.fcm_id, '') as fcm_id,
                                isnull(users.id, 0) as id,
                                isnull(users.user_id, '') as user_id,
                                isnull(users.role_id, '') as role_id,
                                isnull(users.email, '') as email,
                                isnull(users.companyid, '') as companyid")
                    ->leftJoin(DB::raw('user_api_sessions with (nolock)'), function($join) {
                        $join->on('user_api_sessions.session_id', '=', 'user_api_tokens.token');
                    })
                    ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                        $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                            ->on('users.companyid', '=', 'user_api_sessions.companyid');
                    })
                    ->where('user_api_tokens.token', $access_token)
                    ->first();

            if(empty($sql->token)) {
                return ApiResponse::responseWarning('Token tidak ditemukan, lakukan login ulang');
            }

            if((int)$sql->expired_at < time()) {
                $date_expired = date('Y-m-d H:i:s', strtotime('+1 day'));
                $time_expired = time() + 24*60*60;

                DB::connection($request->get('divisi'))
                    ->update('update  user_api_tokens
                            set     date_expired=?, expired_at=?
                            where   token=?', [
                                $date_expired, $time_expired, $access_token
                            ]);
            }

            if(Request::is('api/auth/login')) {
                $request->merge(['userlogin' => (object)[
                    'id'            => (int)$sql->id,
                    'user_id'       => strtoupper(trim($sql->user_id)),
                    'role_id'       => strtoupper(trim($sql->role_id)),
                    'email'         => trim($sql->email),
                    'companyid'     => strtoupper(trim($sql->companyid)),
                ]]);
            } else {
                if(empty($sql->user_id) || trim($sql->user_id) == '') {
                    return ApiResponse::responseWarning('Anda belum login, lakukan login ulang');
                }
                $request->merge(['userlogin' => (object)[
                    'id'            => (int)$sql->id,
                    'token'         => trim($sql->token),
                    'session_id'    => trim($sql->session_id),
                    'user_id'       => strtoupper(trim($sql->user_id)),
                    'role_id'       => strtoupper(trim($sql->role_id)),
                    'email'         => trim($sql->email),
                    'fcm_id'        => trim($sql->fcm_id),
                    'companyid'     => strtoupper(trim($sql->companyid)),
                ]]);
            }


            return $next($request);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

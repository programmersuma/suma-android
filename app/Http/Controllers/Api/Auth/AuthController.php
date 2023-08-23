<?php

namespace app\Http\Controllers\Api\Auth;


use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

class AuthController extends Controller {

    protected function oauthToken(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }
            $user_agent = 'Suma API - Android';
            $ip_address = $request->ip();
            $token = base64_encode(sha1($request->email.time().$request->password));

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $ip_address, $user_agent, $token) {
                DB::connection($request->get('divisi'))
                    ->insert('insert into user_api_tokens (user_agent, ip_address, token, date_expired, expired_at) values (?,?,?,?,?)',
                            [ $user_agent, $ip_address, $token, date('Y-m-d H:i:s', strtotime('+1 day')), time() + 24 * 60 * 60 ]);
            });

            $sql = DB::connection($request->get('divisi'))
                    ->table('user_api_tokens')->lock('with (nolock)')
                    ->where('token', $token)
                    ->first();

            return ApiResponse::responseSuccess('success', $sql);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function login(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'email'     => 'required|string',
                'password'  => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi, email, dan password tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('users')->lock('with (nolock)')
                    ->where('email', $request->get('email'))
                    ->orWhere('user_id', $request->get('email'))
                    ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('Alamat email atau password salah');
            }

            if(!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                return ApiResponse::responseWarning('Alamat email atau password salah');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $access_token = trim($formatToken[1]);

            $role_id = strtoupper(trim($sql->role_id));
            $user_id = strtoupper(trim($sql->user_id));
            $companyid = strtoupper(trim($sql->companyid));

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $sql, $access_token) {
                DB::connection($request->get('divisi'))
                    ->insert('exec SP_UserApiSession_Simpan ?,?,?,?,?,?,?', [
                        $access_token, $request->get('regid'), strtoupper(trim($sql->user_id)), 'Suma API - Android',
                        $request->ip(), strtoupper(trim($sql->id)), strtoupper(trim($sql->companyid))
                    ]
                );
            });

            $divisiId = (strtoupper(trim($request->get('divisi'))) == 'SQLSRV_HONDA') ? 'HONDA' : 'GENERAL';

            $sql = "select	top 1 isnull(user_sessions.session_id, '') as session_id, isnull(dealer.kd_dealer, 0) as ms_dealer_id,
                            isnull(rtrim(msdealer.id), 0) as dealer_id, isnull(rtrim(dealer.nm_dealer), '') as dealer_name,
                            isnull(rtrim(dealer.kd_dealer), '') as dealer_code, isnull(rtrim(users.user_id), '') as code_user,
                            isnull(rtrim(users.jabatan), '') as job, isnull(rtrim(users.role_id), '') as id_role,
                            isnull(rtrim(users.photo), '') as photo, isnull(rtrim(users.name), '') as name,
                            isnull(rtrim(users.telepon), '') as phone_number, isnull(rtrim(users.email), '') as email,
                            isnull(rtrim(users.companyid), '') as company, isnull(company.inisial, 0) as kantor_pusat,
                            '".strtoupper(trim($divisiId))."' as divisi
                    from
                    (
                        select	top 1 id, session_id, user_id, user_agent, ip
                        from	user_api_sessions with (nolock)
                        where	user_api_sessions.session_id='".trim($access_token)."'
                    )	user_sessions
                            left join users with (nolock) on user_sessions.user_id=users.user_id
                            left join role with (nolock) on users.role_id=role.role_id
                            left join company with (nolock) on users.companyid=company.companyid ";

            if (strtoupper(trim($role_id)) === 'D_H3') {
                $sql .= " left join dealer with (nolock) on users.user_id=dealer.kd_dealer and
                                        users.companyid=dealer.companyid ";
            } else {
                $sql .= " left join
                        (
                            select  top 1 dealer.companyid, dealer.kd_sales, dealer.kd_dealer, dealer.nm_dealer
                            from    dealer with (nolock)
                            where   dealer.kd_sales='".strtoupper(trim($user_id))."' and
                                    dealer.companyid='".strtoupper(trim($companyid))."'
                        )  dealer on user_sessions.user_id=dealer.kd_sales and
                                        '".strtoupper(trim($companyid))."'=dealer.companyid ";
            }

            $sql .= " left join msdealer with (nolock) on dealer.kd_dealer=msdealer.kd_dealer and
                                    users.companyid=msdealer.companyid ";

            $result = collect(DB::connection($request->get('divisi'))->select($sql))->first();

            return ApiResponse::responseSuccess('Signed In Successfully', $result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function forgotPassword(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'email'     => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi dan email tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('users')->lock('with (nolock)')
                    ->where('email', $request->get('email'))
                    ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('Alamat email tidak terdaftar');
            }

            $password = mt_rand(100000, 999999);

            $data = [
                'subject'   => 'Forgot Password Suma',
                'email_from'=> 'programmer.sumahondasby@gmail.com',
                'email_to'  => trim($sql->email),
                'name'      => trim($sql->name),
                'user_id'   => trim($sql->user_id),
                'role_id'   => trim($sql->role_id),
                'new_password' => $password,
            ];

            Mail::send('email.forgotpassword', $data, function ($message) use ($data) {
                $message->from($data['email_from']);
                $message->to($data['email_to']);
                $message->subject($data['subject']);
            });

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $password, $sql) {
                DB::connection($request->get('divisi'))
                    ->update('update users set password=? where email=?', [ bcrypt($password), trim($sql->email) ]);
            });

            return ApiResponse::responseSuccess('Password baru telah terkirim ke alamat email anda');
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function renewLogin(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'password' => 'required|string',
                'divisi'   => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi dan password tidak boleh kosong');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $old_token = trim($formatToken[1]);

            $sql = DB::connection($request->get('divisi'))
                    ->table('user_api_sessions')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(users.user_id, '') as user_id,
                                isnull(users.password, '') as password")
                    ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                        $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                            ->on('users.companyid', '=', 'user_api_sessions.companyid');
                    })
                    ->where('user_api_sessions.session_id', $old_token)
                    ->first();

            if (!Hash::check($request->get('password'), $sql->password)) {
                return ApiResponse::responseWarning('Password yang anda entry salah');
            }

            $date_expired = date('Y-m-d H:i:s', strtotime('+1 day'));
            $time_expired = time() + 24 * 60 * 60;

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $date_expired, $time_expired, $old_token) {
                DB::connection($request->get('divisi'))
                    ->update('update  user_api_tokens
                            set     date_expired=?, expired_at=?
                            where   token=?', [ $date_expired, $time_expired, $old_token ]);
            });

            $data_renewToken = [
                'authorization' => $sql->session_id,
                'session'       => $sql->session_id,
                'expired_at'    => $date_expired
            ];
            return ApiResponse::responseSuccess('Renewal Token Success', $data_renewToken);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function profile(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'   => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::connection($request->get('divisi'))
                        ->table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(users.id, 0) as id, isnull(users.role_id, '') as id_role,
                                    isnull(users.name, '') as name, isnull(users.jabatan, '') as job,
                                    isnull(users.photo, '') as photo, isnull(users.telepon, '') as phone_number,
                                    isnull(users.email, '') as email")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->leftJoin(DB::raw('role with (nolock)'), function($join) {
                            $join->on('users.role_id', '=', 'role.role_id');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            return ApiResponse::responseSuccess('success', $sql);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }

    }

    protected function changePassword(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'password'      => 'required|string',
                'new_password'  => 'required|string',
                'divisi'        => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi, password lama, dan password baru tidak boleh kosong');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $access_token = trim($formatToken[1]);

            $sql = DB::connection($request->get('divisi'))
                    ->table('user_api_sessions')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(users.user_id, '') as user_id,
                                isnull(users.password, '') as password")
                    ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                        $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                            ->on('users.companyid', '=', 'user_api_sessions.companyid');
                    })
                    ->where('user_api_sessions.session_id', $access_token)
                    ->first();

            if (empty($sql->user_id)) {
                return ApiResponse::responseWarning('Anda belum login');
            }

            $status_password = Hash::check($request->get('password'), $sql->password, []);
            if ($status_password == false) {
                return ApiResponse::responseWarning('Password lama anda salah');
            } else {
                DB::connection($request->get('divisi'))->transaction(function () use ($request, $sql) {
                    DB::connection($request->get('divisi'))
                        ->update('update  users
                                set     password=?
                                where   user_id=?', [ bcrypt($request->get('new_password')), strtoupper(trim($sql->user_id)) ]);
                });
                return ApiResponse::responseSuccess('Password anda berhasil diubah');
            }
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function logout(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $access_token = trim($formatToken[1]);

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $access_token) {
                DB::connection($request->get('divisi'))->table('user_api_tokens')->where('token', $access_token)->delete();
                DB::connection($request->get('divisi'))->table('user_api_sessions')->where('session_id', $access_token)->delete();
            });
            return ApiResponse::responseSuccess('Anda berhasil logout');
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

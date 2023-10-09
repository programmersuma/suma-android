<?php

namespace app\Http\Controllers\Api\Auth;


use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    protected function listDivisi(Request $request) {
        try {
            $sql = "select	isnull(users.email, '') as email, isnull(users.user_id, '') as user_id,
                            isnull(users.companyid, '') as companyid,
                            iif(isnull(ltrim(rtrim(upper(users.kd_file))), '')='A', 'HONDA', 'GENERAL') as divisi
                    from
                    (
                        select	top 1 dbhonda.dbo.users.email, dbhonda.dbo.users.user_id,
                                dbhonda.dbo.users.companyid, dbhonda.dbo.company.kd_file
                        from	dbhonda.dbo.users with (nolock)
                                    left join dbhonda.dbo.company with (nolock) on
                                                users.companyid=dbhonda.dbo.company.companyid
                        where	dbhonda.dbo.users.email='".$request->get('email')."'
                        union	all
                        select	top 1 dbsuma.dbo.users.email, dbsuma.dbo.users.user_id,
                                dbsuma.dbo.users.companyid, dbsuma.dbo.company.kd_file
                        from	dbsuma.dbo.users with (nolock)
                                    left join dbsuma.dbo.company with (nolock) on
                                                users.companyid=dbsuma.dbo.company.companyid
                        where	dbsuma.dbo.users.email='".$request->get('email')."'
                    )	users";

            $result = DB::connection('sqlsrv_honda')->select($sql);

            $data_divisi = [];

            foreach($result as $data) {
                $data_divisi[] = [
                    'divisi'    => strtoupper(trim($data->divisi))
                ];
            }

            return ApiResponse::responseSuccess('success', $data_divisi);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function checkDivisi(Request $request) {
        try {
            $sql = "select	isnull(users.email, '') as email, isnull(users.user_id, '') as user_id,
                            isnull(users.companyid, '') as companyid,
                            iif(isnull(ltrim(rtrim(upper(users.kd_file))), '')='A', 'HONDA', 'GENERAL') as divisi
                    from
                    (
                        select	top 1 dbhonda.dbo.users.email, dbhonda.dbo.users.user_id,
                                dbhonda.dbo.users.companyid, dbhonda.dbo.company.kd_file
                        from	dbhonda.dbo.users with (nolock)
                                    left join dbhonda.dbo.company with (nolock) on
                                                users.companyid=dbhonda.dbo.company.companyid
                        where	dbhonda.dbo.users.email='".$request->get('email')."'
                        union	all
                        select	top 1 dbsuma.dbo.users.email, dbsuma.dbo.users.user_id,
                                dbsuma.dbo.users.companyid, dbsuma.dbo.company.kd_file
                        from	dbsuma.dbo.users with (nolock)
                                    left join dbsuma.dbo.company with (nolock) on
                                                users.companyid=dbsuma.dbo.company.companyid
                        where	dbsuma.dbo.users.email='".$request->get('email')."'
                    )	users";

            $result = DB::connection('sqlsrv_honda')->select($sql);

            if(empty($result)) {
                return ApiResponse::responseWarning('[Divisi] : Alamat email atau password salah');
            } else {
                return ApiResponse::responseSuccess('success', null);
            }
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
                    ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('Alamat email atau password salah');
            }

            if(!Auth::attempt(['email' => $sql->email, 'password' => $request->password])) {
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
                    ->insert('exec SP_UserApiSession_Simpan1 ?,?,?,?,?,?,?,?', [
                        $access_token, $request->get('regid'), strtoupper(trim($sql->user_id)), 'Suma API - Android',
                        $request->ip(), strtoupper(trim($sql->id)), $request->get('version'), strtoupper(trim($sql->companyid))
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
                            isnull(users.id, 0) as id_user, '".strtoupper(trim($divisiId))."' as divisi
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
                'email'     => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data email tidak boleh kosong');
            }

            $sql = "select	isnull(dbhonda.dbo.company.companyid, '') as companyid,
                            iif(isnull(dbhonda.dbo.company.kd_file, 'A')='A', 'HONDA', 'GENERAL') as divisi,
                            isnull(dbhonda.dbo.users.email, '') as email,
                            isnull(dbhonda.dbo.users.user_id, '') as user_id,
                            isnull(dbhonda.dbo.users.role_id, '') as role_id
                    from	dbhonda.dbo.users
                                inner join dbhonda.dbo.company with (nolock) on
                                            dbhonda.dbo.company.companyid=dbhonda.dbo.users.companyid
                    where	dbhonda.dbo.users.email='".trim($request->get('email'))."'
                    union	all
                    select	isnull(dbsuma.dbo.company.companyid, '') as companyid,
                            iif(isnull(dbsuma.dbo.company.kd_file, 'A')='A', 'HONDA', 'GENERAL') as divisi,
                            isnull(dbsuma.dbo.users.email, '') as email,
                            isnull(dbsuma.dbo.users.user_id, '') as user_id,
                            isnull(dbsuma.dbo.users.role_id, '') as role_id
                    from	dbsuma.dbo.users
                                inner join dbsuma.dbo.company with (nolock) on
                                            dbsuma.dbo.company.companyid=dbsuma.dbo.users.companyid
                    where	dbsuma.dbo.users.email='".trim($request->get('email'))."'";

            $result = DB::connection('sqlsrv_honda')->select($sql);

            $data_user = new Collection();

            foreach($result as $data) {
                $data_user->push((object) [
                    'companyid'     => strtoupper(trim($data->companyid)),
                    'divisi'        => strtoupper(trim($data->divisi)),
                    'email'         => trim($data->email),
                    'user_id'       => strtoupper(trim($data->user_id)),
                    'role_id'       => strtoupper(trim($data->role_id)),
                ]);
            }

            if(empty($data_user)) {
                return ApiResponse::responseWarning('Alamat email tidak terdaftar');
            }

            $link_user_reset = trim($data_user[0]->email).':'.strtotime('+1 hour');

            $data = [
                'subject'       => 'Forgot Password Suma',
                'email_from'    => 'programmer.sumahondasby@gmail.com',
                'email_to'      => trim($data_user[0]->email),
                'users'         => (object)[
                    'user_id'   => strtoupper(trim($data_user[0]->user_id)),
                    'role_id'   => strtoupper(trim($data_user[0]->role_id)),
                    'email'     => trim($data_user[0]->email),
                    'link'      => trim(config('constants.app.app_url_hosting')).'/auth/reset-password/'.
                                    base64_encode(base64_encode(base64_encode(base64_encode(base64_encode($link_user_reset))))),
                ],
            ];

            Mail::send('email.forgotpassword', $data, function ($message) use ($data) {
                $message->from($data['email_from']);
                $message->to($data['email_to']);
                $message->subject($data['subject']);
            });

            DB::connection('sqlsrv_honda')->transaction(function () use ($data_user) {
                DB::connection('sqlsrv_honda')
                    ->update('update users set status_reset_password=1 where email=?', [ trim($data_user[0]->email) ]);
            });

            DB::connection('sqlsrv_general')->transaction(function () use ($data_user) {
                DB::connection('sqlsrv_general')
                    ->update('update users set status_reset_password=1 where email=?', [ trim($data_user[0]->email) ]);
            });

            return ApiResponse::responseSuccess('Password baru telah terkirim ke alamat email anda');
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function forgotPasswordCekStatus(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'email'     => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data email tidak boleh kosong');
            }

            $sql = "select	isnull(dbhonda.dbo.company.companyid, '') as companyid,
                            iif(isnull(dbhonda.dbo.company.kd_file, 'A')='A', 'HONDA', 'GENERAL') as divisi,
                            isnull(dbhonda.dbo.users.email, '') as email,
                            isnull(dbhonda.dbo.users.user_id, '') as user_id,
                            isnull(dbhonda.dbo.users.role_id, '') as role_id,
                            isnull(dbhonda.dbo.users.status_reset_password, 0) as status_reset_password
                    from	dbhonda.dbo.users
                                inner join dbhonda.dbo.company with (nolock) on
                                            dbhonda.dbo.company.companyid=dbhonda.dbo.users.companyid
                    where	dbhonda.dbo.users.email='".trim($request->get('email'))."'
                    union	all
                    select	isnull(dbsuma.dbo.company.companyid, '') as companyid,
                            iif(isnull(dbsuma.dbo.company.kd_file, 'A')='A', 'HONDA', 'GENERAL') as divisi,
                            isnull(dbsuma.dbo.users.email, '') as email,
                            isnull(dbsuma.dbo.users.user_id, '') as user_id,
                            isnull(dbsuma.dbo.users.role_id, '') as role_id,
                            isnull(dbsuma.dbo.users.status_reset_password, 0) as status_reset_password
                    from	dbsuma.dbo.users
                                inner join dbsuma.dbo.company with (nolock) on
                                            dbsuma.dbo.company.companyid=dbsuma.dbo.users.companyid
                    where	dbsuma.dbo.users.email='".trim($request->get('email'))."'";

            $result = DB::connection('sqlsrv_honda')->select($sql);

            $data_user = new Collection();

            foreach($result as $data) {
                $data_user->push((object) [
                    'companyid'             => strtoupper(trim($data->companyid)),
                    'divisi'                => strtoupper(trim($data->divisi)),
                    'email'                 => trim($data->email),
                    'user_id'               => strtoupper(trim($data->user_id)),
                    'role_id'               => strtoupper(trim($data->role_id)),
                    'status_reset_password' => trim($data->status_reset_password),
                ]);
            }

            if(empty($data_user)) {
                return ApiResponse::responseWarning('Alamat email tidak terdaftar');
            }

            return ApiResponse::responseSuccess('success', $data_user->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    protected function submitForgotPassword(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'email'     => 'required|string',
                'password'  => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data email dan password baru harus terisi');
            }

            DB::connection('sqlsrv_honda')->transaction(function () use ($request) {
                DB::connection('sqlsrv_honda')
                    ->update('update users set password=?, status_reset_password=0 where email=?', [
                        bcrypt($request->get('password')), trim($request->get('email'))
                    ]);
            });

            DB::connection('sqlsrv_general')->transaction(function () use ($request) {
                DB::connection('sqlsrv_general')
                    ->update('update users set password=?, status_reset_password=0 where email=?', [
                        bcrypt($request->get('password')), trim($request->get('email'))
                    ]);
            });

            return ApiResponse::responseSuccess('Password anda berhasil diubah');
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

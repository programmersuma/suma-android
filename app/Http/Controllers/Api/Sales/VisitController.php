<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    public function checkCheckInDashboard(Request $request) {
        try {
            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $user_id = strtoupper(trim($sql->user_id));
            $companyid = strtoupper(trim($sql->companyid));

            $sql = "select  top 1 isnull(rtrim(visit.kd_visit), '') as code_visit,
                            isnull(visit.distance, 0) as distance,
                            isnull(rtrim(visit.kd_dealer), '') as dealer_code,
                            isnull(rtrim(dealer.nm_dealer), '') as dealer_name,
                            isnull(visit.check_in, '') as checkin,
                            isnull(visit.tanggal, '') as date_must_checkin
                from
                (
                    select  top 1 visit_date.companyid, visit_date.kd_visit,
                            visit_date.kd_sales, visit_date.kd_dealer
                    from    visit_date with (nolock)
                    where   visit_date.kd_sales=? and
                            isnull(visit_date.checkin, 0)=1 and
                            isnull(visit_date.checkout, 0)=0 and
                            visit_date.companyid=?
                )   visit_date
                        inner join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                    visit_date.companyid=visit.companyid
                        inner join dealer with (nolock) on visit_date.kd_dealer=dealer.kd_dealer and
                                    visit_date.companyid=dealer.companyid
                order by visit.check_in asc";

            $result = DB::select($sql, [ strtoupper(trim($user_id)), strtoupper(trim($companyid)) ]);
            $data_checkIn = new Collection();
            $jumlah_data = 0;

            foreach ($result as $data) {
                $jumlah_data = (double)$jumlah_data + 1;

                $data_checkIn->push((object) [
                    'code_visit'        => strtoupper(trim($data->code_visit)),
                    'dealer_code'       => strtoupper(trim($data->dealer_code)),
                    'dealer_name'       => strtoupper(trim($data->dealer_name)),
                    'checkin'           => trim($data->checkin),
                    'date_must_checkin' => trim($data->date_must_checkin)
                ]);
            }

            if ((double)$jumlah_data <= 0) {
                return ApiResponse::responseWarning('Checkin not found');
            } else {
                return ApiResponse::responseSuccess('success', $data_checkIn->first());
            }
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function dateVisit(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id' => 'required'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data dealer terlebih dahulu');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $user_id = strtoupper(trim($sql->user_id));
            $companyid = strtoupper(trim($sql->companyid));

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_dealer, '') as kode_dealer")
                    ->where('id', $request->get('ms_dealer_id'))
                    ->where('companyid', $companyid)
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Id dealer tidak terdaftar');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = DB::table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(visit_date.kd_visit, '') as id,
                                isnull(visit_date.tanggal, '') as date,
                                isnull(visit_date.created_at, '') as created_at,
                                isnull(visit_date.updated_at, '') as updated_at")
                    ->where('visit_date.companyid', strtoupper(trim($companyid)))
                    ->where('visit_date.kd_sales', strtoupper(trim($user_id)))
                    ->where('visit_date.kd_dealer', strtoupper(trim($kode_dealer)))
                    ->whereRaw("isnull(visit_date.checkin, 0)=0 and
                                visit_date.tanggal >= convert(date, dateadd(month, -1, getdate()), 111)")
                    ->orderBy('visit_date.tanggal', 'asc')
                    ->get();

            $data_visit = [];

            foreach ($sql as $data) {
                $data_visit[] = [
                    'id'            => strtoupper(trim($data->id)),
                    'ms_dealer_id'  => (int)$request->get('ms_dealer_id'),
                    'date'          => trim($data->date),
                    'created_at'    => trim($data->created_at),
                    'updated_at'    => trim($data->updated_at)
                ];
            }

            return ApiResponse::responseSuccess('success', $data_visit);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function addVisit(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'date'          => 'required',
                'latitude'      => 'required',
                'longitude'     => 'required',
                'ms_dealer_id'  => 'required',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('ms_dealer_id, date, latitude, longitude required');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $user_id = strtoupper(trim($sql->user_id));
            $companyid = strtoupper(trim($sql->companyid));

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer,
                                isnull(dealer.latitude, '') as latitude,
                                isnull(dealer.longitude, '') as longitude,
                                cast((6371 *
                                    acos(
                                        cos(radians(".$request->get('latitude').")) * cos(radians(isnull(dealer.latitude, 0))) *
                                                cos(radians(isnull(dealer.longitude, 0)) -
                                            radians(".$request->get('longitude').")) + sin(radians(".$request->get('latitude').")) *
                                                sin(radians(isnull(dealer.latitude, 0)))
                                    )
                                ) as decimal(18, 2)) * 1000 as distance")
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'msdealer.kd_dealer')
                            ->on('dealer.companyid', '=', 'msdealer.companyid');
                        })
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('dealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '' ) {
                return ApiResponse::responseWarning('Id dealer tidak ditemukan');
            }

            $distance = (double)$sql->distance;

            $kode_visit = 'VS'.strtoupper(trim($companyid)).date('ymd', strtotime($request->get('date'))).
                            strtoupper(trim($user_id)).strtoupper(trim($sql->kode_dealer));

            DB::transaction(function () use ($request, $sql, $kode_visit, $user_id, $distance, $companyid) {
                DB::insert('exec SP_VisitTmp_Simpan_new ?,?,?,?,?,?,?,?,?', [
                    strtoupper(trim($kode_visit)), strtoupper(trim($user_id)), strtoupper(trim(trim($sql->kode_dealer))),
                    trim($request->get('latitude')), trim($request->get('longitude')), (double)$distance,
                    strtoupper(trim($request->get('keterangan'))), strtoupper(trim($companyid)),
                    strtoupper(trim($user_id))
                ]);
            });

            $sql = DB::table('visittmp')->lock('with (nolock)')
                    ->selectRaw("isnull(visittmp.kd_visit, '') as code_visit,
                                isnull(visittmp.distance, 0) as distance")
                    ->where('visittmp.kd_visit', strtoupper(trim($kode_visit)))
                    ->where('visittmp.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->code_visit)) {
                return ApiResponse::responseWarning('Data visit tidak ditemukan, coba lagi');
            }

            $status = 0;
            if((double)$sql->distance > 300) {
                $status = 0;
            } else {
                $status = 1;
            }

            $data = [
                'code_visit'    => strtoupper(trim($sql->code_visit)),
                'distance'      => number_format($sql->distance),
                'status'        => (int)$status
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function checkIn(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'code_visit'    => 'required',
                'latitude'      => 'required',
                'longitude'     => 'required',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Code visit required');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $companyid = strtoupper(trim($sql->companyid));

            $sql = DB::table('visittmp')->lock('with (nolock)')
                    ->selectRaw("isnull(visittmp.kd_visit, '') as code_visit,
                                isnull(visittmp.kd_sales, 0) as salesman,
                                cast((6371 *
                                    acos(
                                        cos(radians(".$request->get('latitude').")) * cos(radians(isnull(dealer.latitude, 0))) *
                                                cos(radians(isnull(dealer.longitude, 0)) -
                                            radians(".$request->get('longitude').")) + sin(radians(".$request->get('latitude').")) *
                                                sin(radians(isnull(dealer.latitude, 0)))
                                    )
                                ) as decimal(18, 2)) * 1000 as distance")
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'visittmp.kd_dealer')
                            ->on('dealer.companyid', '=', 'visittmp.companyid');
                    })
                    ->where('visittmp.kd_visit', strtoupper(trim($request->get('code_visit'))))
                    ->where('visittmp.companyid', strtoupper(trim($companyid)))
                    ->first();

            if (empty($sql->code_visit) || trim($sql->code_visit) == '') {
                return ApiResponse::responseWarning('Kode visit tidak ditemukan, ulangi langkah visit dari awal');
            }

            if ((double)$sql->distance > 300) {
                $data = [
                    'code_visit'    => strtoupper(trim($request->get('code_visit'))),
                    'distance'      => number_format($sql->distance),
                    'status'        => 0
                ];
                return ApiResponse::responseSuccess('POSISI_TERLALU_JAUH_DARI_DEALER', $data);
            }

            $kode_sales = strtoupper(trim($sql->salesman));
            $distance = (double)$sql->distance;

            $sql = DB::table('visit')->lock('with (nolock)')
                    ->selectRaw("isnull(visit.kd_visit, '') as code_visit")
                    ->where('visit.companyid', strtoupper(trim($companyid)))
                    ->where('visit.kd_sales', strtoupper(trim($kode_sales)))
                    ->whereRaw("visit.check_out is null")
                    ->orderBy('visit.check_in', 'asc')
                    ->first();

            if(!empty($sql->code_visit) && trim($sql->code_visit) != '') {
                return ApiResponse::responseWarning('Anda belum di check out di toko sebelumnya. Lakukan proses check out terlebih dahulu');
            }

            DB::transaction(function () use ($request, $distance, $companyid) {
                DB::insert("exec SP_Visit_Simpan1 ?,?,?,?,?", [
                    strtoupper(trim($request->get('code_visit'))), $request->get('latitude'),
                    $request->get('longitude'), $distance, strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Anda Berhasil Check-In', strtoupper(trim($request->get('code_visit'))));
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function checkOut(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'code_visit' => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Code visit required');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $companyid = strtoupper(trim($sql->companyid));

            DB::transaction(function () use ($request, $companyid) {
                DB::insert('exec SP_Visit_CheckOut ?,?,?', [
                    strtoupper(trim($request->get('code_visit'))),
                    strtoupper(trim($request->get('keterangan'))),
                    strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Anda Berhasil Check-Out', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listPlanningVisit(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'tanggal'   => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Kolom tanggal harus terisi');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.role_id, '') as role_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $user_id = strtoupper(trim($sql->user_id));
            $role_id = strtoupper(trim($sql->role_id));
            $companyid = strtoupper(trim($sql->companyid));
            $salesman = '';

            /* ==================================================================== */
            /* Cek Role Id Supervisor */
            /* ==================================================================== */
            if(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql = DB::table('superspv')->lock('with (nolock)')
                        ->selectRaw("isnull(superspv.kd_spv, '') as kode_supervisor")
                        ->where('superspv.nm_spv', strtoupper(trim($user_id)))
                        ->where('superspv.companyid', strtoupper(trim($companyid)))
                        ->first();

                if(empty($sql->kode_supervisor) && trim($sql->kode_supervisor) == '') {
                    return ApiResponse::responseWarning('Kode supervisor anda tidak ditemukan, hubungi IT Programmer');
                }

                $kode_supervisor = strtoupper(trim($sql->kode_supervisor));

                $sql = DB::table('salesman')->lock('with (nolock)')
                        ->selectRaw("isnull(salesman.kd_sales, '') as kode_sales")
                        ->where('salesman.companyid', strtoupper(trim($companyid)))
                        ->where('salesman.spv', $kode_supervisor)
                        ->get();

                foreach($sql as $data) {
                    if(trim($salesman) == '') {
                        $salesman = "'".strtoupper(trim($data->kode_sales))."'";
                    } else {
                        $salesman .= ",'".strtoupper(trim($data->kode_sales))."'";
                    }
                }

                if(trim($salesman) == '') {
                    return ApiResponse::responseWarning('Anda belum memiliki salesman');
                }
            }

            $sql = "select	isnull(visit_date.kd_visit, '') as visit_code,
                            isnull(rtrim(visit_date.kd_sales), '') as sales_code,
                            isnull(salesman.nm_sales, '') as sales_name,
                            isnull(format(visit_date.tanggal, 'MMMM, dd yyyy'), '') as date,
                            isnull(rtrim(visit_date.kd_dealer), '') as dealer_code,
                            isnull(dealer.nm_dealer, '') as dealer_name,
                            isnull(format(visit.check_in, 'dd MMM yyyy'), '') as check_in_date,
                            isnull(convert(varchar(8), visit.check_in, 114), '') as check_in_time,
                            isnull(format(visit.check_out, 'dd MMM yyyy'), '') as check_out_date,
                            isnull(convert(varchar(8), visit.check_out, 114), '') as check_out_time
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal,
                                visit_date.kd_sales, visit_date.kd_dealer
                        from	visit_date with (nolock)
                        where	visit_date.companyid=? and
                                visit_date.tanggal=?";

            if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
                $sql .= " and visit_date.kd_sales='".strtoupper(trim($user_id))."'";
            } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql .= " and visit_date.kd_sales in (".strtoupper(trim($salesman)).")";
            }

            if(!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $sql .= " and visit_date.kd_sales='".strtoupper(trim($request->get('salesman')))."'";
            }

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $sql .= " and visit_date.kd_dealer='".strtoupper(trim($request->get('dealer')))."'";
            }

            $sql .= " )	visit_date
                            left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                        visit_date.companyid=visit.companyid
                            left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                        visit_date.companyid=salesman.companyid
                            left join dealer with (nolock) on visit_date.kd_dealer=dealer.kd_dealer and
                                        visit_date.companyid=dealer.companyid
                    order by visit_date.tanggal asc, visit.check_in asc, visit.check_out asc";

            $result = DB::select($sql, [ strtoupper(trim($companyid)), $request->get('tanggal') ]);

            return ApiResponse::responseSuccess('success', $result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function addPlanningVisit(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'tanggal'       => 'required|string',
                'salesman'      => 'required|string',
                'dealer'        => 'required|string',
                'keterangan'    => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Isi data planning visit secara lengkap');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.role_id, '') as role_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $companyid = strtoupper(trim($sql->companyid));

            $sql = DB::table('salesk_dtl')->lock('with (nolock)')
                    ->selectRaw("isnull(salesk_dtl.kd_sales, '') as kode_sales")
                    ->where('salesk_dtl.kd_sales', strtoupper(trim($request->get('salesman'))))
                    ->where('salesk_dtl.kd_dealer', strtoupper(trim($request->get('dealer'))))
                    ->where('salesk_dtl.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_sales) || trim($sql->kode_sales) == '') {
                return ApiResponse::responseWarning('Data salesman '.strtoupper(trim($request->get('salesman'))).' tidak terdaftar di dealer '.strtoupper(trim($request->get('dealer'))));
            }

            DB::transaction(function () use ($request, $companyid) {
                DB::insert('exec SP_PlanVisitSales_Simpan ?,?,?,?,?', [
                    trim($request->get('tanggal')), strtoupper(trim($request->get('dealer'))),
                    strtoupper(trim($request->get('salesman'))), strtoupper(trim($request->get('keterangan'))),
                    strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data Berhasil Disimpan', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function deletePlanningVisit(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'visit_code' => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data visit salesman terlebih dahulu');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                        ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                    isnull(users.user_id, '') as user_id,
                                    isnull(users.role_id, '') as role_id,
                                    isnull(users.companyid, '') as companyid")
                        ->leftJoin(DB::raw('users with (nolock)'), function($join) {
                            $join->on('users.user_id', '=', 'user_api_sessions.user_id')
                                ->on('users.companyid', '=', 'user_api_sessions.companyid');
                        })
                        ->where('user_api_sessions.session_id', $session_id)
                        ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan, lakukan login ulang');
            }

            $companyid = strtoupper(trim($sql->companyid));

            $sql = DB::table('visit')->lock('with (nolock)')
                    ->selectRaw("isnull(visit.kd_visit, '') as code_visit")
                    ->where('visit.kd_visit', strtoupper(trim(trim($request->get('visit_code')))))
                    ->where('visit.companyid', strtoupper(trim(trim($companyid))))
                    ->first();

            if (!empty($sql->code_visit)) {
                return ApiResponse::responseWarning('Planning visit yang telah di kunjungi salesman tidak dapat dihapus');
            }

            DB::transaction(function () use ($request, $companyid) {
                DB::delete("exec SP_PlanVisitSales_Hapus ?,?", [
                    strtoupper(trim($request->get('visit_code'))),
                    strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data Berhasil Dihapus', null);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

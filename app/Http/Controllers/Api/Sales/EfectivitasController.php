<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;

class EfectivitasController extends Controller {

    public function efectivitasSalesman(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'start_date' => 'required',
                'end_date'   => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Start date and end date required');
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
            $companyid = strtoupper(trim($sql->companyid));

            $dealer_id = '';
            $dealer_code_visit = '';

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $data_filter_dealer = explode(',', str_replace('[', '', str_replace(']', '', $request->get('dealer'))));
                foreach($data_filter_dealer as $filter_dealer) {
                    if(trim($dealer_id) == '') {
                        $dealer_id = $filter_dealer;
                    } else {
                        $dealer_id .= ",".$filter_dealer;
                    }
                }
            }

            $sql = DB::table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(visit_date.kd_dealer, '') as kode_dealer")
                    ->leftJoin(DB::raw('msdealer with (nolock)'), function($join) {
                        $join->on('msdealer.kd_dealer', '=', 'visit_date.kd_dealer')
                            ->on('msdealer.companyid', '=', 'visit_date.companyid');
                    })
                    ->whereRaw("visit_date.tanggal between '".trim($request->get('start_date'))."' and
                                    '".trim($request->get('end_date'))."'")
                    ->where('visit_date.kd_sales', strtoupper(trim($user_id)))
                    ->where('visit_date.companyid', strtoupper(trim($companyid)));

            if(trim($dealer_id) != '') {
                $sql->whereRaw("msdealer.id in (".$dealer_id.")");
            }

            $result = $sql->groupByRaw("visit_date.kd_dealer")
                        ->orderBy('visit_date.kd_dealer', 'asc')
                        ->paginate(10);

            foreach($result as $data) {
                if(trim($dealer_code_visit) == '') {
                    $dealer_code_visit = "'".strtoupper(trim($data->kode_dealer))."'";
                } else {
                    $dealer_code_visit .= ",'".strtoupper(trim($data->kode_dealer))."'";
                }
            }

            $sql = "select	isnull(visit.companyid, '') as companyid, isnull(salesman.id_sales, 0) as salesman_id,
                            isnull(dealer.kd_sales, '') as kode_sales, isnull(salesman.nm_sales, '') as nama_sales,
                            isnull(msdealer.id, 0) as dealer_id, isnull(visit.kd_dealer, '') as kode_dealer,
                            isnull(dealer.nm_dealer, '') as nama_dealer, isnull(dealer.alamat1, '') as alamat_dealer,
                            isnull(convert(varchar(10), visit.tanggal_plan_visit, 120), '') as tanggal_plan_visit,
                            isnull(convert(varchar(10), visit.tanggal_visit, 120), '') as tanggal_visit,
                            isnull(visit.id_visit, 0) as visit_id, isnull(visit.amount, 0) as amount_order,
                            isnull(visit.realisasi_prosentase, 0) as realisasi_prosentase,
                            isnull(visit.efectivitas_prosentase, 0) as efectivitas_prosentase
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_dealer, visit_date.tanggal as tanggal_plan_visit,
                                visit.id_visit, visit.tanggal as tanggal_visit, sum(isnull(pof.total, 0)) as amount,
                                iif(isnull(visit.kd_visit, '') <> '', 100, 0) as realisasi_prosentase,
                                case
                                    when isnull(visit.kd_visit, '') <> '' and sum(isnull(pof.total, 0)) > 0 then 100
                                    when isnull(visit.kd_visit, '') <> '' and sum(isnull(pof.total, 0)) <= 0 then 90
                                    when isnull(visit.kd_visit, '') = '' and sum(isnull(pof.total, 0)) > 0 then 80
                                else 0
                                end as efectivitas_prosentase
                        from
                        (
                            select	visit_date.companyid, visit_date.kd_dealer,
                                    visit_date.tanggal
                            from	visit_date with (nolock)
                            where	visit_date.tanggal between '".trim($request->get('start_date'))."' and
                                                '".trim($request->get('end_date'))."' and
                                    visit_date.kd_sales='".strtoupper(trim($user_id))."' and
                                    visit_date.companyid='".strtoupper(trim($companyid))."'";

            if(trim($dealer_code_visit) != '') {
                $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($dealer_code_visit)).")";
            }

            $sql .= " )	visit_date
                                left join visit with (nolock) on visit_date.tanggal=visit.tanggal and
                                            visit_date.kd_dealer=visit.kd_dealer and
                                            visit_date.companyid=visit.companyid
                                left join pof with (nolock) on visit_date.tanggal=pof.tgl_pof and
                                            visit_date.kd_dealer=pof.kd_dealer and
                                            visit_date.companyid=pof.companyid
                        group by	visit_date.companyid, visit_date.kd_dealer, visit_date.tanggal,
                                    visit.id_visit, visit.kd_visit, visit.tanggal
                    )	visit
                            inner join msdealer with (nolock) on visit.kd_dealer=msdealer.kd_dealer and
                                        visit.companyid=msdealer.companyid
                            inner join dealer with (nolock) on visit.kd_dealer=dealer.kd_dealer and
                                        visit.companyid=dealer.companyid
                            left join salesman with (nolock) on dealer.kd_sales=salesman.kd_sales and
                                        visit.companyid=salesman.companyid
                    order by visit.companyid asc, visit.kd_dealer asc, visit.tanggal_plan_visit asc,
                            visit.tanggal_visit asc";

            $result = DB::select($sql);

            $data_visit = new Collection();
            $data_visit_detail = new Collection();

            foreach($result as $data) {
                $data_visit_detail->push((object) [
                    'dealer_code'               => strtoupper(trim($data->kode_dealer)),
                    'salesman_code'             => strtoupper(trim($data->kode_sales)),
                    'id'                        => (int)$data->visit_id,
                    'plan_visit'                => (trim($data->tanggal_plan_visit) == '') ? null : trim($data->tanggal_plan_visit),
                    'actual_visit'              => (trim($data->tanggal_visit) == '') ? null : trim($data->tanggal_visit),
                    'amount'                    => (double)$data->amount_order,
                    'realisasi_persentase'      => (float)$data->realisasi_prosentase,
                    'efectivitas_persentase'    => (float)$data->efectivitas_prosentase
                ]);

                $data_visit->push((object) [
                    'dealer_id'         => (int)$data->dealer_id,
                    'dealer_code'       => strtoupper(trim($data->kode_dealer)),
                    'dealer_name'       => strtoupper(trim($data->nama_dealer)),
                    'dealer_address'    => strtoupper(trim($data->alamat_dealer)),
                    'salesman_id'       => (int)$data->salesman_id,
                    'salesman_code'     => strtoupper(trim($data->kode_sales)),
                    'salesman_name'     => strtoupper(trim($data->nama_sales)),
                    'plan_visit'        => (trim($data->tanggal_plan_visit) == '') ? null : trim($data->tanggal_plan_visit),
                    'actual_visit'      => (trim($data->tanggal_visit) == '') ? null : trim($data->tanggal_visit),
                    'amount'            => (double)$data->amount_order,
                ]);
            }

            $kode_dealer_visit = '';
            $kode_salesman_visit = '';
            $data_visit_salesman_dealer = new Collection();

            foreach($data_visit as $data_sales_dealer) {
                if(strtoupper(trim($kode_dealer_visit)) != strtoupper(trim($data_sales_dealer->dealer_code))) {
                    if(strtoupper(trim($kode_salesman_visit)) != strtoupper(trim($data_sales_dealer->salesman_code))) {
                        $amount = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->sum('amount');

                        $jml_order = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->count();

                        $jml_plan = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('plan_visit')
                                    ->count();

                        $jml_actual = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('actual_visit')
                                    ->count();

                        $data_visit_salesman_dealer->push((object) [
                            'dealer_id'         => (int)$data_sales_dealer->dealer_id,
                            'dealer_code'       => strtoupper(trim($data_sales_dealer->dealer_code)),
                            'dealer_name'       => strtoupper(trim($data_sales_dealer->dealer_name)),
                            'dealer_address'    => strtoupper(trim($data_sales_dealer->dealer_address)),
                            'order'             => (double)$amount,
                            'salesman_id'       => (int)$data_sales_dealer->salesman_id,
                            'salesman_code'     => strtoupper(trim($data_sales_dealer->salesman_code)),
                            'salesman_name'     => strtoupper(trim($data_sales_dealer->salesman_name)),
                            'plan'              => (double)$jml_plan,
                            'actual'            => (double)$jml_actual,
                            'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                            'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'percentage'        => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'visit'             => $data_visit_detail
                                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                                    ->values()
                                                    ->all(),
                        ]);

                        $kode_salesman_visit = strtoupper(trim($data_sales_dealer->salesman_code));
                        $kode_dealer_visit = strtoupper(trim($data_sales_dealer->dealer_code));
                    }
                } else {
                    if(strtoupper(trim($kode_salesman_visit)) != strtoupper(trim($data_sales_dealer->salesman_code))) {
                        $amount = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->sum('amount');

                        $jml_order = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->count();

                        $jml_plan = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('plan_visit')
                                    ->count();

                        $jml_actual = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('actual_visit')
                                    ->count();

                        $data_visit_salesman_dealer->push((object) [
                            'dealer_id'         => (int)$data_sales_dealer->dealer_id,
                            'dealer_code'       => strtoupper(trim($data_sales_dealer->dealer_code)),
                            'dealer_name'       => strtoupper(trim($data_sales_dealer->dealer_name)),
                            'dealer_address'    => strtoupper(trim($data_sales_dealer->dealer_address)),
                            'order'             => (double)$amount,
                            'salesman_id'       => (int)$data_sales_dealer->salesman_id,
                            'salesman_code'     => strtoupper(trim($data_sales_dealer->salesman_code)),
                            'salesman_name'     => strtoupper(trim($data_sales_dealer->salesman_name)),
                            'plan'              => (double)$jml_plan,
                            'actual'            => (double)$jml_actual,
                            'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                            'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'percentage'        => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'visit'             => $data_visit_detail
                                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                                    ->values()
                                                    ->all(),
                        ]);
                        $kode_salesman_visit = strtoupper(trim($data_sales_dealer->salesman_code));
                    }
                }
            }

            $kode_dealer_visit = '';
            $data_dealer_visit = new Collection();

            foreach($data_visit_salesman_dealer as $data_dealer) {
                if(strtoupper(trim($kode_dealer_visit)) != strtoupper(trim($data_dealer->dealer_code))) {
                    $amount = $data_visit_detail
                                ->where('dealer_code', strtoupper(trim($data_dealer->dealer_code)))
                                ->where('amount', '>', 0)
                                ->sum('amount');

                    $jml_order = $data_visit_detail
                                ->where('dealer_code', strtoupper(trim($data_dealer->dealer_code)))
                                ->where('amount', '>', 0)
                                ->count();

                    $jml_plan = $data_visit_detail
                                ->where('dealer_code', strtoupper(trim($data_dealer->dealer_code)))
                                ->whereNotNull('plan_visit')
                                ->count();

                    $jml_actual = $data_visit_detail
                                ->where('dealer_code', strtoupper(trim($data_dealer->dealer_code)))
                                ->whereNotNull('actual_visit')
                                ->count();

                    $data_dealer_visit->push((object) [
                        'dealer_id'     => (int)$data_dealer->dealer_id,
                        'code'          => strtoupper(trim($data_dealer->dealer_code)),
                        'name'          => strtoupper(trim($data_dealer->dealer_name)),
                        'address'       => strtoupper(trim($data_dealer->dealer_address)),
                        'plan'          => (double)$jml_plan,
                        'actual'        => (double)$jml_actual,
                        'realisasi'     => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                        'efectivitas'   => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                        'order'         => (double)$amount,
                        'detail'        => $data_visit_salesman_dealer
                                            ->where('dealer_code', strtoupper(trim($data_dealer->dealer_code)))
                                            ->values()
                                            ->all(),
                    ]);

                    $kode_dealer_visit = strtoupper(trim($data_dealer->dealer_code));
                }
            }

            $data_result = new Collection();

            $amount = $data_visit_detail->where('amount', '>', 0)->sum('amount');
            $jml_order = $data_visit_detail->where('amount', '>', 0)->count();
            $jml_plan = $data_visit_detail->whereNotNull('plan_visit')->count();
            $jml_actual = $data_visit_detail->whereNotNull('actual_visit')->count();

            $data_result->push((object) [
                'actual'       => (double)$jml_actual,
                'target'       => (double)$jml_plan,
                'realisasi'    => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                'efectivitas'  => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                'amount_total' => (double)$amount,
                'page'         => (int)$request->get('page'),
                'data'         => $data_dealer_visit->values()->all(),
            ]);

            return ApiResponse::responseSuccess('success', $data_result->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function efectivitasKoordinator(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'start_date' => 'required',
                'end_date'   => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Start date and end date required');
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
            $companyid = strtoupper(trim($sql->companyid));

            $salesman_id = '';
            $salesman_code = '';

            if(!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $data_filter_salesman = explode(',', str_replace('[', '', str_replace(']', '', $request->get('salesman'))));
                foreach($data_filter_salesman as $filter_salesman) {
                    if(trim($salesman_id) == '') {
                        $salesman_id = $filter_salesman;
                    } else {
                        $salesman_id .= ",".$filter_salesman;
                    }
                }
            }

            $sql = DB::table('salesman')->lock('with (nolock)')
                    ->selectRaw("isnull(salesman.kd_sales, '') as kode_sales")
                    ->leftJoin(DB::raw('superspv with (nolock)'), function($join) {
                        $join->on('superspv.kd_spv', '=', 'salesman.spv')
                            ->on('superspv.companyid', '=', 'salesman.companyid');
                    })
                    ->where('salesman.companyid', strtoupper(trim($companyid)));

            if(trim($salesman_id) == '') {
                $sql->where('superspv.nm_spv', strtoupper(trim($user_id)));
            } else {
                $sql->whereRaw("salesman.id_sales in (".trim($salesman_id).")");
            }

            $result = $sql->get();

            foreach($result as $data) {
                if(trim($salesman_code) == '') {
                    $salesman_code = "'".strtoupper(trim($data->kode_sales))."'";
                } else {
                    $salesman_code .= ",'".strtoupper(trim($data->kode_sales))."'";
                }
            }

            if(trim($salesman_code) == '') {
                return ApiResponse::responseWarning('Supervisor atau koordinator yang anda pilih tidak memiliki salesman');
            }

            $dealer_id = '';
            $dealer_code_visit = '';

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $data_filter_dealer = explode(',', str_replace('[', '', str_replace(']', '', $request->get('dealer'))));
                foreach($data_filter_dealer as $filter_dealer) {
                    if(trim($dealer_id) == '') {
                        $dealer_id = $filter_dealer;
                    } else {
                        $dealer_id .= ",".$filter_dealer;
                    }
                }
            }

            $sql = DB::table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(visit_date.kd_dealer, '') as kode_dealer")
                    ->leftJoin(DB::raw('msdealer with (nolock)'), function($join) {
                        $join->on('msdealer.kd_dealer', '=', 'visit_date.kd_dealer')
                            ->on('msdealer.companyid', '=', 'visit_date.companyid');
                    })
                    ->whereRaw("visit_date.tanggal between '".trim($request->get('start_date'))."' and
                                    '".trim($request->get('end_date'))."'")
                    ->where('visit_date.companyid', strtoupper(trim($companyid)));

            if(trim($salesman_code) != '') {
                $sql->whereRaw("visit_date.kd_sales in (".$salesman_code.")");
            }

            if(trim($dealer_id) != '') {
                $sql->whereRaw("msdealer.id in (".$dealer_id.")");
            }

            $result = $sql->groupByRaw("visit_date.kd_dealer")
                        ->orderBy('visit_date.kd_dealer', 'asc')
                        ->paginate(10);

            foreach($result as $data) {
                if(trim($dealer_code_visit) == '') {
                    $dealer_code_visit = "'".strtoupper(trim($data->kode_dealer))."'";
                } else {
                    $dealer_code_visit .= ",'".strtoupper(trim($data->kode_dealer))."'";
                }
            }

            $sql = "select	isnull(visit.companyid, '') as companyid, isnull(salesman.id_sales, 0) as salesman_id,
                            isnull(dealer.kd_sales, '') as kode_sales, isnull(salesman.nm_sales, '') as nama_sales,
                            isnull(msdealer.id, 0) as dealer_id, isnull(visit.kd_dealer, '') as kode_dealer,
                            isnull(dealer.nm_dealer, '') as nama_dealer, isnull(dealer.alamat1, '') as alamat_dealer,
                            isnull(convert(varchar(10), visit.tanggal_plan_visit, 120), '') as tanggal_plan_visit,
                            isnull(convert(varchar(10), visit.tanggal_visit, 120), '') as tanggal_visit,
                            isnull(visit.id_visit, 0) as visit_id, isnull(visit.amount, 0) as amount_order,
                            isnull(visit.realisasi_prosentase, 0) as realisasi_prosentase,
                            isnull(visit.efectivitas_prosentase, 0) as efectivitas_prosentase
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_dealer, visit_date.tanggal as tanggal_plan_visit,
                                visit.id_visit, visit.tanggal as tanggal_visit, sum(isnull(pof.total, 0)) as amount,
                                iif(isnull(visit.kd_visit, '') <> '', 100, 0) as realisasi_prosentase,
                                case
                                    when isnull(visit.kd_visit, '') <> '' and sum(isnull(pof.total, 0)) > 0 then 100
                                    when isnull(visit.kd_visit, '') <> '' and sum(isnull(pof.total, 0)) <= 0 then 90
                                    when isnull(visit.kd_visit, '') = '' and sum(isnull(pof.total, 0)) > 0 then 80
                                else 0
                                end as efectivitas_prosentase
                        from
                        (
                            select	visit_date.companyid, visit_date.kd_dealer,
                                    visit_date.tanggal
                            from	visit_date with (nolock)
                            where	visit_date.tanggal between '".trim($request->get('start_date'))."' and
                                                '".trim($request->get('end_date'))."' and
                                    visit_date.companyid='".strtoupper(trim($companyid))."'";

            if(trim($dealer_code_visit) != '') {
                $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($dealer_code_visit)).")";
            }

            if(trim($salesman_code) != '') {
                $sql .= " and visit_date.kd_sales in (".strtoupper(trim($salesman_code)).")";
            }

            $sql .= " )	visit_date
                                left join visit with (nolock) on visit_date.tanggal=visit.tanggal and
                                            visit_date.kd_dealer=visit.kd_dealer and
                                            visit_date.companyid=visit.companyid
                                left join pof with (nolock) on visit_date.tanggal=pof.tgl_pof and
                                            visit_date.kd_dealer=pof.kd_dealer and
                                            visit_date.companyid=pof.companyid
                        group by	visit_date.companyid, visit_date.kd_dealer, visit_date.tanggal,
                                    visit.id_visit, visit.kd_visit, visit.tanggal
                    )	visit
                            inner join msdealer with (nolock) on visit.kd_dealer=msdealer.kd_dealer and
                                        visit.companyid=msdealer.companyid
                            inner join dealer with (nolock) on visit.kd_dealer=dealer.kd_dealer and
                                        visit.companyid=dealer.companyid
                            left join salesman with (nolock) on dealer.kd_sales=salesman.kd_sales and
                                        visit.companyid=salesman.companyid
                    order by visit.companyid asc, visit.kd_dealer asc, visit.tanggal_plan_visit asc,
                            visit.tanggal_visit asc";

            $result = DB::select($sql);

            $data_visit = new Collection();
            $data_visit_detail = new Collection();

            foreach($result as $data) {
                $data_visit_detail->push((object) [
                    'dealer_code'               => strtoupper(trim($data->kode_dealer)),
                    'salesman_code'             => strtoupper(trim($data->kode_sales)),
                    'id'                        => (int)$data->visit_id,
                    'plan_visit'                => (trim($data->tanggal_plan_visit) == '') ? null : trim($data->tanggal_plan_visit),
                    'actual_visit'              => (trim($data->tanggal_visit) == '') ? null : trim($data->tanggal_visit),
                    'amount'                    => (double)$data->amount_order,
                    'realisasi_persentase'      => (float)$data->realisasi_prosentase,
                    'efectivitas_persentase'    => (float)$data->efectivitas_prosentase
                ]);

                $data_visit->push((object) [
                    'dealer_id'         => (int)$data->dealer_id,
                    'dealer_code'       => strtoupper(trim($data->kode_dealer)),
                    'dealer_name'       => strtoupper(trim($data->nama_dealer)),
                    'dealer_address'    => strtoupper(trim($data->alamat_dealer)),
                    'salesman_id'       => (int)$data->salesman_id,
                    'salesman_code'     => strtoupper(trim($data->kode_sales)),
                    'salesman_name'     => strtoupper(trim($data->nama_sales)),
                    'plan_visit'        => (trim($data->tanggal_plan_visit) == '') ? null : trim($data->tanggal_plan_visit),
                    'actual_visit'      => (trim($data->tanggal_visit) == '') ? null : trim($data->tanggal_visit),
                    'amount'            => (double)$data->amount_order,
                ]);
            }

            $kode_dealer_visit = '';
            $kode_salesman_visit = '';
            $data_visit_salesman_dealer = new Collection();

            foreach($data_visit as $data_sales_dealer) {
                if(strtoupper(trim($kode_dealer_visit)) != strtoupper(trim($data_sales_dealer->dealer_code))) {
                    if(strtoupper(trim($kode_salesman_visit)) != strtoupper(trim($data_sales_dealer->salesman_code))) {
                        $amount = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->sum('amount');

                        $jml_order = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->count();

                        $jml_plan = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('plan_visit')
                                    ->count();

                        $jml_actual = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('actual_visit')
                                    ->count();

                        $data_visit_salesman_dealer->push((object) [
                            'salesman_id'       => (int)$data_sales_dealer->salesman_id,
                            'salesman_code'     => strtoupper(trim($data_sales_dealer->salesman_code)),
                            'salesman_name'     => strtoupper(trim($data_sales_dealer->salesman_name)),
                            'dealer_id'         => (int)$data_sales_dealer->dealer_id,
                            'dealer_code'       => strtoupper(trim($data_sales_dealer->dealer_code)),
                            'dealer_name'       => strtoupper(trim($data_sales_dealer->dealer_name)),
                            'dealer_address'    => strtoupper(trim($data_sales_dealer->dealer_address)),
                            'order'             => (double)$amount,
                            'plan'              => (double)$jml_plan,
                            'actual'            => (double)$jml_actual,
                            'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                            'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'percentage'        => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'visit'             => $data_visit_detail
                                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                                    ->values()
                                                    ->all(),
                        ]);

                        $kode_salesman_visit = strtoupper(trim($data_sales_dealer->salesman_code));
                        $kode_dealer_visit = strtoupper(trim($data_sales_dealer->dealer_code));
                    }
                } else {
                    if(strtoupper(trim($kode_salesman_visit)) != strtoupper(trim($data_sales_dealer->salesman_code))) {
                        $amount = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->sum('amount');

                        $jml_order = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->count();

                        $jml_plan = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('plan_visit')
                                    ->count();

                        $jml_actual = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('actual_visit')
                                    ->count();

                        $data_visit_salesman_dealer->push((object) [
                            'salesman_id'       => (int)$data_sales_dealer->salesman_id,
                            'salesman_code'     => strtoupper(trim($data_sales_dealer->salesman_code)),
                            'salesman_name'     => strtoupper(trim($data_sales_dealer->salesman_name)),
                            'dealer_id'         => (int)$data_sales_dealer->dealer_id,
                            'dealer_code'       => strtoupper(trim($data_sales_dealer->dealer_code)),
                            'dealer_name'       => strtoupper(trim($data_sales_dealer->dealer_name)),
                            'dealer_address'    => strtoupper(trim($data_sales_dealer->dealer_address)),
                            'order'             => (double)$amount,
                            'plan'              => (double)$jml_plan,
                            'actual'            => (double)$jml_actual,
                            'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                            'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'percentage'        => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'visit'             => $data_visit_detail
                                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                                    ->values()
                                                    ->all(),
                        ]);
                        $kode_salesman_visit = strtoupper(trim($data_sales_dealer->salesman_code));
                    }
                }
            }

            $kode_sales_visit = '';
            $data_sales_visit = new Collection();

            foreach($data_visit_salesman_dealer as $data_sales) {
                if(strtoupper(trim($kode_sales_visit)) != strtoupper(trim($data_sales->salesman_code))) {
                    $amount = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->where('amount', '>', 0)
                                ->sum('amount');

                    $jml_order = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->where('amount', '>', 0)
                                ->count();

                    $jml_plan = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->whereNotNull('plan_visit')
                                ->count();

                    $jml_actual = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->whereNotNull('actual_visit')
                                ->count();

                    $data_sales_visit->push((object) [
                        'salesman_id'   => (int)$data_sales->salesman_id,
                        'code'          => strtoupper(trim($data_sales->salesman_code)),
                        'name'          => strtoupper(trim($data_sales->salesman_name)),
                        'plan'          => (double)$jml_plan,
                        'actual'        => (double)$jml_actual,
                        'realisasi'     => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                        'efectivitas'   => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                        'order'         => (double)$amount,
                        'detail'        => $data_visit_salesman_dealer
                                            ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                            ->values()
                                            ->all(),
                    ]);

                    $kode_sales_visit = strtoupper(trim($data_sales->salesman_code));
                }
            }

            $data_result = new Collection();

            $amount = $data_visit_detail->where('amount', '>', 0)->sum('amount');
            $jml_order = $data_visit_detail->where('amount', '>', 0)->count();
            $jml_plan = $data_visit_detail->whereNotNull('plan_visit')->count();
            $jml_actual = $data_visit_detail->whereNotNull('actual_visit')->count();

            $data_result->push((object) [
                'actual'       => (double)$jml_actual,
                'target'       => (double)$jml_plan,
                'realisasi'    => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                'efectivitas'  => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                'amount_total' => (double)$amount,
                'page'         => (int)$request->get('page'),
                'data'         => $data_sales_visit->values()->all(),
            ]);

            return ApiResponse::responseSuccess('success', $data_result->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function efectivitasManager(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'start_date' => 'required',
                'end_date'   => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Start date and end date required');
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
            $companyid = strtoupper(trim($sql->companyid));

            $koordinator_id = '';
            $koordinator_code = '';

            if(!empty($request->get('coordinator')) && trim($request->get('coordinator')) != '') {
                $data_filter_coordinator = explode(',', str_replace('[', '', str_replace(']', '', $request->get('coordinator'))));
                foreach($data_filter_coordinator as $filter_coordinator) {
                    if(trim($koordinator_id) == '') {
                        $koordinator_id = $filter_coordinator;
                    } else {
                        $koordinator_id .= ",".$filter_coordinator;
                    }
                }
            }

            if(trim($koordinator_id) != '') {
                $sql = DB::table('superspv')->lock('with (nolock)')
                        ->selectRaw("isnull(superspv.kd_spv, '') as kode_spv")
                        ->where('superspv.companyid', strtoupper(trim($companyid)))
                        ->whereRaw("superspv.id_spv in (".$koordinator_id.")")
                        ->get();

                foreach($sql as $data) {
                    if(trim($koordinator_code) == '') {
                        $koordinator_code = "'".strtoupper(trim($data->kode_spv))."'";
                    } else {
                        $koordinator_code .= ",'".strtoupper(trim($data->kode_spv))."'";
                    }
                }
            }

            $salesman_id = '';
            $salesman_code = '';

            if(!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $data_filter_salesman = explode(',', str_replace('[', '', str_replace(']', '', $request->get('salesman'))));
                foreach($data_filter_salesman as $filter_salesman) {
                    if(trim($salesman_id) == '') {
                        $salesman_id = $filter_salesman;
                    } else {
                        $salesman_id .= ",".$filter_salesman;
                    }
                }
            }

            $sql = DB::table('salesman')->lock('with (nolock)')
                    ->selectRaw("isnull(salesman.kd_sales, '') as kode_sales")
                    ->leftJoin(DB::raw('superspv with (nolock)'), function($join) {
                        $join->on('superspv.kd_spv', '=', 'salesman.spv')
                            ->on('superspv.companyid', '=', 'salesman.companyid');
                    })
                    ->where('salesman.companyid', strtoupper(trim($companyid)));

            if(trim($salesman_id) == '') {
                $sql->where('superspv.nm_spv', strtoupper(trim($user_id)));
            } else {
                $sql->whereRaw("salesman.id_sales in (".trim($salesman_id).")");
            }

            $result = $sql->get();

            foreach($result as $data) {
                if(trim($salesman_code) == '') {
                    $salesman_code = "'".strtoupper(trim($data->kode_sales))."'";
                } else {
                    $salesman_code .= ",'".strtoupper(trim($data->kode_sales))."'";
                }
            }

            if(trim($salesman_code) == '') {
                return ApiResponse::responseWarning('Supervisor atau koordinator yang anda pilih tidak memiliki salesman');
            }

            $dealer_id = '';
            $dealer_code_visit = '';

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $data_filter_dealer = explode(',', str_replace('[', '', str_replace(']', '', $request->get('dealer'))));
                foreach($data_filter_dealer as $filter_dealer) {
                    if(trim($dealer_id) == '') {
                        $dealer_id = $filter_dealer;
                    } else {
                        $dealer_id .= ",".$filter_dealer;
                    }
                }
            }

            $sql = DB::table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(visit_date.kd_dealer, '') as kode_dealer")
                    ->leftJoin(DB::raw('msdealer with (nolock)'), function($join) {
                        $join->on('msdealer.kd_dealer', '=', 'visit_date.kd_dealer')
                            ->on('msdealer.companyid', '=', 'visit_date.companyid');
                    })
                    ->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                        $join->on('salesman.kd_sales', '=', 'visit_date.kd_sales')
                            ->on('salesman.companyid', '=', 'visit_date.companyid');
                    })
                    ->whereRaw("visit_date.tanggal between '".trim($request->get('start_date'))."' and
                                    '".trim($request->get('end_date'))."'")
                    ->where('visit_date.companyid', strtoupper(trim($companyid)));

            if(trim($salesman_code) != '') {
                $sql->whereRaw("visit_date.kd_sales in (".$salesman_code.")");
            }

            if(trim($dealer_id) != '') {
                $sql->whereRaw("msdealer.id in (".$dealer_id.")");
            }

            if(trim($koordinator_code) != '') {
                $sql->whereRaw("salesman.spv in (".$koordinator_code.")");
            }

            $result = $sql->groupByRaw("visit_date.kd_dealer")
                        ->orderBy('visit_date.kd_dealer', 'asc')
                        ->paginate(10);

            if(!empty($result->data)) {
                foreach($result->data as $data) {
                    if(trim($dealer_code_visit) == '') {
                        $dealer_code_visit = "'".strtoupper(trim($data->kode_dealer))."'";
                    } else {
                        $dealer_code_visit .= ",'".strtoupper(trim($data->kode_dealer))."'";
                    }
                }
            }

            if(empty($dealer_code_visit) || trim($dealer_code_visit) == '') {
                $data = [
                    'actual'       => 0,
                    'target'       => 0,
                    'realisasi'    => 0,
                    'efectivitas'  => 0,
                    'amount_total' => 0,
                    'page'         => (int)$request->get('page'),
                    'data'         => [],
                ];

                return ApiResponse::responseSuccess('success', $data);
            }

            $sql = "select	isnull(visit.companyid, '') as companyid, isnull(superspv.id_spv, 0) as spv_id,
                            isnull(superspv.kd_spv, '') as kode_spv, isnull(superspv.nm_spv, '') as nama_spv,
                            isnull(salesman.id_sales, 0) as salesman_id,
                            isnull(dealer.kd_sales, '') as kode_sales, isnull(salesman.nm_sales, '') as nama_sales,
                            isnull(msdealer.id, 0) as dealer_id, isnull(visit.kd_dealer, '') as kode_dealer,
                            isnull(dealer.nm_dealer, '') as nama_dealer, isnull(dealer.alamat1, '') as alamat_dealer,
                            isnull(convert(varchar(10), visit.tanggal_plan_visit, 120), '') as tanggal_plan_visit,
                            isnull(convert(varchar(10), visit.tanggal_visit, 120), '') as tanggal_visit,
                            isnull(visit.id_visit, 0) as visit_id, isnull(visit.amount, 0) as amount_order,
                            isnull(visit.realisasi_prosentase, 0) as realisasi_prosentase,
                            isnull(visit.efectivitas_prosentase, 0) as efectivitas_prosentase
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_dealer, visit_date.tanggal as tanggal_plan_visit,
                                visit.id_visit, visit.tanggal as tanggal_visit, sum(isnull(pof.total, 0)) as amount,
                                iif(isnull(visit.kd_visit, '') <> '', 100, 0) as realisasi_prosentase,
                                case
                                    when isnull(visit.kd_visit, '') <> '' and sum(isnull(pof.total, 0)) > 0 then 100
                                    when isnull(visit.kd_visit, '') <> '' and sum(isnull(pof.total, 0)) <= 0 then 90
                                    when isnull(visit.kd_visit, '') = '' and sum(isnull(pof.total, 0)) > 0 then 80
                                else 0
                                end as efectivitas_prosentase
                        from
                        (
                            select	visit_date.companyid, visit_date.kd_dealer,
                                    visit_date.tanggal
                            from	visit_date with (nolock)
                                        left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                                    visit_date.companyid=salesman.companyid
                            where	visit_date.tanggal between '".trim($request->get('start_date'))."' and
                                                '".trim($request->get('end_date'))."' and
                                    visit_date.companyid='".strtoupper(trim($companyid))."'";

            if(trim($dealer_code_visit) != '') {
                $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($dealer_code_visit)).")";
            }

            if(trim($salesman_code) != '') {
                $sql .= " and visit_date.kd_sales in (".strtoupper(trim($salesman_code)).")";
            }

            if(trim($koordinator_code) != '') {
                $sql .= " and salesman.spv in (".strtoupper(trim($koordinator_code)).")";
            }

            $sql .= " )	visit_date
                                left join visit with (nolock) on visit_date.tanggal=visit.tanggal and
                                            visit_date.kd_dealer=visit.kd_dealer and
                                            visit_date.companyid=visit.companyid
                                left join pof with (nolock) on visit_date.tanggal=pof.tgl_pof and
                                            visit_date.kd_dealer=pof.kd_dealer and
                                            visit_date.companyid=pof.companyid
                        group by	visit_date.companyid, visit_date.kd_dealer, visit_date.tanggal,
                                    visit.id_visit, visit.kd_visit, visit.tanggal
                    )	visit
                            inner join msdealer with (nolock) on visit.kd_dealer=msdealer.kd_dealer and
                                        visit.companyid=msdealer.companyid
                            inner join dealer with (nolock) on visit.kd_dealer=dealer.kd_dealer and
                                        visit.companyid=dealer.companyid
                            left join salesman with (nolock) on dealer.kd_sales=salesman.kd_sales and
                                        visit.companyid=salesman.companyid
                            left join superspv with (nolock) on salesman.spv=superspv.kd_spv and
                                        visit.companyid=superspv.companyid
                    order by visit.companyid asc, visit.kd_dealer asc, visit.tanggal_plan_visit asc,
                            visit.tanggal_visit asc";

            $result = DB::select($sql);

            $data_visit = new Collection();
            $data_visit_detail = new Collection();

            foreach($result as $data) {
                $data_visit_detail->push((object) [
                    'spv_code'                  => strtoupper(trim($data->kode_spv)),
                    'dealer_code'               => strtoupper(trim($data->kode_dealer)),
                    'salesman_code'             => strtoupper(trim($data->kode_sales)),
                    'id'                        => (int)$data->visit_id,
                    'plan_visit'                => (trim($data->tanggal_plan_visit) == '') ? null : trim($data->tanggal_plan_visit),
                    'actual_visit'              => (trim($data->tanggal_visit) == '') ? null : trim($data->tanggal_visit),
                    'amount'                    => (double)$data->amount_order,
                    'realisasi_persentase'      => (float)$data->realisasi_prosentase,
                    'efectivitas_persentase'    => (float)$data->efectivitas_prosentase
                ]);

                $data_visit->push((object) [
                    'dealer_id'         => (int)$data->dealer_id,
                    'dealer_code'       => strtoupper(trim($data->kode_dealer)),
                    'dealer_name'       => strtoupper(trim($data->nama_dealer)),
                    'dealer_address'    => strtoupper(trim($data->alamat_dealer)),
                    'salesman_id'       => (int)$data->salesman_id,
                    'salesman_code'     => strtoupper(trim($data->kode_sales)),
                    'salesman_name'     => strtoupper(trim($data->nama_sales)),
                    'coordinator_id'    => (int)$data->spv_id,
                    'coordinator_code'  => strtoupper(trim($data->kode_spv)),
                    'coordinator_name'  => strtoupper(trim($data->nama_spv)),
                    'plan_visit'        => (trim($data->tanggal_plan_visit) == '') ? null : trim($data->tanggal_plan_visit),
                    'actual_visit'      => (trim($data->tanggal_visit) == '') ? null : trim($data->tanggal_visit),
                    'amount'            => (double)$data->amount_order,
                ]);
            }

            $kode_dealer_visit = '';
            $kode_salesman_visit = '';
            $data_visit_salesman_dealer = new Collection();

            foreach($data_visit as $data_sales_dealer) {
                if(strtoupper(trim($kode_dealer_visit)) != strtoupper(trim($data_sales_dealer->dealer_code))) {
                    if(strtoupper(trim($kode_salesman_visit)) != strtoupper(trim($data_sales_dealer->salesman_code))) {
                        $amount = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->sum('amount');

                        $jml_order = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->count();

                        $jml_plan = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('plan_visit')
                                    ->count();

                        $jml_actual = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('actual_visit')
                                    ->count();

                        $data_visit_salesman_dealer->push((object) [
                            'coordinator_id'    => (int)$data_sales_dealer->coordinator_id,
                            'coordinator_code'  => strtoupper(trim($data_sales_dealer->coordinator_code)),
                            'coordinator_name'  => strtoupper(trim($data_sales_dealer->coordinator_name)),
                            'salesman_id'       => (int)$data_sales_dealer->salesman_id,
                            'salesman_code'     => strtoupper(trim($data_sales_dealer->salesman_code)),
                            'salesman_name'     => strtoupper(trim($data_sales_dealer->salesman_name)),
                            'dealer_id'         => (int)$data_sales_dealer->dealer_id,
                            'dealer_code'       => strtoupper(trim($data_sales_dealer->dealer_code)),
                            'dealer_name'       => strtoupper(trim($data_sales_dealer->dealer_name)),
                            'dealer_address'    => strtoupper(trim($data_sales_dealer->dealer_address)),
                            'order'             => (double)$amount,
                            'plan'              => (double)$jml_plan,
                            'actual'            => (double)$jml_actual,
                            'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                            'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'percentage'        => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'visit'             => $data_visit_detail
                                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                                    ->values()
                                                    ->all(),
                        ]);

                        $kode_salesman_visit = strtoupper(trim($data_sales_dealer->salesman_code));
                        $kode_dealer_visit = strtoupper(trim($data_sales_dealer->dealer_code));
                    }
                } else {
                    if(strtoupper(trim($kode_salesman_visit)) != strtoupper(trim($data_sales_dealer->salesman_code))) {
                        $amount = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->sum('amount');

                        $jml_order = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->where('amount', '>', 0)
                                    ->count();

                        $jml_plan = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('plan_visit')
                                    ->count();

                        $jml_actual = $data_visit_detail
                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                    ->whereNotNull('actual_visit')
                                    ->count();

                        $data_visit_salesman_dealer->push((object) [
                            'coordinator_id'    => (int)$data_sales_dealer->coordinator_id,
                            'coordinator_code'  => strtoupper(trim($data_sales_dealer->coordinator_code)),
                            'coordinator_name'  => strtoupper(trim($data_sales_dealer->coordinator_name)),
                            'salesman_id'       => (int)$data_sales_dealer->salesman_id,
                            'salesman_code'     => strtoupper(trim($data_sales_dealer->salesman_code)),
                            'salesman_name'     => strtoupper(trim($data_sales_dealer->salesman_name)),
                            'dealer_id'         => (int)$data_sales_dealer->dealer_id,
                            'dealer_code'       => strtoupper(trim($data_sales_dealer->dealer_code)),
                            'dealer_name'       => strtoupper(trim($data_sales_dealer->dealer_name)),
                            'dealer_address'    => strtoupper(trim($data_sales_dealer->dealer_address)),
                            'order'             => (double)$amount,
                            'plan'              => (double)$jml_plan,
                            'actual'            => (double)$jml_actual,
                            'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                            'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'percentage'        => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                            'visit'             => $data_visit_detail
                                                    ->where('salesman_code', strtoupper(trim($data_sales_dealer->salesman_code)))
                                                    ->where('dealer_code', strtoupper(trim($data_sales_dealer->dealer_code)))
                                                    ->values()
                                                    ->all(),
                        ]);
                        $kode_salesman_visit = strtoupper(trim($data_sales_dealer->salesman_code));
                    }
                }
            }

            $kode_sales_visit = '';
            $data_sales_visit = new Collection();

            foreach($data_visit_salesman_dealer as $data_sales) {
                if(strtoupper(trim($kode_sales_visit)) != strtoupper(trim($data_sales->salesman_code))) {
                    $amount = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->where('amount', '>', 0)
                                ->sum('amount');

                    $jml_order = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->where('amount', '>', 0)
                                ->count();

                    $jml_plan = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->whereNotNull('plan_visit')
                                ->count();

                    $jml_actual = $data_visit_detail
                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                ->whereNotNull('actual_visit')
                                ->count();

                    $data_sales_visit->push((object) [
                        'coordinator_id'    => (int)$data_sales->coordinator_id,
                        'coordinator_code'  => strtoupper(trim($data_sales->coordinator_code)),
                        'coordinator_name'  => strtoupper(trim($data_sales->coordinator_name)),
                        'salesman_id'       => (int)$data_sales->salesman_id,
                        'code'              => strtoupper(trim($data_sales->salesman_code)),
                        'name'              => strtoupper(trim($data_sales->salesman_name)),
                        'plan'              => (double)$jml_plan,
                        'actual'            => (double)$jml_actual,
                        'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                        'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                        'order'             => (double)$amount,
                        'detail'            => $data_visit_salesman_dealer
                                                ->where('salesman_code', strtoupper(trim($data_sales->salesman_code)))
                                                ->values()
                                                ->all(),
                    ]);

                    $kode_sales_visit = strtoupper(trim($data_sales->salesman_code));
                }
            }

            $kode_spv_visit = '';
            $data_coordinator_visit = new Collection();

            foreach($data_sales_visit as $data_spv) {
                if(strtoupper(trim($kode_spv_visit)) != strtoupper(trim($data_spv->coordinator_code))) {
                    $amount = $data_visit_detail
                                ->where('coordinator_code', strtoupper(trim($data_spv->coordinator_code)))
                                ->where('amount', '>', 0)
                                ->sum('amount');

                    $jml_order = $data_visit_detail
                                ->where('coordinator_code', strtoupper(trim($data_spv->coordinator_code)))
                                ->where('amount', '>', 0)
                                ->count();

                    $jml_plan = $data_visit_detail
                                ->where('coordinator_code', strtoupper(trim($data_spv->coordinator_code)))
                                ->whereNotNull('plan_visit')
                                ->count();

                    $jml_actual = $data_visit_detail
                                ->where('coordinator_code', strtoupper(trim($data_spv->coordinator_code)))
                                ->whereNotNull('actual_visit')
                                ->count();

                    $data_coordinator_visit->push((object) [
                        'coordinator_id'    => (int)$data_spv->coordinator_id,
                        'coordinator_code'  => strtoupper(trim($data_spv->coordinator_code)),
                        'coordinator_name'  => strtoupper(trim($data_spv->coordinator_name)),
                        'plan'              => (double)$jml_plan,
                        'actual'            => (double)$jml_actual,
                        'realisasi'         => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                        'efectivitas'       => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                        'order'             => (double)$amount,
                        'sales'             => $data_sales_visit
                                                ->where('coordinator_code', strtoupper(trim($data_spv->coordinator_code)))
                                                ->values()
                                                ->all(),
                    ]);

                    $kode_spv_visit = strtoupper(trim($data_spv->coordinator_code));
                }
            }

            $data_result = new Collection();

            $amount = $data_visit_detail->where('amount', '>', 0)->sum('amount');
            $jml_order = $data_visit_detail->where('amount', '>', 0)->count();
            $jml_plan = $data_visit_detail->whereNotNull('plan_visit')->count();
            $jml_actual = $data_visit_detail->whereNotNull('actual_visit')->count();

            $data_result->push((object) [
                'actual'       => (double)$jml_actual,
                'target'       => (double)$jml_plan,
                'realisasi'    => ((double)$jml_plan <= 0) ? 0 : ((double)$jml_actual / (double)$jml_plan) * 100,
                'efectivitas'  => ((double)$jml_actual <= 0) ? 0 : ((double)$jml_order / (double)$jml_actual) * 100,
                'amount_total' => (double)$amount,
                'page'         => (int)$request->get('page'),
                'data'         => $data_coordinator_visit->values()->all(),
            ]);

            return ApiResponse::responseSuccess('success', $data_result->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\ApiHelpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RealisasiVisitController extends Controller
{
    public function RealisasiVisitSalesman(Request $request) {
        $validate = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date'   => 'required'
        ]);

        if($validate->fails()) {
            return ApiHelpers::ApiResponse(0, "Dealer, start date, and end date required", null);
        }

        $token = $request->header('Authorization');
        $formatToken = explode(" ", $token);
        $access_token = trim($formatToken[1]);

        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        $sql = "select	user_api_sessions.user_id, users.jabatan, users.role_id, role.deskripsi_role, user_api_sessions.companyid
                from
                (
                    select	user_api_sessions.user_id, user_api_sessions.companyid
                    from    user_api_sessions with (nolock)
                    where	session_id=:session_id
                )	user_api_sessions
                        inner join users with (nolock) on user_api_sessions.user_id=users.user_id and users.companyid=user_api_sessions.companyid
                        left join role with (nolock) on users.role_id=role.role_id";

        $sql = collect(DB::select($sql, [':session_id' => $access_token ]))->first();
        $user_id = trim($sql->user_id);
        $role_id = trim($sql->role_id);
        $companyid = trim($sql->companyid);

        $sql = "select	isnull(msdealer.id, 0) as 'dealer_id', isnull(msdealer.kd_dealer, '') as 'kode_dealer', isnull(dealer.nm_dealer, '') as 'nama_dealer',
                        isnull(dealer.alamat1, '') as 'alamat_dealer', isnull(visit.id_visit, 0) as 'id_visit', isnull(visit.kd_visit, '') as 'kode_visit',
                        isnull(visit.id_sales, 0) as 'salesman_id', isnull(visit.kd_sales, '') as 'kode_sales', isnull(visit.nm_sales, '') as 'nama_sales',
                        isnull(convert(varchar(10), visit.tanggal_planning, 120), '') as 'tanggal_plan', isnull(visit.tanggal_checkin, '') as 'tanggal_visit',
                        isnull(visit.realisasi_visit, 0) as 'realisasi_visit', isnull(visit.efektivitas_visit, 0) as 'efektivitas_visit'
                from
                (
                    select	msdealer.companyid, msdealer.id, msdealer.kd_dealer
                    from	msdealer with (nolock)
                    where	msdealer.companyid='".$companyid."'";

        if(!empty($request->get('dealer'))) {
            $dealer_id = str_replace('[', '', str_replace(']', '', $request->get('dealer')));
            $sql .= " and msdealer.id in (".$dealer_id.")";
        }

        $sql .= " )	msdealer
                inner join dealer with (nolock) on msdealer.companyid=dealer.companyid and msdealer.kd_dealer=dealer.kd_dealer
                inner join
                (
                    select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales, salesman.nm_sales, salesman.id_sales,
                            visit_date.tanggal as 'tanggal_planning', min(visit.id_visit) as 'id_visit', isnull(convert(varchar(10), min(visit.check_in), 120), '') as 'tanggal_checkin',
                            iif(isnull(min(visit.check_in), '')='', 0, 100) as 'realisasi_visit',
                            iif(isnull(min(visit.check_in), '')='', 0, iif(isnull(pof.no_pof, '')='', 50, 100)) as 'efektivitas_visit'
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales, visit_date.tanggal
                        from	visit_date with (nolock)
                        where	visit_date.companyid='".$companyid."' and
                                visit_date.tanggal between '".$start_date."' and '".$end_date."'";

        if($role_id == "MD_H3_SM") {
            $sql .= " and visit_date.kd_sales='".$user_id."'";
        }

        $sql .= " )	visit_date
                            left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and visit_date.companyid=salesman.companyid
                            left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and visit_date.companyid=visit.companyid
                            left join pof with (nolock) on visit_date.kd_dealer=pof.kd_dealer and visit_date.kd_sales=pof.kd_sales and visit_date.tanggal=pof.tgl_pof and
                                            visit_date.companyid=pof.companyid
                    group by    visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales, salesman.nm_sales, salesman.id_sales,
                                visit_date.tanggal, pof.no_pof
                )	visit on msdealer.companyid=visit.companyid and msdealer.kd_dealer=visit.kd_dealer
                order by msdealer.companyid, msdealer.kd_dealer, visit.kd_sales";

        $result_visit_dealer = DB::select($sql);
        $data_detail_visit = [];
        $data_visit = new Collection();

        foreach($result_visit_dealer as $result) {
            $data_detail_visit[] = [
                'id'                        => (int)$result->id_visit,
                'dealer_code'               => trim($result->kode_dealer),
                'salesman_code'             => trim($result->kode_sales),
                'plan_visit'                => trim($result->tanggal_plan),
                'actual_visit'              => trim($result->tanggal_visit),
                'realisasi_persentase'      => (double)$result->realisasi_visit,
                'efektivitas_persentase'    => (double)$result->efektivitas_visit
            ];

            $data_visit->push((object) [
                'dealer_id'                 => (int)$result->dealer_id,
                'dealer_code'               => trim($result->kode_dealer),
                'dealer_name'               => trim($result->nama_dealer),
                'dealer_address'            => trim($result->alamat_dealer),
                'salesman_id'               => (int)$result->salesman_id,
                'salesman_code'             => trim($result->kode_sales),
                'salesman_name'             => trim($result->nama_sales),
                'id_visit'                  => (int)$result->id_visit,
                'visit_code'                => $result->id_visit,
                'plan_visit'                => trim($result->tanggal_plan),
                'actual_visit'              => trim($result->tanggal_visit),
                'realisasi_persentase'      => (double)$result->realisasi_visit,
                'efektivitas_persentase'    => (double)$result->efektivitas_visit,
            ]);
        }

        $kode_dealer = "";
        $kode_sales = "";
        $data_visit_persalesman = [];

        foreach($data_visit as $collection) {
            if ($kode_dealer != $collection->dealer_code) {
                $total_plan = collect($data_detail_visit)->where('dealer_code', $collection->dealer_code)->where('salesman_code', $collection->salesman_code)->where('plan_visit', '<>', '')->count();
                $total_actual = collect($data_detail_visit)->where('dealer_code', $collection->dealer_code)->where('salesman_code', $collection->salesman_code)->where('actual_visit', '<>', '')->count();
                $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                $data_visit_persalesman[] = [
                    'dealer_id'         => (int)$collection->dealer_id,
                    'dealer_code'       => trim($collection->dealer_code),
                    'dealer_name'       => trim($collection->dealer_name),
                    'dealer_address'    => trim($collection->dealer_address),
                    'salesman_id'       => (int)$collection->salesman_id,
                    'salesman_code'     => trim($collection->salesman_code),
                    'salesman_name'     => trim($collection->salesman_name),
                    'plan'              => $total_plan,
                    'actual'            => $total_actual,
                    'realisasi'         => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'percentage'        => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'visit'             => collect($data_detail_visit)
                                            ->where('dealer_code', $collection->dealer_code)
                                            ->where('salesman_code', $collection->salesman_code)
                                            ->values()
                                            ->all()
                ];
                $kode_dealer = $collection->dealer_code;
                $kode_sales = $collection->salesman_code;
            } else {
                if($kode_sales != $collection->salesman_code) {
                    $total_plan = collect($data_detail_visit)->where('dealer_code', $collection->dealer_code)->where('salesman_code', $collection->salesman_code)->where('plan_visit', '<>', '')->count();
                    $total_actual = collect($data_detail_visit)->where('dealer_code', $collection->dealer_code)->where('salesman_code', $collection->salesman_code)->where('actual_visit', '<>', '')->count();
                    $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                    $data_visit_persalesman[] = [
                        'dealer_id'         => (int)$collection->dealer_id,
                        'dealer_code'       => trim($collection->dealer_code),
                        'dealer_name'       => trim($collection->dealer_name),
                        'dealer_address'    => trim($collection->dealer_address),
                        'salesman_id'       => (int)$collection->salesman_id,
                        'salesman_code'     => trim($collection->salesman_code),
                        'salesman_name'     => trim($collection->salesman_name),
                        'plan'              => $total_plan,
                        'actual'            => $total_actual,
                        'realisasi'         => (float)number_format((float)$total_realisasi, 2, '.', ''),
                        'percentage'        => (float)number_format((float)$total_realisasi, 2, '.', ''),
                        'visit'             => collect($data_detail_visit)
                                                ->where('dealer_code', $collection->dealer_code)
                                                ->where('salesman_code', $collection->salesman_code)
                                                ->values()
                                                ->all()
                    ];
                    $kode_sales = $collection->salesman_code;
                }
            }
        }

        $kode_dealer = "";
        $data_visit_perdealer = [];
        foreach($data_visit as $collection) {
            if($kode_dealer != $collection->dealer_code) {
                $total_plan = collect($data_detail_visit)->where('dealer_code', $collection->dealer_code)->where('plan_visit', '<>', '')->count();
                $total_actual = collect($data_detail_visit)->where('dealer_code', $collection->dealer_code)->where('actual_visit', '<>', '')->count();
                $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                $data_visit_perdealer[] = [
                    'dealer_id'     => (int)$collection->dealer_id,
                    'code'          => trim($collection->dealer_code),
                    'name'          => trim($collection->dealer_name),
                    'address'       => trim($collection->dealer_address),
                    'plan'          => $total_plan,
                    'actual'        => $total_actual,
                    'realisasi'     => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'detail'        => collect($data_visit_persalesman)->where('dealer_code', $collection->dealer_code)->values()->all()
                ];
                $kode_dealer = $collection->dealer_code;
            }
        }

        $grand_total_plan = collect($data_detail_visit)->where('plan_visit', '<>', '')->count();
        $grand_total_actual = collect($data_detail_visit)->where('actual_visit', '<>', '')->count();
        $grand_total_realisasi = ($grand_total_plan == 0 || empty($grand_total_plan)) ? 0 : round(($grand_total_actual / $grand_total_plan) * 100, 0);


        if(empty($request->get('page'))) {
            $data_visit_total[] = [
                'actual'    => $grand_total_actual,
                'target'    => $grand_total_plan,
                'realisasi' => $grand_total_realisasi,
                'page'      => $this->paginate($data_visit_perdealer)->currentPage(),
                'data'      => $data_visit_perdealer
            ];
        } else {
            if($request->get('page') == 1) {
                $data_visit_total[] = [
                    'actual'    => $grand_total_actual,
                    'target'    => $grand_total_plan,
                    'realisasi' => $grand_total_realisasi,
                    'page'      => $this->paginate($data_visit_perdealer)->currentPage(),
                    'data'      => $data_visit_perdealer
                ];
            } else {
                $data_visit_total[] = [
                    'actual'    => $grand_total_actual,
                    'target'    => $grand_total_plan,
                    'realisasi' => $grand_total_realisasi,
                    'page'      => $this->paginate($data_visit_perdealer)->currentPage(),
                    'data'      => []
                ];
            }
        }
        return ApiHelpers::ApiResponse(1, "success", collect($data_visit_total)->first());
    }

    public function RealisasiVisitCoordinator(Request $request) {
        $validate = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date'   => 'required'
        ]);

        if($validate->fails()) {
            return ApiHelpers::ApiResponse(0, "Dealer, start date, and end date required", null);
        }

        $token = $request->header('Authorization');
        $formatToken = explode(" ", $token);
        $access_token = trim($formatToken[1]);

        $sql = "select	user_api_sessions.user_id, users.jabatan, users.role_id, role.deskripsi_role, user_api_sessions.companyid
                from
                (
                    select	user_api_sessions.user_id, user_api_sessions.companyid
                    from    user_api_sessions with (nolock)
                    where	session_id=:session_id
                )	user_api_sessions
                        inner join users with (nolock) on user_api_sessions.user_id=users.user_id and users.companyid=user_api_sessions.companyid
                        left join role with (nolock) on users.role_id=role.role_id";

        $sql = collect(DB::select($sql, [':session_id' => $access_token ]))->first();
        $companyid = trim($sql->companyid);

        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        $sql = "select	isnull(salesman.companyid, '') as 'CompanyID', isnull(salesman.id_sales, 0) as 'salesman_id', isnull(salesman.kd_sales, '') as 'salesman_code',
                        isnull(salesman.nm_sales, '') as 'salesman_name', isnull(msdealer.id, 0) as 'dealer_id', isnull(dealer.kd_dealer, '') as 'dealer_code',
                        isnull(dealer.nm_dealer, '') as 'dealer_name', isnull(dealer.alamat1, '') as 'dealer_address', isnull(visit.id_visit, 0) as 'visit_id',
                        isnull(visit.kd_visit, '') as 'visit_code', isnull(visit.tanggal_planning, '') as 'plan_visit', isnull(visit.tanggal_checkin, '') as 'actual_visit',
                        isnull(visit.realisasi_visit, 0) as 'realisasi_visit', isnull(visit.efektivitas_visit, 0) as 'efektivitas_visit'
                from
                (
                    select	salesman.companyid, salesman.id_sales, salesman.kd_sales, salesman.nm_sales
                    from	salesman with (nolock)
                    where	salesman.companyid='".$companyid."'";

        if(!empty($request->get('salesman'))) {
            $salesman = str_replace('[', '', str_replace(']', '', $request->get('salesman')));
            $sql .= " and salesman.id_sales in (".$salesman.")";
        }

        $sql .= " )	salesman
                inner join dealer with (nolock) on salesman.companyid=dealer.companyid and salesman.kd_sales=dealer.kd_sales
                inner join msdealer with (nolock) on salesman.companyid=msdealer.companyid and dealer.kd_dealer=msdealer.kd_dealer";

        if(!empty($request->get('dealer'))) {
            $dealer = str_replace('[', '', str_replace(']', '', $request->get('dealer')));
            $sql .= " and msdealer.id in (".$dealer.")";
        }

        $sql .= " inner join
                (
                    select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales,
                            isnull(convert(varchar(10), visit_date.tanggal, 120), '') as 'tanggal_planning',
                            min(visit.id_visit) as 'id_visit', isnull(convert(varchar(10), min(visit.check_in), 120), '') as 'tanggal_checkin',
                            iif(isnull(min(visit.check_in), '')='', 0, 100) as 'realisasi_visit',
                            iif(isnull(min(visit.check_in), '')='', 0, iif(isnull(pof.no_pof, '')='', 50, 100)) as 'efektivitas_visit'
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales, visit_date.tanggal
                        from	visit_date with (nolock)
                        where	visit_date.companyid='".$companyid."' and
                                visit_date.tanggal between '".$start_date."' and '".$end_date."'
                    )	visit_date
                            left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and visit_date.companyid=visit.companyid
                            left join pof with (nolock) on visit_date.kd_dealer=pof.kd_dealer and visit_date.kd_sales=pof.kd_sales and visit_date.tanggal=pof.tgl_pof and
                                            visit_date.companyid=pof.companyid
                    group by visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales, visit_date.tanggal, pof.no_pof
                )	visit on salesman.companyid=visit.companyid and salesman.kd_sales=visit.kd_sales and msdealer.kd_dealer=visit.kd_dealer
                order by salesman.companyid, salesman.kd_sales, msdealer.kd_dealer";

        $result_visit_dealer = DB::select($sql);
        $data_detail_visit = [];
        $data_visit = new Collection();

        foreach($result_visit_dealer as $result) {
            $data_detail_visit[] = [
                'id'                        => (int)$result->visit_id,
                'dealer_code'               => trim($result->dealer_code),
                'salesman_code'             => trim($result->salesman_code),
                'plan_visit'                => trim($result->plan_visit),
                'actual_visit'              => trim($result->actual_visit),
                'realisasi_persentase'      => (double)$result->realisasi_visit,
                'efektivitas_persentase'    => (double)$result->efektivitas_visit
            ];

            $data_visit->push((object) [
                'dealer_id'                 => (int)$result->dealer_id,
                'dealer_code'               => trim($result->dealer_code),
                'dealer_name'               => trim($result->dealer_name),
                'dealer_address'            => trim($result->dealer_address),
                'salesman_id'               => (int)$result->salesman_id,
                'salesman_code'             => trim($result->salesman_code),
                'salesman_name'             => trim($result->salesman_name),
                'id_visit'                  => (int)$result->visit_id,
                'visit_code'                => trim($result->visit_code),
                'plan_visit'                => trim($result->plan_visit),
                'actual_visit'              => trim($result->actual_visit),
                'realisasi_persentase'      => (double)$result->realisasi_visit,
                'efektivitas_persentase'    => (double)$result->efektivitas_visit,
            ]);
        }

        $kode_dealer = "";
        $data_visit_perdealer = [];
        foreach($data_visit as $collection) {
            if($kode_dealer != $collection->dealer_code) {
                $total_plan = collect($data_detail_visit)->where('salesman_code', $collection->salesman_code)->where('dealer_code', $collection->dealer_code)->where('plan_visit', '<>', '')->count();
                $total_actual = collect($data_detail_visit)->where('salesman_code', $collection->salesman_code)->where('dealer_code', $collection->dealer_code)->where('actual_visit', '<>', '')->count();
                $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                $data_visit_perdealer[] = [
                    'salesman_id'       => (int)$collection->salesman_id,
                    'salesman_code'     => $collection->salesman_code,
                    'salesman_name'     => trim($collection->salesman_name),
                    'dealer_id'         => (int)$collection->dealer_id,
                    'dealer_code'       => trim($collection->dealer_code),
                    'dealer_name'       => trim($collection->dealer_name),
                    'dealer_address'    => trim($collection->dealer_address),
                    'plan'              => $total_plan,
                    'actual'            => $total_actual,
                    'realisasi'         => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'percentage'        => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'visit'             => collect($data_detail_visit)->where('dealer_code', $collection->dealer_code)->where('salesman_code', $collection->salesman_code)->values()->all()
                ];
                $kode_dealer = $collection->dealer_code;
            }
        }

        $kode_sales = "";
        $data_visit_persales = [];
        foreach($data_visit as $collection) {
            if($kode_sales != $collection->salesman_code) {
                $total_plan = collect($data_detail_visit)->where('salesman_code', $collection->salesman_code)->where('plan_visit', '<>', '')->count();
                $total_actual = collect($data_detail_visit)->where('salesman_code', $collection->salesman_code)->where('actual_visit', '<>', '')->count();
                $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                $data_visit_persales[] = [
                    'salesman_id'       => (int)$collection->salesman_id,
                    'salesman_code'     => $collection->salesman_code,
                    'name'              => trim($collection->salesman_name),
                    'plan'              => $total_plan,
                    'actual'            => $total_actual,
                    'realisasi'         => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'detail'            => collect($data_visit_perdealer)->where('salesman_code', $collection->salesman_code)->values()->all()
                ];
                $kode_sales = $collection->salesman_code;
            }
        }

        $grand_total_plan = collect($data_detail_visit)->where('plan_visit', '<>', '')->count();
        $grand_total_actual = collect($data_detail_visit)->where('actual_visit', '<>', '')->count();
        $grand_total_realisasi = ($grand_total_plan == 0 || empty($grand_total_plan)) ? 0 : round(($grand_total_actual / $grand_total_plan) * 100, 0);


        if(empty($request->get('page'))) {
            $data_visit_total[] = [
                'actual'    => $grand_total_actual,
                'target'    => $grand_total_plan,
                'realisasi' => $grand_total_realisasi,
                'page'      => $this->paginate($data_visit_persales)->currentPage(),
                'data'      => $data_visit_persales
            ];
        } else {
            if($request->get('page') == 1) {
                $data_visit_total[] = [
                    'actual'    => $grand_total_actual,
                    'target'    => $grand_total_plan,
                    'realisasi' => $grand_total_realisasi,
                    'page'      => $this->paginate($data_visit_persales)->currentPage(),
                    'data'      => $data_visit_persales
                ];
            } else {
                $data_visit_total[] = [
                    'actual'    => $grand_total_actual,
                    'target'    => $grand_total_plan,
                    'realisasi' => $grand_total_realisasi,
                    'page'      => $this->paginate($data_visit_persales)->currentPage(),
                    'data'      => []
                ];
            }
        }
        return ApiHelpers::ApiResponse(1, "success", collect($data_visit_total)->first());
    }

    public function realisasiVisitManager(Request $request) {
        $validate = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date'   => 'required'
        ]);

        if($validate->fails()) {
            return ApiHelpers::ApiResponse(0, "Dealer, start date, and end date required", null);
        }

        $token = $request->header('Authorization');
        $formatToken = explode(" ", $token);
        $access_token = trim($formatToken[1]);

        $sql = "select	user_api_sessions.user_id, users.jabatan, users.role_id, role.deskripsi_role, user_api_sessions.companyid
                from
                (
                    select	user_api_sessions.user_id, user_api_sessions.companyid
                    from    user_api_sessions with (nolock)
                    where	session_id=:session_id
                )	user_api_sessions
                        inner join users with (nolock) on user_api_sessions.user_id=users.user_id and users.companyid=user_api_sessions.companyid
                        left join role with (nolock) on users.role_id=role.role_id";

        $sql = collect(DB::select($sql, [':session_id' => $access_token ]))->first();
        $companyid = trim($sql->companyid);

        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        $sql = "select	isnull(supervisor.companyid, '') as 'CompanyID', isnull(supervisor.id_spv, 0) as 'supervisor_id',
                        isnull(supervisor.kd_spv, '') as 'supervisor_code',  isnull(supervisor.nm_spv, '') as 'supervisor_name',
                        isnull(salesman.id_sales, 0) as 'salesman_id',
                        isnull(salesman.kd_sales, '') as 'salesman_code', isnull(salesman.nm_sales, '') as 'salesman_name',
                        isnull(msdealer.id, 0) as 'dealer_id', isnull(dealer.kd_dealer, '') as 'dealer_code',
                        isnull(dealer.nm_dealer, '') as 'dealer_name', isnull(dealer.alamat1, '') as 'dealer_address',
                        isnull(visit.tanggal_planning, '') as 'plan_visit', isnull(visit.id_visit, 0) as 'visit_id',
                        isnull(visit.kd_visit, '') as 'visit_code',
                        isnull(visit.tanggal_checkin, '') as 'actual_visit', isnull(visit.realisasi_visit, 0) as 'realisasi_visit',
                        isnull(visit.efektivitas_visit, 0) as 'efektivitas_visit'
                from
                (
                    select	superspv.companyid, superspv.id_spv, superspv.kd_spv, superspv.nm_spv
                    from	superspv with (nolock)
                    where	superspv.companyid='".$companyid."'";

        if(!empty($request->get('coordinator'))) {
            $coordinator = str_replace('[', '', str_replace(']', '', $request->get('coordinator')));
            $sql .= " and superspv.id_spv in (".$coordinator.")";
        }

        $sql .= " )	supervisor
                inner join
                (
                    select	salesman.companyid, salesman.id_sales, salesman.kd_sales, salesman.nm_sales,
                            salesman.spv
                    from	salesman with (nolock)
                    where	salesman.companyid='".$companyid."'";

        if(!empty($request->get('salesman'))) {
            $salesman = str_replace('[', '', str_replace(']', '', $request->get('salesman')));
            $sql .= " and salesman.id_sales in (".$salesman.")";
        }

        $sql .= " )	salesman on supervisor.kd_spv=salesman.spv and supervisor.companyid=salesman.companyid
                inner join dealer with (nolock) on salesman.kd_sales=dealer.kd_sales and supervisor.companyid=dealer.companyid
                inner join msdealer with (nolock) on dealer.kd_dealer=msdealer.kd_dealer and supervisor.companyid=msdealer.companyid";

        if(!empty($request->get('dealer'))) {
            $dealer = str_replace('[', '', str_replace(']', '', $request->get('dealer')));
            $sql .= " and msdealer.id in (".$dealer.")";
        }

        $sql .= " inner join
                (
                    select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales,
                            isnull(convert(varchar(10), visit_date.tanggal, 120), '') as 'tanggal_planning',
                            min(visit.id_visit) as 'id_visit', isnull(convert(varchar(10), min(visit.check_in), 120), '') as 'tanggal_checkin',
                            iif(isnull(min(visit.check_in), '')='', 0, 100) as 'realisasi_visit',
                            iif(isnull(min(visit.check_in), '')='', 0, iif(isnull(max(pof.no_pof), '')='', 50, 100)) as 'efektivitas_visit'
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales, visit_date.tanggal
                        from	visit_date with (nolock)
                        where	visit_date.companyid='".$companyid."' and
                                visit_date.tanggal between '".$start_date."' and '".$end_date."'
                    )	visit_date
                            left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and visit_date.companyid=visit.companyid
                            left join pof with (nolock) on visit_date.kd_dealer=pof.kd_dealer and visit_date.kd_sales=pof.kd_sales and visit_date.tanggal=pof.tgl_pof and
                                            visit_date.companyid=pof.companyid
                    group by visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, visit_date.kd_sales, visit_date.tanggal
                )	visit on salesman.companyid=visit.companyid and salesman.kd_sales=visit.kd_sales and msdealer.kd_dealer=visit.kd_dealer
                order by supervisor.companyid asc, supervisor.kd_spv asc, salesman.kd_sales asc, dealer.kd_dealer asc, visit.tanggal_planning asc";

        $result_visit_dealer = DB::select($sql);
        $data_detail_visit = [];
        $data_visit = new Collection();

        foreach($result_visit_dealer as $result) {
            $data_detail_visit[] = [
                'id'                        => (int)$result->visit_id,
                'coordinator_code'          => trim($result->supervisor_code),
                'salesman_code'             => trim($result->salesman_code),
                'dealer_code'               => trim($result->dealer_code),
                'plan_visit'                => trim($result->plan_visit),
                'actual_visit'              => trim($result->actual_visit),
                'realisasi_persentase'      => (double)$result->realisasi_visit,
                'efectivitas_persentase'    => (double)$result->efektivitas_visit
            ];

            $data_visit->push((object) [
                'coordinator_id'            => (int)$result->supervisor_id,
                'coordinator_code'          => trim($result->supervisor_code),
                'coordinator_name'          => trim($result->supervisor_name),
                'salesman_id'               => (int)$result->salesman_id,
                'salesman_code'             => trim($result->salesman_code),
                'salesman_name'             => trim($result->salesman_name),
                'dealer_id'                 => (int)$result->dealer_id,
                'dealer_code'               => trim($result->dealer_code),
                'dealer_name'               => trim($result->dealer_name),
                'dealer_address'            => trim($result->dealer_address),
                'id_visit'                  => (int)$result->visit_id,
                'visit_code'                => trim($result->visit_code),
                'plan_visit'                => trim($result->plan_visit),
                'actual_visit'              => trim($result->actual_visit),
                'realisasi_persentase'      => (double)$result->realisasi_visit,
                'efektivitas_persentase'    => (double)$result->efektivitas_visit,
            ]);
        }

        $kode_dealer = "";
        $data_visit_perdealer = [];
        $total_plan = 0;
        $total_actual = 0;
        $total_realisasi = 0;
        foreach($data_visit as $collection) {
            if($kode_dealer != $collection->dealer_code) {
                $total_plan = collect($data_detail_visit)->where('coordinator_code', $collection->coordinator_code)->where('salesman_code', $collection->salesman_code)->where('dealer_code', $collection->dealer_code)->where('plan_visit', '<>', '')->count();
                $total_actual = collect($data_detail_visit)->where('coordinator_code', $collection->coordinator_code)->where('salesman_code', $collection->salesman_code)->where('dealer_code', $collection->dealer_code)->where('actual_visit', '<>', '')->count();
                $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                $data_visit_perdealer[] = [
                    'coordinator_code'  => trim($collection->coordinator_code),
                    'salesman_id'       => (int)$collection->salesman_id,
                    'salesman_code'     => $collection->salesman_code,
                    'salesman_name'     => trim($collection->salesman_name),
                    'dealer_id'         => (int)$collection->dealer_id,
                    'dealer_code'       => trim($collection->dealer_code),
                    'dealer_name'       => trim($collection->dealer_name),
                    'dealer_address'    => trim($collection->dealer_address),
                    'plan'              => $total_plan,
                    'actual'            => $total_actual,
                    'realisasi'         => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'percentage'        => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'visit'             => collect($data_detail_visit)->where('coordinator_code', $collection->coordinator_code)->where('salesman_code', $collection->salesman_code)->where('dealer_code', $collection->dealer_code)->values()->all()
                ];
                $kode_dealer = $collection->dealer_code;
            }
        }

        $kode_sales = "";
        $data_visit_persales = [];
        $total_plan = 0;
        $total_actual = 0;
        $total_realisasi = 0;
        foreach($data_visit as $collection) {
            if($kode_sales != $collection->salesman_code) {
                $total_plan = collect($data_detail_visit)->where('coordinator_code', $collection->coordinator_code)->where('salesman_code', $collection->salesman_code)->where('plan_visit', '<>', '')->count();
                $total_actual = collect($data_detail_visit)->where('coordinator_code', $collection->coordinator_code)->where('salesman_code', $collection->salesman_code)->where('actual_visit', '<>', '')->count();
                $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                $data_visit_persales[] = [
                    'coordinator_code'  => trim($collection->coordinator_code),
                    'salesman_id'       => (int)$collection->salesman_id,
                    'salesman_code'     => $collection->salesman_code,
                    'name'              => trim($collection->salesman_name),
                    'plan'              => $total_plan,
                    'actual'            => $total_actual,
                    'realisasi'         => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'detail'            => collect($data_visit_perdealer)->where('coordinator_code', $collection->coordinator_code)->where('salesman_code', $collection->salesman_code)->values()->all()
                ];
                $kode_sales = $collection->salesman_code;
            }
        }

        $kode_koordinator = "";
        $data_visit_perkoordinator = [];
        $total_plan = 0;
        $total_actual = 0;
        $total_realisasi = 0;
        foreach($data_visit as $collection) {
            if($kode_koordinator != $collection->coordinator_code) {
                $total_plan = collect($data_detail_visit)->where('coordinator_code', $collection->coordinator_code)->where('plan_visit', '<>', '')->count();
                $total_actual = collect($data_detail_visit)->where('coordinator_code', $collection->coordinator_code)->where('actual_visit', '<>', '')->count();
                $total_realisasi = ($total_plan == 0 || empty($total_plan)) ? 0 : round(($total_actual / $total_plan) * 100, 0);

                $data_visit_perkoordinator[] = [
                    'coordinator_id'    => (int)$collection->coordinator_id,
                    'coordinator_code'  => trim($collection->coordinator_code),
                    'name'              => trim($collection->coordinator_name),
                    'plan'              => $total_plan,
                    'actual'            => $total_actual,
                    'realisasi'         => (float)number_format((float)$total_realisasi, 2, '.', ''),
                    'sales'             => collect($data_visit_persales)->where('coordinator_code', $collection->coordinator_code)->values()->all()
                ];
                $kode_koordinator = $collection->coordinator_code;
            }
        }

        $grand_total_plan = collect($data_detail_visit)->where('plan_visit', '<>', '')->count();
        $grand_total_actual = collect($data_detail_visit)->where('actual_visit', '<>', '')->count();
        $grand_total_realisasi = ($grand_total_plan == 0 || empty($grand_total_plan)) ? 0 : round(($grand_total_actual / $grand_total_plan) * 100, 0);

        if(empty($request->get('page'))) {
            $data_visit_total[] = [
                'actual'    => $grand_total_actual,
                'target'    => $grand_total_plan,
                'realisasi' => $grand_total_realisasi,
                'page'      => $this->paginate($data_visit_perkoordinator)->currentPage(),
                'data'      => $data_visit_perkoordinator
            ];
        } else {
            if($request->get('page') == 1) {
                $data_visit_total[] = [
                    'actual'    => $grand_total_actual,
                    'target'    => $grand_total_plan,
                    'realisasi' => $grand_total_realisasi,
                    'page'      => $this->paginate($data_visit_perkoordinator)->currentPage(),
                    'data'      => $data_visit_perkoordinator
                ];
            } else {
                $data_visit_total[] = [
                    'actual'    => $grand_total_actual,
                    'target'    => $grand_total_plan,
                    'realisasi' => $grand_total_realisasi,
                    'page'      => $this->paginate($data_visit_perkoordinator)->currentPage(),
                    'data'      => []
                ];
            }
        }
        return ApiHelpers::ApiResponse(1, "success", collect($data_visit_total)->first());
    }

    public function paginate($items, $perPage = 2, $page = null, $options = []) {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}

<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;

class RealisasiVisitController extends Controller {

    public function realisasiVisitDetail(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'start_date'    => 'required',
                'end_date'      => 'required',
                'code'          => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi, tanggal awal, tanggal akhir, dan dealer harus terisi');
            }

            $sql = "select	isnull(dealer.companyid, '') as companyid, isnull(dealer.kd_dealer, '') as kode_dealer,
                            isnull(dealer.nm_dealer, '') as nama_dealer, isnull(dealer.alamat1, '') as alamat_dealer,
                            isnull(dealer.kabupaten, '') as kabupaten, isnull(visit.actual, 0) as actual,
                            isnull(visit.target, 0) as target, isnull(visit.realisasi, 0) as realisasi,
                            isnull(visit.amount_order, 0) as amount_order, isnull(visit.efectivitas, 0) as efectivitas
                    from
                    (
                        select	dealer.companyid, dealer.kd_dealer, dealer.nm_dealer,
                                dealer.alamat1, dealer.kabupaten
                        from	dealer with (nolock)
                        where	dealer.kd_dealer='".strtoupper(trim($request->get('code')))."' and
                                dealer.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                    )	dealer
                    left join
                    (
                        select	visit.companyid, visit.kd_dealer,
                                sum(isnull(visit.jml_realisasi, 0)) as actual,
                                sum(isnull(visit.target, 0)) as target,
                                iif(sum(isnull(visit.jml_realisasi, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.akurasi_waktu, 0)) as decimal(10,0)) /
                                        cast(sum(isnull(visit.jml_realisasi, 0)) as decimal(10,0))) as realisasi,
                                iif(sum(isnull(visit.target, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.jml_pof, 0)) as decimal(10,0)) /
                                    cast(sum(isnull(visit.target, 0)) as decimal(10,0)) * 100) as efectivitas,
                                sum(isnull(visit.total_pof, 0)) as amount_order
                        from
                        (
                            select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, isnull(visit_date.target, 0) as target,
                                    iif(isnull(visit.kd_visit, '')='', 0, 1) as jml_realisasi, iif(isnull(pof.total, 0) > 0, 1, 0) as jml_pof,
                                    isnull(pof.total, 0) as total_pof,
                                    iif(isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) <= 0, 0,
                                        isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0)) as akurasi_waktu
                            from
                            (
                                select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal, visit_date.kd_dealer,
                                        row_number() over(partition by visit_date.kd_visit order by visit_date.kd_visit) as target
                                from	visit_date with (nolock)
                                where	visit_date.tanggal between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                        visit_date.kd_dealer='".strtoupper(trim($request->get('code')))."' and
                                        visit_date.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                            )	visit_date
                                    left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                                visit_date.companyid=visit.companyid
                                    left join
                                    (
                                        select	pof.companyid, pof.tgl_pof, pof.kd_dealer, sum(pof.total) as total
                                        from	pof with (nolock)
                                        where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                pof.kd_dealer='".strtoupper(trim($request->get('code')))."' and
                                                pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                                        group by pof.companyid, pof.tgl_pof, pof.kd_dealer
                                    )	pof on visit_date.kd_dealer=pof.kd_dealer and
                                                visit_date.tanggal=pof.tgl_pof
                        )	visit
                        group by visit.companyid, visit.kd_dealer
                    )	visit on dealer.kd_dealer=visit.kd_dealer and
                                visit.companyid=dealer.companyid";

            $data_header = DB::connection($request->get('divisi'))->select($sql);
            $data_visit = [];
            $data_detail_visit = [];

            if(empty($data_header)) {
                return ApiResponse::responseWarning('Kode dealer tidak terdaftar');
            }

            foreach($data_header as $data) {
                $data_visit = [
                    'dealer_code'   => strtoupper(trim($data->kode_dealer)),
                    'dealer_name'   => strtoupper(trim($data->nama_dealer)),
                    'address'       => strtoupper(trim($data->alamat_dealer)),
                    'regency'       => strtoupper(trim($data->kabupaten)),
                    'actual'        => (double)$data->actual,
                    'target'        => (double)$data->target,
                    'realisasi'     => number_format((double)$data->realisasi, 2),
                    'efectivitas'   => number_format((double)$data->efectivitas, 2),
                    'amount_order'  => (double)$data->amount_order,
                ];
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(visit_date.kd_visit, '') as kode_visit")
                    ->whereBetween('visit_date.tanggal', [ $request->get('start_date'), $request->get('end_date') ])
                    ->where('visit_date.kd_dealer', strtoupper(trim($request->get('code'))))
                    ->where('visit_date.companyid', strtoupper(trim(strtoupper(trim($request->userlogin['companyid'])))))
                    ->orderBy('visit_date.kd_visit','asc')
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $kode_visit_result = '';

            foreach($data_result as $data) {
                if(strtoupper(trim($kode_visit_result)) == '') {
                    $kode_visit_result = "'".strtoupper(trim($data->kode_visit))."'";
                } else {
                    $kode_visit_result .= ",'".strtoupper(trim($data->kode_visit))."'";
                }
            }

            if(strtoupper(trim($kode_visit_result)) != '') {
                $sql = "select	isnull(visit_date.companyid, '') as companyid, isnull(visit_date.kd_visit, '') as kode_visit,
                                isnull(visit_date.kd_dealer, '') as kode_dealer, isnull(visit_date.kd_sales, '') as kode_sales,
                                isnull(salesman.nm_sales, '') as nama_sales, isnull(visit_date.keterangan, '') as keterangan_planning,
                                isnull(convert(varchar(10), visit_date.tanggal, 120), '') as tanggal_planning,
                                isnull(convert(varchar(10), visit.tanggal, 120), '') as tanggal_visit,
                                isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) as realisasi,
                                isnull(pof.total, 0) as amount_order, iif(isnull(pof.total, 0) > 0, 100, 0) as efectivitas
                        from
                        (
                            select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales,
                                    visit_date.kd_dealer, visit_date.tanggal, visit_date.keterangan
                            from	visit_date with (nolock)
                            where	visit_date.kd_visit in (".$kode_visit_result.") and
                                    visit_date.companyid='".strtoupper(trim(strtoupper(trim($request->userlogin['companyid']))))."'
                        )	visit_date
                                left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                            visit_date.companyid=visit.companyid
                                left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                            visit_date.companyid=salesman.companyid
                                left join
                                (
                                    select	pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer,
                                            sum(pof.total) as total
                                    from	pof with (nolock)
                                    where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                            pof.kd_dealer='".strtoupper(trim($request->get('code')))."' and
                                            pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                                    group by pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer
                                )	pof on visit_date.kd_sales=pof.kd_sales and
                                            visit_date.kd_dealer=pof.kd_dealer and
                                            visit_date.tanggal=pof.tgl_pof
                        order by visit_date.tanggal asc";

                $result = DB::connection($request->get('divisi'))->select($sql);

                foreach($result as $data) {
                    $data_detail_visit[] = [
                        'visit_code'    => strtoupper(trim($data->kode_visit)),
                        'sales_code'    => strtoupper(trim($data->kode_sales)),
                        'sales_name'    => strtoupper(trim($data->nama_sales)),
                        'keterangan'    => strtoupper(trim($data->keterangan_planning)),
                        'planning'      => $data->tanggal_planning,
                        'visit'         => $data->tanggal_visit,
                        'realisasi'     => number_format((double)$data->realisasi, 2),
                        'efectivitas'   => number_format($data->efectivitas, 2),
                        'amount_order'  => (double)$data->amount_order,
                    ];
                }
            }

            $data_visits = collect($data_visit)->mergeRecursive([
                'detail' => $data_detail_visit
            ]);

            return ApiResponse::responseSuccess('success', $data_visits);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function realisasiVisitSalesman(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'start_date'    => 'required',
                'end_date'      => 'required',
                'divisi'        => 'required',
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi, tanggal awal, dan tanggal akhir harus terisi');
            }

            $list_dealer = '';

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $list_dealer_selected = json_decode(str_replace('\\', '', $request->get('dealer')));

                foreach($list_dealer_selected as $result) {
                    if(strtoupper(trim($list_dealer)) == '') {
                        $list_dealer = "'".strtoupper(trim($result->dealer_code))."'";
                    } else {
                        $list_dealer .= ",'".strtoupper(trim($result->dealer_code))."'";
                    }
                }
            }
            if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_SM') {
                $salesman_code = strtoupper(trim($request->userlogin['user_id']));
            } else {
                $salesman_code = strtoupper(trim($request->get('code')));
            }

            $sql = "select	isnull(salesman.companyid, '') as companyid, isnull(salesman.kd_sales, '') as kode_sales,
                            isnull(salesman.nm_sales, '') as nama_sales, isnull(visit.actual, 0) as actual,
                            isnull(visit.target, 0) as target, isnull(visit.realisasi, 0) as realisasi,
                            isnull(visit.amount_order, 0) as amount_order, isnull(visit.efectivitas, 0) as efectivitas
                    from
                    (
                        select	salesman.companyid, salesman.kd_sales, salesman.nm_sales
                        from	salesman with (nolock)
                        where	salesman.kd_sales='".strtoupper(trim($salesman_code))."' and
                                salesman.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                    )	salesman
                    left join
                    (
                        select	visit.companyid, visit.kd_sales,
                                sum(isnull(visit.jml_realisasi, 0)) as actual,
                                sum(isnull(visit.target, 0)) as target,
                                iif(sum(isnull(visit.jml_realisasi, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.akurasi_waktu, 0)) as decimal(10,0)) /
                                        cast(sum(isnull(visit.jml_realisasi, 0)) as decimal(10,0))) as realisasi,
                                iif(sum(isnull(visit.target, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.jml_pof, 0)) as decimal(10,0)) /
                                    cast(sum(isnull(visit.target, 0)) as decimal(10,0)) * 100) as efectivitas,
                                sum(isnull(visit.total_pof, 0)) as amount_order
                        from
                        (
                            select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales, isnull(visit_date.target, 0) as target,
                                    iif(isnull(visit.kd_visit, '')='', 0, 1) as jml_realisasi, iif(isnull(pof.total, 0) > 0, 1, 0) as jml_pof,
                                    isnull(pof.total, 0) as total_pof,
                                    iif(isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) <= 0, 0,
                                        isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0)) as akurasi_waktu
                            from
                            (
                                select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal, visit_date.kd_sales, visit_date.kd_dealer,
                                        row_number() over(partition by visit_date.kd_visit order by visit_date.kd_visit) as target
                                from	visit_date with (nolock)
                                where	visit_date.tanggal between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                        visit_date.kd_sales='".strtoupper(trim($salesman_code))."' and
                                        visit_date.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(trim($list_dealer) != '') {
                $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).") ";
            }

            $sql .= " )	visit_date
                                    left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                                visit_date.companyid=visit.companyid
                                    left join
                                    (
                                        select	pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer, sum(pof.total) as total
                                        from	pof with (nolock)
                                        where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                pof.kd_sales='".strtoupper(trim($salesman_code))."' and
                                                pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(trim($list_dealer) != '') {
                $sql .= " and pof.kd_dealer in (".strtoupper(trim($list_dealer)).") ";
            }

            $sql .= " group by pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer
                                    )	pof on visit_date.kd_sales=pof.kd_sales and
                                                visit_date.kd_dealer=pof.kd_dealer and
                                                visit_date.tanggal=pof.tgl_pof
                        )	visit
                        group by visit.companyid, visit.kd_sales
                    )	visit on salesman.kd_sales=visit.kd_sales and
                                visit.companyid=salesman.companyid";

            $data_header = DB::connection($request->get('divisi'))->select($sql);
            $data_visit = [];
            $data_detail_visit = [];

            if(empty($data_header)) {
                return ApiResponse::responseWarning('Kode salesman tidak terdaftar');
            }

            foreach($data_header as $data) {
                $data_visit = [
                    'sales_code'    => strtoupper(trim($data->kode_sales)),
                    'sales_name'    => strtoupper(trim($data->nama_sales)),
                    'actual'        => (double)$data->actual,
                    'target'        => (double)$data->target,
                    'realisasi'     => number_format((double)$data->realisasi, 2),
                    'efectivitas'   => number_format((double)$data->efectivitas, 2),
                    'amount_order'  => (double)$data->amount_order,
                ];
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(visit_date.kd_dealer, '') as kode_dealer")
                    ->whereBetween('visit_date.tanggal', [ $request->get('start_date'), $request->get('end_date') ])
                    ->where('visit_date.kd_sales', strtoupper(trim($salesman_code)))
                    ->where('visit_date.companyid', strtoupper(trim(strtoupper(trim($request->userlogin['companyid'])))));

            if(trim($list_dealer) != '') {
                $sql->whereRaw("visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).")");
            }

            $sql = $sql->groupBy('visit_date.kd_dealer')
                    ->orderBy('visit_date.kd_dealer','asc')
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $kode_dealer_result = '';

            foreach($data_result as $data) {
                if(strtoupper(trim($kode_dealer_result)) == '') {
                    $kode_dealer_result = "'".strtoupper(trim($data->kode_dealer))."'";
                } else {
                    $kode_dealer_result .= ",'".strtoupper(trim($data->kode_dealer))."'";
                }
            }

            if(strtoupper(trim($kode_dealer_result)) != '') {
                $sql = "select	isnull(dealer.companyid, '') as companyid, isnull(dealer.kd_dealer, '') as kode_dealer,
                                isnull(dealer.nm_dealer, '') as nama_dealer, isnull(dealer.alamat1, '') as alamat_dealer,
                                isnull(dealer.kabupaten, '') as kabupaten, isnull(visit.actual, 0) as actual,
                                isnull(visit.target, 0) as target, isnull(visit.realisasi, 0) as realisasi,
                                isnull(visit.amount_order, 0) as amount_order, isnull(visit.efectivitas, 0) as efectivitas
                        from
                        (
                            select	dealer.companyid, dealer.kd_dealer, dealer.nm_dealer,
                                    dealer.alamat1, dealer.kabupaten
                            from	dealer with (nolock)
                            where	dealer.kd_dealer in (".strtoupper(trim($kode_dealer_result)).") and
                                    dealer.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                        )	dealer
                        left join
                        (
                            select	visit.companyid, visit.kd_dealer,
                                    sum(isnull(visit.jml_realisasi, 0)) as actual,
                                    sum(isnull(visit.target, 0)) as target,
                                    iif(sum(isnull(visit.jml_realisasi, 0)) <= 0, 0,
                                        cast(sum(isnull(visit.akurasi_waktu, 0)) as decimal(10,0)) /
                                            cast(sum(isnull(visit.jml_realisasi, 0)) as decimal(10,0))) as realisasi,
                                    iif(sum(isnull(visit.target, 0)) <= 0, 0,
                                        cast(sum(isnull(visit.jml_pof, 0)) as decimal(10,0)) /
                                        cast(sum(isnull(visit.target, 0)) as decimal(10,0)) * 100) as efectivitas,
                                    sum(isnull(visit.total_pof, 0)) as amount_order
                            from
                            (
                                select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_dealer, isnull(visit_date.target, 0) as target,
                                        iif(isnull(visit.kd_visit, '')='', 0, 1) as jml_realisasi, iif(isnull(pof.total, 0) > 0, 1, 0) as jml_pof,
                                        isnull(pof.total, 0) as total_pof,
                                        iif(isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) <= 0, 0,
                                            isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0)) as akurasi_waktu
                                from
                                (
                                    select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal, visit_date.kd_dealer,
                                            row_number() over(partition by visit_date.kd_visit order by visit_date.kd_visit) as target
                                    from	visit_date with (nolock)
                                    where	visit_date.tanggal between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                            visit_date.kd_dealer in (".strtoupper(trim($kode_dealer_result)).") and
                                            visit_date.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                                )	visit_date
                                        left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                                    visit_date.companyid=visit.companyid
                                        left join
                                        (
                                            select	pof.companyid, pof.tgl_pof, pof.kd_dealer, sum(pof.total) as total
                                            from	pof with (nolock)
                                            where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                    pof.kd_dealer in (".strtoupper(trim($kode_dealer_result)).") and
                                                    pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                                            group by pof.companyid, pof.tgl_pof, pof.kd_dealer
                                        )	pof on visit_date.kd_dealer=pof.kd_dealer and
                                                    visit_date.tanggal=pof.tgl_pof
                            )	visit
                            group by visit.companyid, visit.kd_dealer
                        )	visit on dealer.kd_dealer=visit.kd_dealer and
                                    visit.companyid=dealer.companyid";

                $result = DB::connection($request->get('divisi'))->select($sql);

                foreach($result as $data) {
                    $data_detail_visit[] = [
                        'dealer_code'   => strtoupper(trim($data->kode_dealer)),
                        'dealer_name'   => strtoupper(trim($data->nama_dealer)),
                        'address'       => strtoupper(trim($data->alamat_dealer)),
                        'regency'       => strtoupper(trim($data->kabupaten)),
                        'actual'        => (double)$data->actual,
                        'target'        => (double)$data->target,
                        'realisasi'     => number_format((double)$data->realisasi, 2),
                        'efectivitas'   => number_format((double)$data->efectivitas, 2),
                        'amount_order'  => (double)$data->amount_order,
                    ];
                }
            }

            $data_visits = collect($data_visit)->mergeRecursive([
                'detail' => $data_detail_visit
            ]);

            return ApiResponse::responseSuccess('success', $data_visits);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function realisasiVisitKoordinator(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'start_date'    => 'required',
                'end_date'      => 'required',
                'divisi'        => 'required',
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi, tanggal awal, dan tanggal akhir harus terisi');
            }

            $list_salesman = '';
            $list_dealer = '';

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $list_dealer_selected = json_decode(str_replace('\\', '', $request->get('dealer')));

                foreach($list_dealer_selected as $result) {
                    if(strtoupper(trim($list_dealer)) == '') {
                        $list_dealer = "'".strtoupper(trim($result->dealer_code))."'";
                    } else {
                        $list_dealer .= ",'".strtoupper(trim($result->dealer_code))."'";
                    }
                }
            }

            if(!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $list_salesman_selected = json_decode(str_replace('\\', '', $request->get('salesman')));

                foreach($list_salesman_selected as $result) {
                    if(strtoupper(trim($list_salesman)) == '') {
                        $list_salesman = "'".strtoupper(trim($result->sales_code))."'";
                    } else {
                        $list_salesman .= ",'".strtoupper(trim($result->sales_code))."'";
                    }
                }
            }

            if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_KORSM') {
                $koordinator_code = strtoupper(trim($request->userlogin['user_id']));
            } else {
                $koordinator_code = strtoupper(trim($request->get('code')));
            }

            $sql = "select	isnull(salesman.companyid, '') as companyid, isnull(salesman.spv, '') as kode_supervisor,
                            isnull(salesman.nm_spv, '') as nama_supervisor, isnull(visit.actual, 0) as actual,
                            isnull(visit.target, 0) as target, isnull(visit.realisasi, 0) as realisasi,
                            isnull(visit.amount_order, 0) as amount_order, isnull(visit.efectivitas, 0) as efectivitas
                    from
                    (
                        select	salesman.companyid, salesman.spv, superspv.nm_spv
                        from	salesman with (nolock)
                                    left join superspv with (nolock) on salesman.spv=superspv.kd_spv and
                                                salesman.companyid=superspv.companyid
                        where	salesman.spv='".strtoupper(trim($koordinator_code))."' and
                                salesman.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(strtoupper(trim($list_salesman)) != '') {
                $sql .= " and salesman.kd_sales in (".strtoupper(trim($list_salesman)).")";
            }

            $sql .= " group by salesman.companyid, salesman.spv, superspv.nm_spv
                    )	salesman
                    left join
                    (
                        select	visit.companyid, visit.spv,
                                sum(isnull(visit.jml_realisasi, 0)) as actual,
                                sum(isnull(visit.target, 0)) as target,
                                iif(sum(isnull(visit.jml_realisasi, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.akurasi_waktu, 0)) as decimal(10,0)) /
                                        cast(sum(isnull(visit.jml_realisasi, 0)) as decimal(10,0))) as realisasi,
                                iif(sum(isnull(visit.target, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.jml_pof, 0)) as decimal(10,0)) /
                                    cast(sum(isnull(visit.target, 0)) as decimal(10,0)) * 100) as efectivitas,
                                sum(isnull(visit.total_pof, 0)) as amount_order
                        from
                        (
                            select	visit.companyid, visit.spv,
                                    sum(isnull(visit.target, 0)) as target, sum(isnull(visit.jml_realisasi, 0)) as jml_realisasi,
                                    sum(isnull(visit.jml_pof, 0)) as jml_pof, sum(isnull(visit.total_pof, 0)) as total_pof,
                                    sum(isnull(visit.akurasi_waktu, 0)) as akurasi_waktu
                            from
                            (
                                select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales, visit_date.spv, isnull(visit_date.target, 0) as target,
                                        iif(isnull(visit.kd_visit, '')='', 0, 1) as jml_realisasi, iif(isnull(pof.total, 0) > 0, 1, 0) as jml_pof,
                                        isnull(pof.total, 0) as total_pof,
                                        iif(isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) <= 0, 0,
                                            isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0)) as akurasi_waktu
                                from
                                (
                                    select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal,
                                            salesman.spv, visit_date.kd_sales, visit_date.kd_dealer,
                                            row_number() over(partition by visit_date.kd_visit order by visit_date.kd_visit) as target
                                    from	visit_date with (nolock)
                                                left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                                            visit_date.companyid=salesman.companyid
                                    where	visit_date.tanggal between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                            salesman.spv='".strtoupper(trim($koordinator_code))."' and
                                            visit_date.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(strtoupper(trim($list_salesman)) != '') {
                $sql .= " and visit_date.kd_sales in (".strtoupper(trim($list_salesman)).")";
            }

            if(strtoupper(trim($list_dealer)) != '') {
                $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).")";
            }

            $sql .= " )	visit_date
                                        left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                                    visit_date.companyid=visit.companyid
                                        left join
                                        (
                                            select	pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer, sum(pof.total) as total
                                            from	pof with (nolock)
                                                        left join salesman with (nolock) on pof.kd_sales=salesman.kd_sales and
                                                                    pof.companyid=salesman.companyid
                                            where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                    salesman.spv='".strtoupper(trim($koordinator_code))."' and
                                                    pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(strtoupper(trim($list_salesman)) != '') {
                $sql .= " and pof.kd_sales in (".strtoupper(trim($list_salesman)).")";
            }

            if(strtoupper(trim($list_dealer)) != '') {
                $sql .= " and pof.kd_dealer in (".strtoupper(trim($list_dealer)).")";
            }

            $sql .= " group by pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer
                                        )	pof on visit_date.kd_sales=pof.kd_sales and
                                                    visit_date.kd_dealer=pof.kd_dealer and
                                                    visit_date.tanggal=pof.tgl_pof
                            )	visit
                            group by visit.companyid, visit.spv
                        )	visit
                        group by visit.companyid, visit.spv
                    )	visit on salesman.spv=visit.spv and
                                visit.companyid=salesman.companyid";

            $data_header = DB::connection($request->get('divisi'))->select($sql);
            $data_visit = [];
            $data_detail_visit = [];

            if(empty($data_header)) {
                return ApiResponse::responseWarning('Kode supervisor tidak terdaftar');
            }

            foreach($data_header as $data) {
                $data_visit = [
                    'coordinator_code'  => strtoupper(trim($data->kode_supervisor)),
                    'coordinator_name'  => strtoupper(trim($data->nama_supervisor)),
                    'actual'            => (double)$data->actual,
                    'target'            => (double)$data->target,
                    'realisasi'         => number_format((double)$data->realisasi, 2),
                    'efectivitas'       => number_format((double)$data->efectivitas, 2),
                    'amount_order'      => (double)$data->amount_order,
                ];
            }

            $sql = DB::connection($request->get('divisi'))->table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(visit_date.kd_sales, '') as kode_sales")
                    ->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                        $join->on('salesman.kd_sales', '=', 'visit_date.kd_sales')
                            ->on('salesman.companyid', '=', 'visit_date.companyid');
                    })
                    ->whereBetween('visit_date.tanggal', [ $request->get('start_date'), $request->get('end_date') ])
                    ->where('salesman.spv', strtoupper(trim($koordinator_code)))
                    ->where('visit_date.companyid', strtoupper(trim(strtoupper(trim($request->userlogin['companyid'])))));

            if(trim($list_salesman) != '') {
                $sql->whereRaw("visit_date.kd_sales in (".strtoupper(trim($list_salesman)).")");
            }

            if(strtoupper(trim($list_dealer)) != '') {
                $sql->whereRaw("visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).")");
            }

            $sql = $sql->groupBy('visit_date.kd_sales')
                    ->orderBy('visit_date.kd_sales','asc')
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $kode_sales_result = '';

            foreach($data_result as $data) {
                if(strtoupper(trim($kode_sales_result)) == '') {
                    $kode_sales_result = "'".strtoupper(trim($data->kode_sales))."'";
                } else {
                    $kode_sales_result .= ",'".strtoupper(trim($data->kode_sales))."'";
                }
            }

            if(strtoupper(trim($kode_sales_result)) != '') {
                $sql = "select	isnull(salesman.companyid, '') as companyid, isnull(salesman.kd_sales, '') as kode_sales,
                                isnull(salesman.nm_sales, '') as nama_sales, isnull(visit.actual, 0) as actual,
                                isnull(visit.target, 0) as target, isnull(visit.realisasi, 0) as realisasi,
                                isnull(visit.amount_order, 0) as amount_order, isnull(visit.efectivitas, 0) as efectivitas
                        from
                        (
                            select	salesman.companyid, salesman.kd_sales, salesman.nm_sales
                            from	salesman with (nolock)
                            where	salesman.kd_sales in (".strtoupper(trim($kode_sales_result)).") and
                                    salesman.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                        )	salesman
                        left join
                        (
                            select	visit.companyid, visit.kd_sales,
                                    sum(isnull(visit.jml_realisasi, 0)) as actual,
                                    sum(isnull(visit.target, 0)) as target,
                                    iif(sum(isnull(visit.jml_realisasi, 0)) <= 0, 0,
                                        cast(sum(isnull(visit.akurasi_waktu, 0)) as decimal(10,0)) /
                                            cast(sum(isnull(visit.jml_realisasi, 0)) as decimal(10,0))) as realisasi,
                                    iif(sum(isnull(visit.target, 0)) <= 0, 0,
                                        cast(sum(isnull(visit.jml_pof, 0)) as decimal(10,0)) /
                                        cast(sum(isnull(visit.target, 0)) as decimal(10,0)) * 100) as efectivitas,
                                    sum(isnull(visit.total_pof, 0)) as amount_order
                            from
                            (
                                select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales, isnull(visit_date.target, 0) as target,
                                        iif(isnull(visit.kd_visit, '')='', 0, 1) as jml_realisasi, iif(isnull(pof.total, 0) > 0, 1, 0) as jml_pof,
                                        isnull(pof.total, 0) as total_pof,
                                        iif(isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) <= 0, 0,
                                            isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0)) as akurasi_waktu
                                from
                                (
                                    select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal, visit_date.kd_sales, visit_date.kd_dealer,
                                            row_number() over(partition by visit_date.kd_visit order by visit_date.kd_visit) as target
                                    from	visit_date with (nolock)
                                    where	visit_date.tanggal between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                            visit_date.kd_sales in (".strtoupper(trim($kode_sales_result)).") and
                                            visit_date.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

                if(trim($list_dealer) != '') {
                    $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).") ";
                }

                $sql .= " )	visit_date
                                        left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                                    visit_date.companyid=visit.companyid
                                        left join
                                        (
                                            select	pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer, sum(pof.total) as total
                                            from	pof with (nolock)
                                            where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                    pof.kd_sales in (".strtoupper(trim($kode_sales_result)).") and
                                                    pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

                if(trim($list_dealer) != '') {
                    $sql .= " and pof.kd_dealer in (".strtoupper(trim($list_dealer)).") ";
                }

                $sql .= " group by pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer
                                        )	pof on visit_date.kd_sales=pof.kd_sales and
                                                    visit_date.kd_dealer=pof.kd_dealer and
                                                    visit_date.tanggal=pof.tgl_pof
                            )	visit
                            group by visit.companyid, visit.kd_sales
                        )	visit on salesman.kd_sales=visit.kd_sales and
                                    visit.companyid=salesman.companyid";

                $result = DB::connection($request->get('divisi'))->select($sql);

                foreach($result as $data) {
                    $data_detail_visit[] = [
                        'sales_code'    => strtoupper(trim($data->kode_sales)),
                        'sales_name'    => strtoupper(trim($data->nama_sales)),
                        'actual'        => (double)$data->actual,
                        'target'        => (double)$data->target,
                        'realisasi'     => number_format((double)$data->realisasi, 2),
                        'efectivitas'   => number_format((double)$data->efectivitas, 2),
                        'amount_order'  => (double)$data->amount_order,
                    ];
                }
            }

            $data_visits = collect($data_visit)->mergeRecursive([
                'detail' => $data_detail_visit
            ]);

            return ApiResponse::responseSuccess('success', $data_visits);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function realisasiVisitManager(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'start_date'    => 'required',
                'end_date'      => 'required',
                'divisi'        => 'required',
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi, tanggal awal, dan tanggal akhir harus terisi');
            }

            $list_koordinator = '';
            $list_salesman = '';
            $list_dealer = '';

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $list_dealer_selected = json_decode(str_replace('\\', '', $request->get('dealer')));

                foreach($list_dealer_selected as $result) {
                    if(strtoupper(trim($list_dealer)) == '') {
                        $list_dealer = "'".strtoupper(trim($result->dealer_code))."'";
                    } else {
                        $list_dealer .= ",'".strtoupper(trim($result->dealer_code))."'";
                    }
                }
            }

            if(!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $list_salesman_selected = json_decode(str_replace('\\', '', $request->get('salesman')));

                foreach($list_salesman_selected as $result) {
                    if(strtoupper(trim($list_salesman)) == '') {
                        $list_salesman = "'".strtoupper(trim($result->sales_code))."'";
                    } else {
                        $list_salesman .= ",'".strtoupper(trim($result->sales_code))."'";
                    }
                }
            }

            if(!empty($request->get('coordinator')) && trim($request->get('coordinator')) != '') {
                $list_koordinator_selected = json_decode(str_replace('\\', '', $request->get('coordinator')));

                foreach($list_koordinator_selected as $result) {
                    if(strtoupper(trim($list_koordinator)) == '') {
                        $list_koordinator = "'".strtoupper(trim($result->koordinator_code))."'";
                    } else {
                        $list_koordinator .= ",'".strtoupper(trim($result->koordinator_code))."'";
                    }
                }
            }

            $sql = "select	isnull(salesman.companyid, '') as companyid, isnull(visit.actual, 0) as actual,
                            isnull(visit.target, 0) as target, isnull(visit.realisasi, 0) as realisasi,
                            isnull(visit.amount_order, 0) as amount_order, isnull(visit.efectivitas, 0) as efectivitas
                    from
                    (
                        select	salesman.companyid
                        from	salesman with (nolock)
                                    left join superspv with (nolock) on salesman.spv=superspv.kd_spv and
                                                salesman.companyid=superspv.companyid
                        where	salesman.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(trim($list_koordinator) != '') {
                $sql .= " and salesman.spv in (".strtoupper(trim($list_koordinator)).")";
            }

            if(trim($list_salesman) != '') {
                $sql .= " and salesman.kd_sales in (".strtoupper(trim($list_salesman)).")";
            }

            $sql .= " group by salesman.companyid
                    )	salesman
                    left join
                    (
                        select	visit.companyid,
                                sum(isnull(visit.jml_realisasi, 0)) as actual,
                                sum(isnull(visit.target, 0)) as target,
                                iif(sum(isnull(visit.jml_realisasi, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.akurasi_waktu, 0)) as decimal(10,0)) /
                                        cast(sum(isnull(visit.jml_realisasi, 0)) as decimal(10,0))) as realisasi,
                                iif(sum(isnull(visit.target, 0)) <= 0, 0,
                                    cast(sum(isnull(visit.jml_pof, 0)) as decimal(10,0)) /
                                    cast(sum(isnull(visit.target, 0)) as decimal(10,0)) * 100) as efectivitas,
                                sum(isnull(visit.total_pof, 0)) as amount_order
                        from
                        (
                            select	visit.companyid,
                                    sum(isnull(visit.target, 0)) as target, sum(isnull(visit.jml_realisasi, 0)) as jml_realisasi,
                                    sum(isnull(visit.jml_pof, 0)) as jml_pof, sum(isnull(visit.total_pof, 0)) as total_pof,
                                    sum(isnull(visit.akurasi_waktu, 0)) as akurasi_waktu
                            from
                            (
                                select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales, visit_date.spv, isnull(visit_date.target, 0) as target,
                                        iif(isnull(visit.kd_visit, '')='', 0, 1) as jml_realisasi, iif(isnull(pof.total, 0) > 0, 1, 0) as jml_pof,
                                        isnull(pof.total, 0) as total_pof,
                                        iif(isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) <= 0, 0,
                                            isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0)) as akurasi_waktu
                                from
                                (
                                    select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal,
                                            salesman.spv, visit_date.kd_sales, visit_date.kd_dealer,
                                            row_number() over(partition by visit_date.kd_visit order by visit_date.kd_visit) as target
                                    from	visit_date with (nolock)
                                                left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                                            visit_date.companyid=salesman.companyid
                                    where	visit_date.tanggal between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                            visit_date.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(trim($list_koordinator) != '') {
                $sql .= " and salesman.spv in (".strtoupper(trim($list_koordinator)).")";
            }

            if(trim($list_salesman) != '') {
                $sql .= " and visit_date.kd_sales in (".strtoupper(trim($list_salesman)).")";
            }

            if(strtoupper(trim($list_dealer)) != '') {
                $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).")";
            }

            $sql .= " )	visit_date
                                        left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                                    visit_date.companyid=visit.companyid
                                        left join
                                        (
                                            select	pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer, sum(pof.total) as total
                                            from	pof with (nolock)
                                                        left join salesman with (nolock) on pof.kd_sales=salesman.kd_sales and
                                                                    pof.companyid=salesman.companyid
                                            where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                    pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

            if(trim($list_koordinator) != '') {
                $sql .= " and salesman.spv in (".strtoupper(trim($list_koordinator)).")";
            }

            if(trim($list_salesman) != '') {
                $sql .= " and pof.kd_sales in (".strtoupper(trim($list_salesman)).")";
            }

            if(strtoupper(trim($list_dealer)) != '') {
                $sql .= " and pof.kd_dealer in (".strtoupper(trim($list_dealer)).")";
            }

            $sql .= " group by pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer
                                        )	pof on visit_date.kd_sales=pof.kd_sales and
                                                    visit_date.kd_dealer=pof.kd_dealer and
                                                    visit_date.tanggal=pof.tgl_pof
                            )	visit
                            group by visit.companyid
                        )	visit
                        group by visit.companyid
                    )	visit on visit.companyid=salesman.companyid";

            $data_header = DB::connection($request->get('divisi'))->select($sql);
            $data_visit = [];
            $data_detail_visit = [];

            if(empty($data_header)) {
                return ApiResponse::responseWarning('Kode supervisor tidak terdaftar');
            }

            foreach($data_header as $data) {
                $data_visit = [
                    'code'          => 'MANAGER',
                    'name'          => 'MANAGER',
                    'actual'        => (double)$data->actual,
                    'target'        => (double)$data->target,
                    'realisasi'     => number_format((double)$data->realisasi, 2),
                    'efectivitas'   => number_format((double)$data->efectivitas, 2),
                    'amount_order'  => (double)$data->amount_order,
                ];
            }

            $sql = DB::connection($request->get('divisi'))->table('visit_date')->lock('with (nolock)')
                    ->selectRaw("isnull(salesman.spv, '') as kode_supervisor")
                    ->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                        $join->on('salesman.kd_sales', '=', 'visit_date.kd_sales')
                            ->on('salesman.companyid', '=', 'visit_date.companyid');
                    })
                    ->whereBetween('visit_date.tanggal', [ $request->get('start_date'), $request->get('end_date') ])
                    ->where('visit_date.companyid', strtoupper(trim(strtoupper(trim($request->userlogin['companyid'])))));

            if(trim($list_koordinator) != '') {
                $sql->whereRaw("salesman.spv in (".strtoupper(trim($list_koordinator)).")");
            }

            if(trim($list_salesman) != '') {
                $sql->whereRaw("visit_date.kd_sales in (".strtoupper(trim($list_salesman)).")");
            }

            if(strtoupper(trim($list_dealer)) != '') {
                $sql->whereRaw("visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).")");
            }

            $sql = $sql->groupBy('salesman.spv')
                    ->orderBy('salesman.spv','asc')
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $kode_koordinator_result = '';

            foreach($data_result as $data) {
                if(strtoupper(trim($kode_koordinator_result)) == '') {
                    $kode_koordinator_result = "'".strtoupper(trim($data->kode_supervisor))."'";
                } else {
                    $kode_koordinator_result .= ",'".strtoupper(trim($data->kode_supervisor))."'";
                }
            }

            if(strtoupper(trim($kode_koordinator_result)) != '') {
                $sql = "select	isnull(salesman.companyid, '') as companyid, isnull(salesman.spv, '') as kode_supervisor,
                                isnull(salesman.nm_spv, '') as nama_supervisor, isnull(visit.actual, 0) as actual,
                                isnull(visit.target, 0) as target, isnull(visit.realisasi, 0) as realisasi,
                                isnull(visit.amount_order, 0) as amount_order, isnull(visit.efectivitas, 0) as efectivitas
                        from
                        (
                            select	salesman.companyid, salesman.spv, superspv.nm_spv
                            from	salesman with (nolock)
                                        left join superspv with (nolock) on salesman.spv=superspv.kd_spv and
                                                    salesman.companyid=superspv.companyid
                            where	salesman.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

                if(strtoupper(trim($list_koordinator)) != '') {
                    $sql .= " and salesman.spv in (".strtoupper(trim($list_koordinator)).")";
                }

                if(strtoupper(trim($list_salesman)) != '') {
                    $sql .= " and salesman.kd_sales in (".strtoupper(trim($list_salesman)).")";
                }

                $sql .= " group by salesman.companyid, salesman.spv, superspv.nm_spv
                        )	salesman
                        left join
                        (
                            select	visit.companyid, visit.spv,
                                    sum(isnull(visit.jml_realisasi, 0)) as actual,
                                    sum(isnull(visit.target, 0)) as target,
                                    iif(sum(isnull(visit.jml_realisasi, 0)) <= 0, 0,
                                        cast(sum(isnull(visit.akurasi_waktu, 0)) as decimal(10,0)) /
                                            cast(sum(isnull(visit.jml_realisasi, 0)) as decimal(10,0))) as realisasi,
                                    iif(sum(isnull(visit.target, 0)) <= 0, 0,
                                        cast(sum(isnull(visit.jml_pof, 0)) as decimal(10,0)) /
                                        cast(sum(isnull(visit.target, 0)) as decimal(10,0)) * 100) as efectivitas,
                                    sum(isnull(visit.total_pof, 0)) as amount_order
                            from
                            (
                                select	visit.companyid, visit.spv,
                                        sum(isnull(visit.target, 0)) as target, sum(isnull(visit.jml_realisasi, 0)) as jml_realisasi,
                                        sum(isnull(visit.jml_pof, 0)) as jml_pof, sum(isnull(visit.total_pof, 0)) as total_pof,
                                        sum(isnull(visit.akurasi_waktu, 0)) as akurasi_waktu
                                from
                                (
                                    select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales, visit_date.spv, isnull(visit_date.target, 0) as target,
                                            iif(isnull(visit.kd_visit, '')='', 0, 1) as jml_realisasi, iif(isnull(pof.total, 0) > 0, 1, 0) as jml_pof,
                                            isnull(pof.total, 0) as total_pof,
                                            iif(isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0) <= 0, 0,
                                                isnull(cast(((7 - cast(datediff(day, visit_date.tanggal, visit.tanggal) as decimal(5,0))) / 7) * 100 as decimal(5,2)), 0)) as akurasi_waktu
                                    from
                                    (
                                        select	visit_date.companyid, visit_date.kd_visit, visit_date.tanggal,
                                                salesman.spv, visit_date.kd_sales, visit_date.kd_dealer,
                                                row_number() over(partition by visit_date.kd_visit order by visit_date.kd_visit) as target
                                        from	visit_date with (nolock)
                                                    left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                                                visit_date.companyid=salesman.companyid
                                        where	visit_date.tanggal between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                visit_date.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

                if(strtoupper(trim($list_koordinator)) != '') {
                    $sql .= " and salesman.spv in (".strtoupper(trim($list_koordinator)).")";
                }

                if(strtoupper(trim($list_salesman)) != '') {
                    $sql .= " and visit_date.kd_sales in (".strtoupper(trim($list_salesman)).")";
                }

                if(strtoupper(trim($list_dealer)) != '') {
                    $sql .= " and visit_date.kd_dealer in (".strtoupper(trim($list_dealer)).")";
                }

                $sql .= " )	visit_date
                                            left join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                                        visit_date.companyid=visit.companyid
                                            left join
                                            (
                                                select	pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer, sum(pof.total) as total
                                                from	pof with (nolock)
                                                            left join salesman with (nolock) on pof.kd_sales=salesman.kd_sales and
                                                                        pof.companyid=salesman.companyid
                                                where	pof.tgl_pof between '".$request->get('start_date')."' and '".$request->get('end_date')."' and
                                                        pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."'";

                if(strtoupper(trim($list_koordinator)) != '') {
                    $sql .= " and salesman.spv in (".strtoupper(trim($list_koordinator)).")";
                }

                if(strtoupper(trim($list_salesman)) != '') {
                    $sql .= " and pof.kd_sales in (".strtoupper(trim($list_salesman)).")";
                }

                if(strtoupper(trim($list_dealer)) != '') {
                    $sql .= " and pof.kd_dealer in (".strtoupper(trim($list_dealer)).")";
                }

                $sql .= " group by pof.companyid, pof.tgl_pof, pof.kd_sales, pof.kd_dealer
                                            )	pof on visit_date.kd_sales=pof.kd_sales and
                                                        visit_date.kd_dealer=pof.kd_dealer and
                                                        visit_date.tanggal=pof.tgl_pof
                                )	visit
                                group by visit.companyid, visit.spv
                            )	visit
                            group by visit.companyid, visit.spv
                        )	visit on salesman.spv=visit.spv and
                                    visit.companyid=salesman.companyid";

                $result = DB::connection($request->get('divisi'))->select($sql);

                foreach($result as $data) {
                    $data_detail_visit[] = [
                        'coordinator_code'  => strtoupper(trim($data->kode_supervisor)),
                        'coordinator_name'  => strtoupper(trim($data->nama_supervisor)),
                        'actual'            => (double)$data->actual,
                        'target'            => (double)$data->target,
                        'realisasi'         => number_format((double)$data->realisasi, 2),
                        'efectivitas'       => number_format((double)$data->efectivitas, 2),
                        'amount_order'      => (double)$data->amount_order,
                    ];
                }
            }

            $data_visits = collect($data_visit)->mergeRecursive([
                'detail' => $data_detail_visit
            ]);

            return ApiResponse::responseSuccess('success', $data_visits);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

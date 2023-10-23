<?php

namespace App\Http\Controllers\Api\Part;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class SalesBoController extends Controller {

    public function listDealerSalesBo(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'tanggal'   => 'required',
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data tanggal dan divisi terlebih dahulu');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $role_id = strtoupper(trim($request->userlogin['role_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $kode_supervisor = '';
            /* ==================================================================== */
            /* Cek Role Id Supervisor */
            /* ==================================================================== */
            if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_KORSM') {
                $sql = DB::connection($request->get('divisi'))
                        ->table('superspv')->lock('with (nolock)')
                        ->selectRaw("isnull(superspv.kd_spv, '') as kode_supervisor")
                        ->where('superspv.nm_spv', strtoupper(trim($request->userlogin['user_id'])))
                        ->where('superspv.companyid', strtoupper(trim($request->userlogin['companyid'])))
                        ->first();

                if(empty($sql->kode_supervisor) && trim($sql->kode_supervisor) == '') {
                    return ApiResponse::responseWarning('Kode supervisor anda tidak ditemukan, hubungi IT Programmer');
                }

                $kode_supervisor = strtoupper(trim($sql->kode_supervisor));
            }

            $sql = DB::table('dealer')->lock('with (nolock)')
                    ->selectRaw("isnull(dealer.kd_dealer, '') as kode_dealer")
                    ->where('dealer.companyid', strtoupper(trim($companyid)))
                    ->whereRaw("isnull(dealer.delsign, 0)=0");

            if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
                $sql->where('dealer.kd_sales', strtoupper(trim($user_id)));
            } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                    $join->on('salesman.kd_sales', '=', 'dealer.kd_sales')
                        ->on('salesman.companyid', '=', 'dealer.companyid');
                });
                $sql->where('salesman.spv', $kode_supervisor);
            } elseif(strtoupper(trim($role_id)) == 'D_H3') {
                $sql->where('dealer.kd_dealer', strtoupper(trim($user_id)));
            }

            if(!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $sql->where('dealer.kd_sales', strtoupper(trim($request->get('salesman'))));
            }

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $sql->where('dealer.kd_dealer', strtoupper(trim($request->get('dealer'))));
            }

            $sql = $sql->orderBy('dealer.kd_dealer', 'asc')
                        ->paginate(10);

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

            if(trim($kode_dealer_result) == '' || empty($kode_dealer_result)) {
                return ApiResponse::responseWarning('Tidak ada transaksi pada data yang anda entry');
            }

            $sql = "select	isnull(dealer.kd_dealer, '') as kode_dealer, isnull(dealer.nm_dealer, '') as nama_dealer,
                            isnull(dealer.alamat1, '') as alamat, isnull(dealer.kabupaten, '') as kabupaten,
                            isnull(dealer.limit, 0) as limit, isnull(dealer.omzet_berjalan, 0) as omzet,
                            isnull(dealer.piutang, 0) as piutang, isnull(faktur.jumlah_faktur, 0) as jumlah_faktur
                    from
                    (
                        select	dealer.companyid, dealer.kd_dealer, dealer.nm_dealer, dealer.alamat1, dealer.kabupaten,
                                isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0) as omzet_berjalan,
                                isnull(dealer.s_awal_b, 0) + isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0) -
                                    isnull(dealer.extra, 0) + isnull(dealer.da, 0) - isnull(dealer.ca, 0) -
                                        isnull(dealer.insentif, 0) - isnull(dealer.t_bayar_b, 0) as piutang,
                                case
                                    when isnull(dealer.limit_piut, 0)=1 or isnull(dealer.limit_sales, 0)=1 then 1
                                    when isnull(dealer.limit_piut, 0) <> 0 or isnull(dealer.limit_sales, 0) <> 0 then
                                            isnull(dealer.limit_piut, 0) - (isnull(dealer.s_awal_b, 0) + isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0) - isnull(dealer.extra, 0) +
                                                isnull(dealer.da, 0) - isnull(dealer.ca, 0) - isnull(dealer.insentif, 0) - isnull(dealer.t_bayar_b, 0))
                                    else
                                        isnull(dealer.limit_sales, 0) - (isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0))
                                end as limit
                        from	dealer with (nolock)
                        where	dealer.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                dealer.kd_dealer in (".$kode_dealer_result.")
                    )	dealer
                    left join
                    (
                        select	faktur.companyid, faktur.kd_dealer, count(faktur.no_faktur) as jumlah_faktur
                        from	faktur with (nolock)
                        where	faktur.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                faktur.tgl_faktur between cast(dateadd(month, -1, '".$request->get('tanggal')."') as date) and
                                                    cast('".$request->get('tanggal')."' as date) and
                                faktur.kd_dealer in (".$kode_dealer_result.")
                        group by faktur.companyid, faktur.kd_dealer
                    )	faktur on dealer.companyid=faktur.companyid and dealer.kd_dealer=faktur.kd_dealer
                    order by dealer.companyid asc, dealer.kd_dealer asc";

            $result = DB::select($sql);

            return ApiResponse::responseSuccess('success', $result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listPartSalesBo(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'tanggal'   => 'required',
                'dealer'    => 'required',
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data tanggal, dealer, dan divisi terlebih dahuelu');
            }

            $companyid = strtoupper(trim($request->userlogin['companyid']));

            // ====================================================================
            // QUERY FAKTUR
            // ====================================================================
            $sqlFaktur = DB::table(function ($query) use ($request, $companyid) {
                $query->selectRaw("faktur.companyid, faktur.no_faktur, faktur.kd_dealer, faktur.tgl_faktur")
                    ->from('faktur')->lock('with (nolock)')
                    ->where('faktur.kd_dealer', strtoupper(trim($request->get('dealer'))))
                    ->where('faktur.companyid', strtoupper(trim($companyid)))
                    ->whereRaw("faktur.tgl_faktur between
                                cast(dateadd(month, -1, '".$request->get('tanggal')."') as date) and
                                cast('".$request->get('tanggal')."' as date)");
            }, 'faktur')
            ->selectRaw("faktur.companyid, faktur.kd_dealer, fakt_dtl.kd_part,
                        sum(isnull(fakt_dtl.jml_jual, 0)) as total_faktur,
                        0 as total_bo")
            ->leftJoin(DB::raw('fakt_dtl with (nolock)'), function($join) {
                $join->on('fakt_dtl.no_faktur', '=', 'faktur.no_faktur')
                    ->on('fakt_dtl.companyid', '=', 'faktur.companyid');
            })
            ->whereRaw("isnull(fakt_dtl.jml_jual, 0) > 0")
            ->groupByRaw("faktur.companyid, faktur.kd_dealer, fakt_dtl.kd_part");

            // ====================================================================
            // QUERY BO
            // ====================================================================
            $sqlBO = DB::table('bo')->lock('with (nolock)')
                        ->selectRaw("bo.companyid, bo.kd_dealer, bo.kd_part, 0 as total_faktur,
                                    sum(isnull(bo.jumlah, 0)) as total_bo")
                        ->where('bo.companyid', strtoupper(trim($companyid)))
                        ->where('bo.kd_dealer', strtoupper(trim($request->get('dealer'))))
                        ->whereRaw("isnull(bo.jumlah, 0) > 0")
                        ->groupByRaw("bo.companyid, bo.kd_dealer, bo.kd_part");

            $sqlSalesBo = DB::table($sqlFaktur, 'faktur')
                            ->unionAll($sqlBO);
            // ====================================================================
            // QUERY RESULT
            // ====================================================================
            $sqlResult = DB::table($sqlSalesBo, 'salesbo')
                        ->selectRaw("salesbo.companyid, salesbo.kd_dealer, salesbo.kd_part,
                                    sum(isnull(salesbo.total_faktur, 0)) as total_faktur,
                                    sum(isnull(salesbo.total_bo, 0)) as total_bo ")
                        ->groupByRaw("salesbo.companyid, salesbo.kd_dealer, salesbo.kd_part");

            // ====================================================================
            // QUERY RESULT
            // ====================================================================
            $sqlResult = DB::table($sqlResult, 'salesbo')
                        ->selectRaw("isnull(salesbo.companyid, '') as companyid,
                                    isnull(salesbo.kd_dealer, '') as kode_dealer,
                                    isnull(dealer.nm_dealer, '') as nama_dealer,
                                    isnull(dealer.alamat1, '') as alamat,
                                    isnull(dealer.kabupaten, '') as kabupaten,
                                    isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0) as omzet_berjalan,
                                    isnull(dealer.s_awal_b, 0) + isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0) -
                                        isnull(dealer.extra, 0) + isnull(dealer.da, 0) - isnull(dealer.ca, 0) -
                                            isnull(dealer.insentif, 0) - isnull(dealer.t_bayar_b, 0) as piutang,
                                    case
                                        when isnull(dealer.limit_piut, 0)=1 or isnull(dealer.limit_sales, 0)=1 then 1
                                        when isnull(dealer.limit_piut, 0) <> 0 or isnull(dealer.limit_sales, 0) <> 0 then
                                                isnull(dealer.limit_piut, 0) - (isnull(dealer.s_awal_b, 0) + isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0) - isnull(dealer.extra, 0) +
                                                    isnull(dealer.da, 0) - isnull(dealer.ca, 0) - isnull(dealer.insentif, 0) - isnull(dealer.t_bayar_b, 0))
                                        else
                                            isnull(dealer.limit_sales, 0) - (isnull(dealer.jual_14, 0) + isnull(dealer.jual_20, 0))
                                    end as limit,
                                    isnull(salesbo.kd_part, '') as part_number,
                                    isnull(part.ket, '') as part_description,
                                    isnull(produk.nama, '') as produk,
                                    isnull(salesbo.total_faktur, 0) as total_faktur,
                                    isnull(salesbo.total_bo, 0) as total_bo")
                        ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                            $join->on('dealer.kd_dealer', '=', 'salesbo.kd_dealer')
                                ->on('dealer.companyid', '=', 'salesbo.companyid');
                        })
                        ->leftJoin(DB::raw('part with (nolock)'), function($join) {
                            $join->on('part.kd_part', '=', 'salesbo.kd_part')
                                ->on('part.companyid', '=', 'salesbo.companyid');
                        })
                        ->leftJoin(DB::raw('sub with (nolock)'), function($join) {
                            $join->on('sub.kd_sub', '=', 'part.kd_sub');
                        })
                        ->leftJoin(DB::raw('produk with (nolock)'), function($join) {
                            $join->on('produk.kd_produk', '=', 'sub.kd_produk');
                        });

            $sqlResult = $sqlResult->paginate(10);
            $result = collect($sqlResult)->toArray();
            $data_result = $result['data'];

            $kode_dealer = '';
            $nama_dealer = '';
            $alamat = '';
            $kabupaten = '';
            $omzet_berjalan = 0;
            $piutang = 0;
            $limit = 0;

            $data_sales_bo_part = new Collection();

            foreach($data_result as $data) {
                $kode_dealer = strtoupper(trim($data->kode_dealer));
                $nama_dealer = strtoupper(trim($data->nama_dealer));
                $alamat = strtoupper(trim($data->alamat));
                $kabupaten = strtoupper(trim($data->kabupaten));
                $omzet_berjalan = (double)$data->omzet_berjalan;
                $piutang = (double)$data->piutang;
                $limit = (double)$data->limit;

                $data_sales_bo_part->push((object) [
                    'kode_dealer'   => strtoupper(trim($data->kode_dealer)),
                    'part_pictures' => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                    'part_number'   => strtoupper(trim($data->part_number)),
                    'part_description' => strtoupper(trim($data->part_description)),
                    'produk'        => strtoupper(trim($data->produk)),
                    'total_faktur'  => (double)$data->total_faktur,
                    'total_bo'      => (double)$data->total_bo
                ]);
            }

            $data = [
                'kode_dealer'   => $kode_dealer,
                'nama_dealer'   => $nama_dealer,
                'alamat'        => $alamat,
                'kabupaten'     => $kabupaten,
                'omzet_berjalan'=> (double)$omzet_berjalan,
                'piutang'       => (double)$piutang,
                'limit'         => (double)$limit,
                'list_part'     => $data_sales_bo_part
                                    ->where('kode_dealer', $kode_dealer)
                                    ->values()
                                    ->all()
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listFakturSalesBo(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'tanggal'       => 'required',
                'dealer'        => 'required',
                'part_number'   => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data tanggal, dealer, part number dan divisi terlebih dahulu');
            }

            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = "select	isnull(faktur.companyid, '') as companyid,
                            isnull(faktur.no_faktur, '') as nomor_faktur,
                            isnull(faktur.tgl_faktur, '') as tanggal_faktur,
                            isnull(faktur.kd_part, '') as part_number,
                            isnull(part.ket, '') as part_description,
                            isnull(produk.nama, '') as produk,
                            isnull(faktur.jml_faktur, 0) as jumlah_faktur,
                            isnull(bo.jumlah_bo, 0) as jumlah_bo
                    from
                    (
                        select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur, fakt_dtl.kd_part,
                                sum(isnull(fakt_dtl.jml_jual, 0)) as jml_faktur
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur
                            from	faktur with (nolock)
                            where	faktur.companyid='".strtoupper(trim($companyid))."' and
                                    faktur.kd_dealer='".strtoupper(trim($request->get('dealer')))."' and
                                    faktur.tgl_faktur between
                                        cast(dateadd(month, -1, '".$request->get('tanggal')."') as date) and
                                        cast('".$request->get('tanggal')."' as date)
                        )	faktur
                                inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                            faktur.companyid=fakt_dtl.companyid
                        where	isnull(fakt_dtl.jml_jual, 0) > 0 and
                                fakt_dtl.kd_part='".strtoupper(trim($request->get('part_number')))."'
                        group by faktur.companyid, faktur.no_faktur, faktur.tgl_faktur, fakt_dtl.kd_part
                    )	faktur
                    left join
                    (
                        select	bo.companyid, bo.kd_part,
                                sum(isnull(bo.jumlah, 0)) as jumlah_bo
                        from	bo with (nolock)
                        where	bo.companyid='".strtoupper(trim($companyid))."' and
                                bo.kd_part='".strtoupper(trim($request->get('part_number')))."'
                        group by bo.companyid, bo.kd_part
                    )	bo on faktur.companyid=bo.companyid and faktur.kd_part=bo.kd_part
                    left join part with (nolock) on faktur.kd_part=part.kd_part and
                                faktur.companyid=part.companyid
                    left join sub with (nolock) on part.kd_sub=sub.kd_sub
                    left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

            $result = DB::select($sql);
            $data_faktur = new Collection();

            $part_number = '';
            $part_description = '';
            $produk = '';
            $jumlah_faktur = 0;
            $jumlah_bo = 0;

            foreach($result as $data) {
                $part_number = strtoupper(trim($data->part_number));
                $part_description = strtoupper(trim($data->part_description));
                $produk = strtoupper(trim($data->produk));
                $jumlah_faktur = (double)$jumlah_faktur + (double)$data->jumlah_faktur;
                $jumlah_bo = (double)$data->jumlah_bo;

                $data_faktur->push((object) [
                    'part_number'   => strtoupper(trim($data->part_number)),
                    'nomor_faktur'  => strtoupper(trim($data->nomor_faktur)),
                    'tanggal'       => strtoupper(trim($data->tanggal_faktur)),
                    'jumlah_jual'   => (double)$data->jumlah_faktur
                ]);
            }

            $data = [
                'part_number'       => strtoupper(trim($part_number)),
                'part_description'  => strtoupper(trim($part_description)),
                'produk'            => strtoupper(trim($produk)),
                'total_faktur'      => (double)$jumlah_faktur,
                'total_bo'          => (double)$jumlah_bo,
                'list_faktur'       => $data_faktur
                                        ->values()
                                        ->all()
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

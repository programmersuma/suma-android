<?php

namespace App\Http\Controllers\Api\Tracking;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class TrackingController extends Controller {

    public function trackingOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'month'         => 'required|string',
                'ms_dealer_id'  => 'required|string'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Kode Dealer, bulan, dan tahun harus diisi');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(users.role_id, '') as role_id,
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
            $role_id = strtoupper(trim($sql->role_id));
            $companyid = strtoupper(trim($sql->companyid));

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
            }

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Id dealer tidak terdaftar');
            }

            $year = substr($request->get('month'), 0, 4);
            $month = substr($request->get('month'), 5, 2);
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            // ===================================================================
            // GET NOMOR FAKTUR TRACKING ORDER
            // ===================================================================
            $sql = DB::table('faktur')->lock('with (nolock)')
                    ->selectRaw("isnull(faktur.no_faktur, '') as nomor_faktur");

            if(!empty($request->get('part_number')) && trim($request->get('part_number')) != '') {
                $sql->leftJoin(DB::raw('fakt_dtl with (nolock)'), function($join) {
                    $join->on('fakt_dtl.no_faktur', '=', 'faktur.no_faktur')
                        ->on('fakt_dtl.companyid', '=', 'faktur.companyid');
                });
            }

            $sql->whereYear('faktur.tgl_faktur', $year)
                ->whereMonth('faktur.tgl_faktur', $month)
                ->where('faktur.kd_dealer', strtoupper($kode_dealer))
                ->where('faktur.companyid', strtoupper(trim($companyid)));

            if(strtoupper(trim($role_id)) == 'D_H3') {
                $sql->where('faktur.kd_dealer', strtoupper(trim($user_id)));
            } elseif(strtoupper(trim($role_id)) == 'MD_H3_SM') {
                $sql->where('faktur.kd_sales', strtoupper(trim($user_id)));
            } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql->where('salesman.spv', strtoupper(trim($kode_supervisor)));
            }

            if(!empty($request->get('nomor_faktur')) && trim($request->get('nomor_faktur')) != '') {
                $sql->where('faktur.no_faktur', 'like', '%'.strtoupper(trim($request->get('nomor_faktur'))).'%');
            }

            if(!empty($request->get('status_bo')) && trim($request->get('status_bo')) != '') {
                if(strtoupper(trim($request->get('status_bo'))) == 'BO') {
                    $sql->where('faktur.bo', 'B');
                } else {
                    $sql->where('faktur.bo', 'T');
                }
            }

            if(!empty($request->get('status_invoice')) && trim($request->get('status_invoice')) != '') {
                if(strtoupper(trim($request->get('status_invoice'))) == 'LUNAS') {
                    $sql->whereRaw("isnull(faktur.terbayar, 0) = isnull(faktur.total, 0)");
                } else {
                    $sql->whereRaw("isnull(faktur.terbayar, 0) <> isnull(faktur.total, 0)");
                }
            }

            if(!empty($request->get('part_number')) && trim($request->get('part_number')) != '') {
                $sql->where('fakt_dtl.kd_part', strtoupper(trim($request->get('part_number'))));
            }

            $sql = $sql->groupByRaw("faktur.no_faktur, faktur.tgl_faktur")
                        ->orderByRaw("faktur.tgl_faktur desc,
                                    faktur.no_faktur desc")
                        ->paginate(10);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $nomor_faktur_result = '';

            $data_tracking = new Collection();
            $data_tracking_temp = new Collection();
            $data_detail_tracking = new Collection();
            $result_data_tracking = [];

            foreach($data_result as $data) {
                if(strtoupper(trim($nomor_faktur_result)) == '') {
                    $nomor_faktur_result = "'".strtoupper(trim($data->nomor_faktur))."'";
                } else {
                    $nomor_faktur_result .= ",'".strtoupper(trim($data->nomor_faktur))."'";
                }
            }

            if(strtoupper(trim($nomor_faktur_result)) != '') {
                // ===================================================================
                // GET DETAIL FAKTUR TRACKING ORDER
                // ===================================================================
                $sql = "select	substring(faktur.no_faktur, 3, 5) as id, isnull(faktur.no_faktur, '') as nomor_faktur,
                                isnull(faktur.tgl_faktur, '') as tanggal_faktur, isnull(faktur.kd_sales, '') as kode_sales,
                                isnull(salesman.nm_sales, '') as nama_sales, isnull(faktur.kd_dealer, '') as kode_dealer,
                                isnull(dealer.nm_dealer, '') as nama_dealer, isnull(faktur.ket, '') as keterangan,
                                isnull(faktur.kd_tpc, '') as kode_tpc, isnull(faktur.bo, '') as bo,
                                isnull(faktur.disc2, 0) as disc_header, isnull(faktur.discrp, 0) + isnull(faktur.discrp1, 0) as disc_rp,
                                isnull(faktur.total, 0) as total_faktur, isnull(fakt_dtl.kd_part, '') as part_number,
                                isnull(mspart.id, 0) as part_id, isnull(part.ket, '') as part_description,
                                isnull(produk.nama, '') as item_group, isnull(fakt_dtl.jml_order, 0) as jml_order,
                                isnull(fakt_dtl.jml_jual, 0) as jml_jual, isnull(fakt_dtl.harga, 0) as harga_satuan,
                                isnull(fakt_dtl.disc1, 0) as disc_detail, isnull(fakt_dtl.jumlah, 0) as total_detail,
                                cast(isnull(year(faktur.tgl_faktur), 0) as varchar(4)) + '=' +
                                    cast(isnull(month(faktur.tgl_faktur), 0) as varchar(2)) + '=' +
                                        ltrim(rtrim(upper(isnull(faktur.kd_dealer, '')))) + '=' +
                                            ltrim(rtrim(upper(isnull(fakt_dtl.kd_part, '')))) as tracking_item_id
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur,
                                    faktur.kd_sales, faktur.kd_dealer, faktur.ket,
                                    faktur.kd_tpc, faktur.bo, faktur.total,
                                    faktur.disc2, faktur.discrp, faktur.discrp1
                            from	faktur with (nolock)
                            where	faktur.no_faktur in (".$nomor_faktur_result.") and
                                    faktur.companyid='".strtoupper(trim($companyid))."'
                        )	faktur
                                left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                            faktur.companyid=salesman.companyid
                                left join dealer with (nolock) on faktur.kd_dealer=dealer.kd_dealer and
                                            faktur.companyid=dealer.companyid
                                inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                            faktur.companyid=fakt_dtl.companyid
                                left join part with (nolock) on fakt_dtl.kd_part=part.kd_part and
                                            faktur.companyid=part.companyid
                                left join mspart with (nolock) on fakt_dtl.kd_part=mspart.kd_part
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                        order by faktur.companyid asc, faktur.no_faktur asc, fakt_dtl.kd_part asc";

                $result = DB::select($sql);

                foreach($result as $data) {
                    $data_detail_tracking->push((object) [
                        'id'                => strtoupper(trim(trim($data->tracking_item_id))),
                        'nomor_faktur'      => strtoupper(trim($data->nomor_faktur)),
                        'date_order'        => trim($data->tanggal_faktur).' '.date('h:i:s'),
                        'dealer'            => strtoupper(trim($data->kode_dealer)).' • '.strtoupper(trim($data->nama_dealer)),
                        'part_id'           => (int)$data->part_id,
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'part_description'  => strtoupper(trim($data->part_description)),
                        'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                        'item_group'        => strtoupper(trim($data->item_group)),
                        'price'             => (double)$data->harga_satuan,
                        'order'             => (double)$data->jml_order,
                        'supply'            => (double)$data->jml_jual,
                        'discount'          => (double)$data->disc_detail,
                        'total_price'       => (double)$data->total_detail
                    ]);

                    $data_tracking_temp->push((object) [
                        'id'            => (int)$data->id,
                        'nomor_faktur'  => strtoupper(trim($data->nomor_faktur)),
                        'date_order'    => trim($data->tanggal_faktur).' '.date('h:i:s'),
                        'salesman'      => strtoupper(trim($data->kode_sales)).' • '.strtoupper(trim($data->nama_sales)),
                        'dealer'        => strtoupper(trim($data->kode_dealer)).' • '.strtoupper(trim($data->nama_dealer)),
                        'keterangan'    => (trim($data->keterangan) == '') ? '' : '*) '.strtoupper(trim($data->keterangan)),
                        'tpc_code'      => strtoupper(trim($data->kode_tpc)),
                        'status_bo'     => (strtoupper(trim($data->bo)) == 'B') ? 'BACK ORDER' : 'TIDAK BO',
                        'discount_percent'  => (double)$data->disc_header,
                        'discount_rupiah'   => (double)$data->disc_rp,
                        'grand_total'   => (double)$data->total_faktur,
                    ]);
                }

                $nomor_faktur = "";
                foreach($data_tracking_temp as $result) {
                    if (strtoupper(trim($nomor_faktur)) != strtoupper(trim($result->nomor_faktur))) {
                        $data_tracking->push((object) [
                            'id'                    => (int)$result->id,
                            'nomor_faktur'          => strtoupper(trim($result->nomor_faktur)),
                            'date_order'            => trim($result->date_order),
                            'salesman'              => strtoupper(trim($result->salesman)),
                            'dealer'                => strtoupper(trim($result->dealer)),
                            'keterangan'            => strtoupper(trim(trim($result->keterangan))),
                            'tpc_code'              => strtoupper(trim(trim($result->tpc_code))),
                            'status_bo'             => strtoupper(trim(trim($result->status_bo))),
                            'total_order'           => $data_detail_tracking->where('nomor_faktur', strtoupper(trim($result->nomor_faktur)))->sum('order'),
                            'total_supply'          => $data_detail_tracking->where('nomor_faktur', strtoupper(trim($result->nomor_faktur)))->sum('supply'),
                            'discount_percent'      => (double)$result->discount_percent,
                            'discount_rupiah'       => (double)$result->discount_rupiah,
                            'grand_total'           => (double)$result->grand_total,
                            'item'                  => $data_detail_tracking->where('nomor_faktur', strtoupper(trim($result->nomor_faktur)))->values()->all()
                        ]);
                        $nomor_faktur = strtoupper(trim($result->nomor_faktur));
                    }
                }

                if(!empty($request->get('sorting')) && trim($request->get('sorting')) != '') {
                    $data_sorting = json_decode(str_replace('\\', '', $request->get('sorting')));

                    foreach($data_sorting as $sort) {
                        if(trim($sort->sorting) == 'no_order|asc') {
                            $result_data_tracking = $data_tracking->sortBy('nomor_faktur')->values()->all();
                        } elseif(trim($sort->sorting) == 'no_order|desc') {
                            $result_data_tracking = $data_tracking->sortByDesc('nomor_faktur')->values()->all();
                        }
                        if(trim($sort->sorting) == 'date_order|asc') {
                            $result_data_tracking = $data_tracking->sortBy('date_order')->values()->all();
                        } elseif(trim($sort->sorting) == 'date_order|desc') {
                            $result_data_tracking = $data_tracking->sortByDesc('date_order')->values()->all();
                        }
                        if(trim($sort->sorting) == 'amount|asc') {
                            $result_data_tracking = $data_tracking->sortBy('grand_total')->values()->all();
                        } elseif(trim($sort->sorting) == 'amount|desc') {
                            $result_data_tracking = $data_tracking->sortByDesc('grand_total')->values()->all();
                        }
                        if(trim($sort->sorting) == 'qty|asc') {
                            $result_data_tracking = $data_tracking->sortBy('total_supply')->values()->all();
                        } elseif(trim($sort->sorting) == 'qty|desc') {
                            $result_data_tracking = $data_tracking->sortByDesc('total_supply')->values()->all();
                        }
                    }
                } else {
                    $result_data_tracking = $data_tracking->sortByDesc('nomor_faktur')->values()->all();
                }
            }

            return ApiResponse::responseSuccess('success', $result_data_tracking);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function detailTracking(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'tracking_item_id' => 'required|string'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih atau isi part number yang ada didalam nomor faktur terlebih dahulu', null);
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(users.role_id, '') as role_id,
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
            $nomor_order_detail = explode("=", strtoupper(trim($request->get('tracking_item_id'))));

            $tahun = trim($nomor_order_detail[0]);
            $bulan = trim($nomor_order_detail[1]);
            $dealer = trim($nomor_order_detail[2]);
            $part_number = trim($nomor_order_detail[3]);

            $sql = DB::table('faktur')->lock('with (nolock)')
                    ->selectRaw("isnull(faktur.no_faktur, '') as nomor_faktur")
                    ->leftJoin(DB::raw('fakt_dtl with (nolock)'), function($join) {
                        $join->on('fakt_dtl.no_faktur', '=', 'faktur.no_faktur')
                            ->on('fakt_dtl.companyid', '=', 'faktur.companyid');
                    })
                    ->whereYear('faktur.tgl_faktur', trim($tahun))
                    ->whereMonth('faktur.tgl_faktur', trim($bulan))
                    ->where('faktur.kd_dealer', strtoupper(trim($dealer)))
                    ->where('fakt_dtl.kd_part', strtoupper(trim($part_number)))
                    ->where('faktur.companyid', strtoupper(trim($companyid)))
                    ->orderBy('faktur.no_faktur','asc')
                    ->paginate(10);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $data_tracking_order_result = new Collection();
            $nomor_faktur_result = '';

            foreach($data_result as $data) {
                if(trim($nomor_faktur_result) == '') {
                    $nomor_faktur_result = "'".strtoupper(trim($data->nomor_faktur))."'";
                } else {
                    $nomor_faktur_result .= ",'".strtoupper(trim($data->nomor_faktur))."'";
                }
            }

            if($nomor_faktur_result != '') {
                $sql = "select	isnull(faktur.companyid, '') as companyid, substring(faktur.no_faktur, 3, 5) as tracking_id,
                                isnull(mspart.id, 0) as part_id, isnull(faktur.kd_part, '') as part_number,
                                isnull(part.ket, '') as part_description, isnull(produk.nama, '') as item_group,
                                isnull(faktur.no_faktur, '') as nomor_faktur, isnull(faktur.tgl_faktur, '') as tanggal_faktur,
                                isnull(faktur.jml_jual, 0) as jumlah_pengiriman, isnull(sj.no_sj, '') as nomor_sj,
                                isnull(convert(varchar(10), sj.tgl,120), '') as tanggal_sj, isnull(serah.no_dok, '') as nomor_serah_terima,
                                isnull(convert(varchar(10), serah.tanggal, 120), '') as tanggal_serah_terima,
                                isnull(serah.no_polisi, '') as nomor_polisi, isnull(serah.sopir, '') as sopir
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur, faktur.kd_dealer, faktur.bo,
                                    fakt_dtl.kd_part, fakt_dtl.jml_order, fakt_dtl.jml_jual
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.bo, faktur.tgl_faktur,
                                        faktur.kd_dealer, faktur.disc2, faktur.discrp, faktur.discrp1
                                from	faktur with (nolock)
                                where	faktur.no_faktur in (".strtoupper(trim($nomor_faktur_result)).") and
                                        faktur.companyid='".strtoupper(trim($companyid))."'
                            )	faktur
                                    inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where   isnull(fakt_dtl.jml_jual, 0) > 0
                        )	faktur
                                left join bo with (nolock) on faktur.kd_dealer=bo.kd_dealer and
                                            faktur.kd_part=bo.kd_part and
                                            faktur.companyid=bo.companyid
                                left join sj_dtl with (nolock) on faktur.no_faktur=sj_dtl.no_faktur and
                                            faktur.companyid=sj_dtl.companyid
                                left join sj with (nolock) on sj_dtl.no_sj=sj.no_sj and
                                            faktur.companyid=sj.companyid
                                left join serah_dtl with (nolock) on sj.no_sj=serah_dtl.no_sj and
                                            faktur.companyid=serah_dtl.companyid
                                left join serah with (nolock) on serah_dtl.no_dok=serah.no_dok and
                                            faktur.companyid=serah.companyid
                                left join mspart with (nolock) on faktur.kd_part=mspart.kd_part
                                left join part with (nolock) on faktur.kd_part=part.kd_part and
                                            faktur.companyid=part.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                        order by faktur.no_faktur asc";

                $result = DB::select($sql);

                $data_tracking = new Collection();
                $data_faktur = new Collection();

                foreach($result as $data) {
                    $data_faktur->push((object) [
                        'part_number'           => strtoupper(trim($data->part_number)),
                        'id'                    => (int)$data->tracking_id,
                        'nomor_faktur'          => strtoupper(trim(trim($data->nomor_faktur))),
                        'tanggal_faktur'        => trim($data->tanggal_faktur).' '.date('h:i:s'),
                        'jumlah_pengiriman'     => (double)$data->jumlah_pengiriman,
                        'nomor_sj'              => strtoupper(trim($data->nomor_sj)),
                        'tanggal_sj'            => (trim($data->tanggal_sj) == '') ? '' : trim($data->tanggal_sj).' '.date('h:i:s'),
                        'nomor_serah_terima'    => strtoupper(trim($data->nomor_serah_terima)),
                        'tanggal_serah_terima'  => (trim($data->tanggal_serah_terima) == '') ? '' : trim($data->tanggal_serah_terima).' '.date('h:i:s'),
                        'ekspedisi'             => (trim($data->nomor_serah_terima) == '') ? '' : strtoupper(trim($data->nomor_polisi)).' • '.strtoupper(trim($data->sopir)),
                    ]);

                    $data_tracking->push((object) [
                        'id'                => (int)$data->tracking_id,
                        'part_id'           => (int)$data->part_id,
                        'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'part_description'  => strtoupper(trim($data->part_description)),
                        'item_group'        => strtoupper(trim($data->item_group)),
                    ]);
                }

                $part_number = "";

                foreach($data_tracking as $result) {
                    if ($part_number != strtoupper(trim($result->part_number))) {
                        $data_tracking_order_result->push((object) [
                            'id'                => (int)$result->id,
                            'part_id'           => (int)$result->part_id,
                            'part_pictures'     => trim($result->part_pictures),
                            'part_number'       => strtoupper(trim($result->part_number)),
                            'part_description'  => strtoupper(trim($result->part_description)),
                            'item_group'        => strtoupper(trim($result->item_group)),
                            'invoice'           => $data_faktur
                                                    ->where('part_number', strtoupper(trim($result->part_number)))
                                                    ->values()
                                                    ->all()
                        ]);
                        $part_number = strtoupper(trim($result->part_number));
                    }
                }
            }

            return ApiResponse::responseSuccess('success', $data_tracking_order_result->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

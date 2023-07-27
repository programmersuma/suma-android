<?php

namespace App\Http\Controllers\Api\Part;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class SuggestionController extends Controller
{
    public function listSuggestOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id' => 'required|int'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih dealer_id terlebih dahulu');
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

            $role_id = strtoupper(trim($sql->role_id));
            $companyid = strtoupper(trim($sql->companyid));

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = "select	isnull(part.id, 0) as part_id, isnull(part.kd_part, '') as part_number,
                            isnull(part.ket, '') as part_description, isnull(part.produk, '') as item_group,
                            isnull(part.jml1dus, 0) as jml_dus, isnull(part.het, 0) as  het,
                            isnull(part.stock, 0) as stock_total_part,
                            isnull(convert(varchar(10), pof.tanggal_order_terakhir, 120), '') as tanggal_order_terakhir,
                            isnull(convert(varchar(10), faktur_terakhir.tanggal_faktur_terakhir, 120), '') as tanggal_faktur_terakhir,
                            isnull(pof.jumlah_order_terakhir, 0) as jumlah_order_terakhir,
                            isnull(faktur_terakhir.jumlah_order_faktur_terakhir, 0) as jumlah_order_faktur_terakhir,
                            isnull(faktur_terakhir.jumlah_jual_terakhir, 0) as jumlah_jual_terakhir,
                            isnull(part.jumlah_order, 0) as total_order, isnull(part.jumlah_jual, 0) as total_sales,
                            isnull(part.jumlah_bo, 0) as back_order,
                            cast(round(iif(isnull(part.jumlah_order, 0) <= 0, 0,
                                (isnull(part.jumlah_order, 0)) / 3), 0) as decimal(13, 0)) as rata_order_3_bulan,
                            cast(round(iif(isnull(part.jumlah_jual, 0) <= 0, 0, isnull(part.jumlah_jual, 0) / 3), 0) as decimal(13, 0)) as rata_sales_3_bulan,
                            cast(iif(isnull(part.jumlah_order, 0) <= 0, 0,
                                (isnull(part.jumlah_jual, 0) /
                                    (isnull(part.jumlah_order, 0))) * 100) as decimal(5,2)) as service_rate,
                            cast(round(iif(isnull(part.jumlah_order, 0) + isnull(part.jumlah_bo, 0) <= 0, 0,
                                (((isnull(part.jumlah_order, 0) + isnull(part.jumlah_bo, 0)) / 3) +
                                    round(((((isnull(part.jumlah_order, 0) + isnull(part.jumlah_bo, 0)) / 3) * 10) / 100), 0)) - isnull(part.jumlah_bo, 0)
                            ), 0) as decimal(13,0)) as suggest_order,
                            iif(isnull(campaign.kd_part, '') = '' , 'NO', 'YES') as status_campaign
                    from
                    (
                        select	part.companyid, part.id, part.kd_part, part.ket, part.produk, part.het, part.jml1dus,
                                part.jumlah_order, part.jumlah_jual, part.jumlah_bo,
                                iif(isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0) < 0, 0,
                                    isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0)) as stock
                        from
                        (
                            select	faktur.companyid, mspart.id, faktur.kd_part, faktur.jumlah_order, faktur.jumlah_jual,
                                    faktur.jumlah_bo, part.ket, produk.nama as produk, part.het, part.jml1dus,
                                    isnull(tbstlokasirak.stock, 0) -
                                        (isnull(stlokasi.min, 0) + isnull(stlokasi.in_transit, 0) +
                                            isnull(part.kanvas, 0) + isnull(part.min_gudang, 0) +
                                                isnull(part.in_transit, 0) + isnull(part.min_htl, 0)) as stock
                            from
                            (
                                select	faktur.companyid, faktur.kd_part,
                                        isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0) as jumlah_order,
                                        isnull(faktur.jumlah_jual, 0) as jumlah_jual,
                                        isnull(bo.jumlah, 0) as jumlah_bo
                                from
                                (
                                    select	faktur.companyid, fakt_dtl.kd_part,
                                            sum(fakt_dtl.jml_order) as jumlah_order,
                                            sum(fakt_dtl.jml_jual) as jumlah_jual
                                    from
                                    (
                                        select	faktur.companyid, faktur.no_faktur
                                        from	faktur with (nolock)
                                        where	faktur.tgl_faktur >= dateadd(month, -3, getdate()) and
                                                faktur.companyid='".strtoupper(trim($companyid))."' and
                                                faktur.kd_dealer='".strtoupper(trim($kode_dealer))."'
                                    )	faktur
                                            inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                        faktur.companyid=fakt_dtl.companyid
                                    where	isnull(fakt_dtl.jml_jual, 0) > 0
                                    group by faktur.companyid, fakt_dtl.kd_part
                                )	faktur
                                        left join bo with (nolock) on faktur.kd_part=bo.kd_part and
                                                    faktur.companyid=bo.companyid and
                                                    bo.kd_dealer='".strtoupper(trim($kode_dealer))."'
                            )	faktur
                                    inner join company with (nolock) on faktur.companyid=company.companyid
                                    inner join mspart with (nolock) on faktur.kd_part=mspart.kd_part
                                    left join part with (nolock) on faktur.kd_part=part.kd_part and
                                                faktur.companyid=part.companyid
                                    left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                    left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                                    left join stlokasi with (nolock) on faktur.kd_part=stlokasi.kd_part and
                                                company.kd_lokasi=stlokasi.kd_lokasi and
                                                faktur.companyid=stlokasi.companyid
                                    left join tbstlokasirak with (nolock) on faktur.kd_part=tbstlokasirak.kd_part and
                                                company.kd_lokasi=tbstlokasirak.kd_lokasi and
                                                company.kd_rak=tbstlokasirak.kd_rak and
                                                faktur.companyid=tbstlokasirak.companyid
                            where	isnull(tbstlokasirak.stock, 0) -
                                        (isnull(stlokasi.min, 0) + isnull(stlokasi.in_transit, 0) +
                                            isnull(part.kanvas, 0) + isnull(part.min_gudang, 0) +
                                                isnull(part.in_transit, 0) + isnull(part.min_htl, 0)) > 0
                        )	part
                                left join minshare with (nolock) on part.kd_part=minshare.kd_part and
                                            part.companyid=minshare.companyid
                        where	iif(isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0) < 0, 0,
                                    isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0)) > 0
                    )	part
                    left join
                    (
                        select	faktur.companyid, faktur.tgl_faktur as tanggal_faktur_terakhir,
                                faktur.kd_part, faktur.jml_order as jumlah_order_faktur_terakhir,
                                faktur.jml_jual as jumlah_jual_terakhir
                        from
                        (
                            select	row_number() over(partition by faktur.companyid, fakt_dtl.kd_part
                                        order by faktur.companyid asc, fakt_dtl.kd_part asc, faktur.tgl_faktur desc) as nomor,
                                    faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part,
                                    fakt_dtl.jml_order, fakt_dtl.jml_jual
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur
                                from	faktur with (nolock)
                                where	faktur.companyid='".strtoupper(trim($companyid))."' and faktur.kd_dealer='".strtoupper(trim($kode_dealer))."'
                            )	faktur
                                    inner join fakt_dtl on faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where	isnull(fakt_dtl.jml_jual, 0) > 0
                        )	faktur
                        where	isnull(faktur.nomor, 0) = 1
                    )	faktur_terakhir on part.companyid=faktur_terakhir.companyid and
                                            part.kd_part=faktur_terakhir.kd_part
                    left join
                    (
                        select	pof.companyid, pof.tgl_pof as tanggal_order_terakhir,
                                pof.kd_part, pof.jml_order as jumlah_order_terakhir
                        from
                        (
                            select	pof.companyid, pof.tgl_pof, pof_dtl.kd_part, pof_dtl.jml_order,
                                    row_number() over(partition by pof.companyid, pof_dtl.kd_part
                                        order by pof.companyid asc, pof_dtl.kd_part asc, pof.tgl_pof desc) as nomor
                            from
                            (
                                select	pof.companyid, pof.no_pof, pof.tgl_pof
                                from	pof with (nolock)
                                where	pof.companyid='".strtoupper(trim($companyid))."' and
                                        pof.kd_dealer='".strtoupper(trim($kode_dealer))."'
                            )	pof
                                    inner join pof_dtl with (nolock) on pof.no_pof=pof_dtl.no_pof and
                                                pof.companyid=pof_dtl.companyid
                        )	pof
                        where	isnull(pof.nomor, 0) = 1
                    )	pof on part.companyid=pof.companyid and
                                part.kd_part=pof.kd_part
                    left join
                    (
                        select	top 1 isnull(camp.companyid, '') as companyid,
                                isnull(camp_dtl.kd_part, '') as kd_part
                        from
                        (
                            select	camp.companyid, camp.no_camp
                            from	camp with (nolock)
                            where	camp.companyid='".strtoupper(trim($companyid))."' and
                                    camp.tgl_prd1 >= convert(varchar(10), getdate(), 120) and
                                    camp.tgl_prd2 <= convert(varchar(10), getdate(), 120)
                        )	camp
                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                            camp.companyid=camp_dtl.companyid
                    )	campaign on part.companyid=campaign.companyid and
                                    part.kd_part=campaign.kd_part
                    where   cast(round(iif(isnull(part.jumlah_order, 0) <= 0, 0,
                                (((isnull(part.jumlah_order, 0)) / 3) +
                                    round(((((isnull(part.jumlah_order, 0)) / 3) * 10) / 100), 0)) - isnull(part.jumlah_bo, 0)
                            ), 0) as decimal(13,0)) > 0
                    order by part.kd_part asc";

            $result = DB::select($sql);

            $data_suggestion_order = new Collection();

            foreach($result as $data) {
                if((double)$data->stock_total_part > 0) {
                    if(strtoupper(trim($role_id)) == 'MD_H3_MGMT') {
                        $available_part = 'Available '.trim($data->stock_total_part).' pcs';
                    } else {
                        $available_part = 'Available';
                    }
                } else {
                    $available_part = 'Not Available';
                }

                $data_suggestion_order->push((object) [
                    'ms_dealer_id'              => (int)$request->get('ms_dealer_id'),
                    'part_id'                   => (int)$data->part_id,
                    'part_number'               => strtoupper(trim($data->part_number)),
                    'part_description'          => strtoupper(trim($data->part_description)),
                    'part_pictures'             => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                    'item_group'                => strtoupper(trim($data->item_group)),
                    'het'                       => (double)$data->het,
                    'available_part'            => trim($available_part),
                    'jml1dus'                   => ((double)$data->jml_dus <= 0) ? 1 : (double)$data->jml_dus,
                    'total_order'               => (double)$data->total_order,
                    'rata_order_3_bl'           => (double)$data->rata_order_3_bulan,
                    'tanggal_order_terakhir'    => (empty($data->tanggal_order_terakhir) || trim($data->tanggal_order_terakhir) == '') ?
                                                    trim($data->tanggal_faktur_terakhir) : trim($data->tanggal_order_terakhir),
                    'jumlah_order_terakhir'     => (empty($data->tanggal_order_terakhir) || trim($data->tanggal_order_terakhir) == '') ?
                                                    (double)$data->jumlah_order_faktur_terakhir : (double)$data->jumlah_order_terakhir,
                    'total_sales'               => (double)$data->total_sales,
                    'rata_sales_3_bl'           => (double)$data->rata_sales_3_bulan,
                    'tanggal_sales_terakhir'    => trim($data->tanggal_faktur_terakhir),
                    'jumlah_sales_terakhir'     => (double)$data->jumlah_jual_terakhir,
                    'service_rate'              => (float)$data->service_rate,
                    'back_order'                => (double)$data->back_order,
                    'suggest_order'             => ((double)$data->suggest_order <= 0) ? 1 : (double)$data->suggest_order,
                    'total_price'               => (double)$data->het * (((double)$data->suggest_order <= 0) ? 1 : (double)$data->suggest_order),
                    'flag_campaign'             => strtoupper(trim($data->status_campaign)),
                ]);
            }

            $data = [
                'data'  => $data_suggestion_order
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function UseSuggestion(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'list_item'     => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih dealer_id terlebih dahulu');
            }

            $token = $request->header('Authorization');
            $formatToken = explode(" ", $token);
            $session_id = trim($formatToken[1]);

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(users.user_id, '') as user_id,
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

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer)) {
                return ApiResponse::responseWarning('Kode dealer tidak terdaftar, hubungi IT Programmer');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            DB::transaction(function () use ($kode_dealer, $user_id, $companyid) {
                DB::delete("delete
                            from    cart_dtlsuggesttmp
                            where   cart_dtlsuggesttmp.user_id=? and
                                    cart_dtlsuggesttmp.kd_dealer=? and
                                    cart_dtlsuggesttmp.companyid=?", [
                                        strtoupper(trim($user_id)),
                                        strtoupper(trim($kode_dealer)),
                                        strtoupper(trim($companyid)),
                                    ]);
            });

            if($request->get('list_item')) {
                $list_part = json_decode($request->get('list_item'));
                $list_part_suggest = [];
                $part_id_suggest = '';

                foreach($list_part as $result) {
                    if(trim($part_id_suggest) == '') {
                        $part_id_suggest = (int)$result->part_id;
                    } else {
                        $part_id_suggest .= ",".(int)$result->part_id;
                    }
                }
                dd($part_id_suggest);
                foreach($list_part as $result) {
                    $list_part_suggest[] = [
                        'user_id'   => strtoupper(trim($user_id)),
                        'kd_dealer' => strtoupper(trim($kode_dealer)),
                        'part_id'   => (int)$result->part_id,
                        'qty'       => (double)$result->qty,
                        'companyid' => strtoupper(trim($companyid))
                    ];
                }

                DB::transaction(function () use ($list_part_suggest) {
                    DB::table('cart_dtlsuggest')->insert($list_part_suggest);
                });


                return ApiResponse::responseSuccess('success', $request->all());
            }
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

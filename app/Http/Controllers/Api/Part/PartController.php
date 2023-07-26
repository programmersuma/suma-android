<?php

namespace App\Http\Controllers\Api\Part;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class PartController extends Controller {

    public function listMotorType(Request $request) {
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

            $sql = DB::table('typemotor')->lock('with (nolock)')
                    ->selectRaw("isnull(typemotor.id, 0) as id,
                                isnull(typemotor.typemkt, '') as code,
                                isnull(typemotor.ket, '') as name,
                                iif(isnull(typemotor_fav.typemkt, '')='', 0, 1) as favorite")
                    ->leftJoin(DB::raw('typemotor_fav with (nolock)'), function($join) use($user_id, $companyid) {
                        $join->on('typemotor_fav.typemkt', '=', 'typemotor.typemkt')
                            ->on('typemotor_fav.user_id', '=', DB::raw("'".strtoupper(trim($user_id))."'"))
                            ->on('typemotor_fav.companyid', '=', DB::raw("'".strtoupper(trim($companyid))."'"));
                    });

            if(!empty($request->get('search')) && trim($request->get('search') != '')) {
                $sql->where('typemotor.typemkt', 'like', strtoupper(trim($request->get('search'))).'%')
                    ->orWhere('typemotor.ket', 'like', strtoupper(trim($request->get('search'))).'%');
            }

            $sql = $sql->orderBy('typemotor.typemkt', 'asc')
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];

            $data_favorite = [];
            $data_list_motor = [];
            $jumlah_data = 0;

            foreach($data_result as $result) {
                if((int)$result->favorite == 1) {
                    $data_favorite[] = [
                        'id'    => (int)$result->id,
                        'code'  => strtoupper(trim($result->code)),
                        'name'  => strtoupper(trim($result->name)),
                    ];
                }
                if(empty('search') || trim($request->get('search') == '')) {
                    if((int)$jumlah_data == 0 && $request->get('page') == 1) {
                        $data_list_produk[] = [
                            'id'    => 0,
                            'code'  => 'ALL',
                            'name'  => 'SEMUA'
                        ];
                    }
                }
                $data_list_motor[] = [
                    'id'    => (int)$result->id,
                    'code'  => strtoupper(trim($result->code)),
                    'name'  => strtoupper(trim($result->name)),
                ];
                $jumlah_data = (int)$jumlah_data + 1;
            }

            $data = [
                'data' => [
                    'favorit'   => $data_favorite,
                    'list'      => $data_list_motor
                ]
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listItemGroup(Request $request) {
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

            $sql = DB::table('produk')->lock('with (nolock)')
                    ->selectRaw("isnull(produk.id_mobile, 0) as id,
                                isnull(produk.kd_produk, '') as kode_produk,
                                isnull(produk.nama, '') as nama_produk,
                                iif(isnull(produk_fav.kd_produk, '')='', 0, 1) as favorite")
                    ->leftJoin(DB::raw('produk_fav with (nolock)'), function($join) use($user_id, $companyid) {
                        $join->on('produk_fav.kd_produk', '=', 'produk.kd_produk')
                            ->on('produk_fav.user_id', '=', DB::raw("'".strtoupper(trim($user_id))."'"))
                            ->on('produk_fav.companyid', '=', DB::raw("'".strtoupper(trim($companyid))."'"));
                    });

            if(!empty('search') && trim($request->get('search') != '')) {
                $sql->where('produk.kd_produk', 'like', strtoupper(trim($request->get('search'))).'%')
                    ->orWhere('produk.nama', 'like', strtoupper(trim($request->get('search'))).'%');
            }

            $sql = $sql->orderBy('produk.nourut', 'asc')
                        ->paginate(10);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];

            $data_favorite = [];
            $data_list_produk = [];
            $jumlah_data = 0;

            foreach($data_result as $result) {
                if((int)$result->favorite == 1) {
                    $data_favorite[] = [
                        'id'    => (int)$result->id,
                        'code'  => strtoupper(trim($result->kode_produk)),
                        'name'  => strtoupper(trim($result->nama_produk))
                    ];
                }
                if(empty('search') || trim($request->get('search') == '')) {
                    if((int)$jumlah_data == 0 && $request->get('page') == 1) {
                        $data_list_produk[] = [
                            'id'    => 0,
                            'code'  => 'ALL',
                            'name'  => 'SEMUA'
                        ];
                    }
                }
                $data_list_produk[] = [
                    'id'    => (int)$result->id,
                    'code'  => strtoupper(trim($result->kode_produk)),
                    'name'  => strtoupper(trim($result->nama_produk))
                ];
                $jumlah_data = (int)$jumlah_data + 1;
            }

            $data = [
                'data' => [
                    'favorit'   => $data_favorite,
                    'list'      => $data_list_produk
                ]
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function partSearch(Request $request) {
        try {
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

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_dealer, '') as kode_dealer")
                    ->where('id', $request->get('ms_dealer_id'))
                    ->where('companyid', $companyid)
                    ->first();

            if(empty($sql->kode_dealer)) {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $filter_similarity = '';

            if (!empty($request->get('similarity'))) {
                $sql = DB::table('similarity')->lock('with (nolock)')
                        ->selectRaw("isnull(key_word, '') as key_word")
                        ->where('similarity', 'like', '%' . $request->get('similarity') . '%')
                        ->first();

                $filter_similarity = (!empty($sql->key_word)) ? trim($sql->key_word) : '';
            } else {
                $filter_similarity = '';
            }

            /* ========================================================================================== */
            /* Part Number Search */
            /* ========================================================================================== */
            $list_search_part = '';

            $sql = DB::table('part')->lock('with (nolock)')
                    ->selectRaw("isnull(part.kd_part, '') as part_number")
                    ->whereRaw("isnull(part.del_send, 0) = 0")
                    ->whereRaw("part.kd_sub <> 'DSTO'")
                    ->where('part.companyid', strtoupper(trim($companyid)));

            if(!empty($request->get('item_group')) || $request->get('item_group') != '') {
                if($request->get('item_group') != 0) {
                    $sql->leftJoin(DB::raw('sub with (nolock)'), function($join) {
                            $join->on('sub.kd_sub', '=', 'part.kd_sub');
                        });
                    $sql->leftJoin(DB::raw('produk with (nolock)'), function($join) {
                            $join->on('produk.kd_produk', '=', 'sub.kd_produk');
                        });
                    $sql->where('produk.id_mobile', $request->get('item_group'));
                }
            }

            if (!empty($request->get('motor_type')) && trim($request->get('motor_type')) != '') {
                if($request->get('motor_type') != 0) {
                    $sql->leftJoin(DB::raw('pvtm with (nolock)'), function($join) {
                        $join->on('pvtm.kd_part', '=', 'part.kd_part');
                    });
                    $sql->leftJoin(DB::raw('typemotor with (nolock)'), function($join) {
                        $join->on('typemotor.typemkt', '=', 'pvtm.typemkt');
                    });
                    $sql->where('typemotor.id', $request->get('motor_type'));
                }
            }

            if (!empty($request->get('part_number')) && trim($request->get('part_number')) != '') {
                $sql->where('part.kd_part', 'like', trim($request->get('part_number')).'%');
            }

            if (!empty($request->get('part_description')) && trim($request->get('part_description')) != '') {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('part.ket', 'like', '%'.trim($request->get('part_description')).'%')
                            ->orWhere('part.bhs_pasar', 'like', '%'.trim($request->get('part_description')).'%');
                });
            }

            if(!empty($filter_similarity) && trim($filter_similarity) != '') {
                $sql->where(function($query) use ($filter_similarity) {
                    return $query
                            ->where('part.ket', 'like', '%'.trim($filter_similarity).'%')
                            ->orWhere('part.bhs_pasar', 'like', '%'.trim($filter_similarity).'%');
                });
            }

            $result = $sql->limit(50)->get();

            $list_search_part = '';
            $data_part = [];
            $data_type_motor = [];
            $collection_data_part = new Collection();
            $result_search_part = [];

            foreach($result as $data) {
                if(strtoupper(trim($list_search_part)) == '') {
                    $list_search_part = "'".strtoupper(trim($data->part_number))."'";
                } else {
                    $list_search_part .= ",'".strtoupper(trim($data->part_number))."'";
                }
            }

            /* ========================================================================================== */
            /* Result Detail Part Number Search */
            /* ========================================================================================== */
            if(trim($list_search_part) != '') {
                $sql = "select	isnull(part.id, 0) as id, isnull(rtrim(part.kd_part), '') as part_number,
                                isnull(rtrim(part.ket), '') as part_description, isnull(part.het, 0) as het,
                                iif(isnull(part.frg, '')='F', 'FIX', 'REGULER') as frg,
                                isnull(part.kd_sub, '') as kode_sub,
                                isnull(rtrim(part.produk), '') as item_group,
                                case
                                    when isnull(part.kelas, '')='A' then 'FAST MOVING'
                                    when isnull(part.kelas, '')='B' then 'SEMI FAST MOVING'
                                    when isnull(part.kelas, '')='C' then 'SEMI SLOW MOVING'
                                    when isnull(part.kelas, '')='D' then 'SLOW MOVING'
                                    when isnull(part.kelas, '')='E' then 'DEATH STOCK'
                                    when isnull(part.kelas, '')='F' then 'ITEM DELETE'
                                    when isnull(part.kelas, '')='G' then 'SP AHM'
                                    when isnull(part.kelas, '')='H' then 'TRANS=0 (> 1 THN)'
                                end as kelas, isnull(part.bhs_pasar, '') as bhs_pasar,
                                isnull(part.type, '') as type, isnull(part.jenis, '') as jenis,
                                isnull(part.kategori, '') as kategori, isnull(part.pattern, '') as pattern,
                                isnull(pho.kd_part, '') as part_pho, isnull(pdirect.kd_part, '') as part_pdirect,
                                iif(isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0) < 0, 0,
                                    isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0)) as stock_total_part,
                                isnull(part.jml1dus, 0) as dus, isnull(camp.no_camp, '') as no_camp, isnull(camp.nm_camp, '') as nama_camp,
                                isnull(convert(varchar(10), camp.tgl_prd1, 107), '') as tanggal_awal_camp,
                                isnull(convert(varchar(10), camp.tgl_prd2, 107), '') as tanggal_akhir_camp,
                                iif(isnull(part_fav.kd_part, '')='', 0, 1) as is_love,
                                isnull(typemotor.typemkt, '') as typemkt, rtrim(typemotor.typemkt) + ' ' + rtrim(typemotor.ket) as type_motor,
                                isnull(bo.jumlah, 0) as jumlah_bo, isnull(convert(varchar(50), cast(faktur.tgl_faktur as date), 106), '') as tgl_faktur,
                                isnull(faktur.jml_jual, 0) as jumlah_faktur,
                                isnull(convert(varchar(50), cast(pof.tgl_pof as date), 106), '') as tgl_pof,
                                isnull(pof.jml_order, 0) as jumlah_pof
                        from
                        (
                            select	part.companyid, mspart.id, part.kd_part, part.ket, produk.nama as produk,
                                    part.frg, part.het, part.jml1dus, part.kelas, part.jenis, part.type,
                                    part.kategori, part.pattern, part.bhs_pasar, part.kd_sub,
                                    isnull(tbstlokasirak.stock, 0) -
                                        (isnull(stlokasi.min, 0) + isnull(stlokasi.in_transit, 0) +
                                            isnull(part.kanvas, 0) + isnull(part.min_gudang, 0) + isnull(part.in_transit, 0) +
                                                    isnull(part.min_htl, 0)) as stock
                            from
                            (
                                select	part.companyid, part.kd_part, part.ket, part.bhs_pasar, part.kd_sub,
                                        part.het, part.min_gudang, part.in_transit, part.min_htl, part.frg,
                                        part.konsinyasi, part.kanvas, part.jml1dus, part.kelas,
                                        part.jenis, part.type, part.kategori, part.pattern
                                from	part with (nolock)
                                where	part.companyid='".strtoupper(trim($companyid))."' and
                                        part.kd_part in (".$list_search_part.")
                            )	part
                                    inner join company with (nolock) on part.companyid=company.companyid
                                    inner join mspart with (nolock) on part.kd_part=mspart.kd_part
                                    left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                    left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                                    left join stlokasi with (nolock) on part.kd_part=stlokasi.kd_part and
                                                    company.kd_lokasi=stlokasi.kd_lokasi and
                                                    part.companyid=stlokasi.companyid
                                    left join tbstlokasirak with (nolock) on part.kd_part=tbstlokasirak.kd_part and
                                                    company.kd_lokasi=tbstlokasirak.kd_lokasi and
                                                    company.kd_rak=tbstlokasirak.kd_rak and
                                                    part.companyid=tbstlokasirak.companyid
                        )	part
                                left join minshare with (nolock) on part.kd_part=minshare.kd_part and
                                            part.companyid=minshare.companyid
                                left join pho with (nolock) on part.kd_part=pho.kd_part and
                                            part.companyid=pho.companyid
                                left join pdirect with (nolock) on part.kd_part=pdirect.kd_part and
                                            part.companyid=pdirect.companyid
                                left join part_fav with (nolock) on part.kd_part=part_fav.kd_part and
                                            part.companyid=part_fav.companyid and part_fav.user_id='".strtoupper(trim($user_id))."'
                                left join bo with (nolock) on part.kd_part=bo.kd_part and
                                            '".strtoupper(trim($kode_dealer))."'=bo.kd_dealer and part.companyid=bo.companyid
                                left join
                                (
                                    select	faktur.companyid, faktur.no, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                                    from
                                    (
                                        select	row_number() over(partition by faktur.kd_part
                                                    order by faktur.kd_part asc, faktur.tgl_faktur desc) as no,
                                                faktur.companyid, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                                        from
                                        (
                                            select	faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part,
                                                    sum(isnull(fakt_dtl.jml_jual, 0)) as jml_jual
                                            from
                                            (
                                                select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur
                                                from	faktur with (nolock)
                                                where	faktur.companyid='".strtoupper(trim($companyid))."' and
                                                        faktur.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                                        cast(faktur.tgl_faktur as date) >= dateadd(month, -3, convert(varchar(10), getdate(), 120))
                                            )	faktur
                                                    inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                                    faktur.companyid=fakt_dtl.companyid
                                            where	isnull(fakt_dtl.jml_jual, 0) > 0 and
                                                    fakt_dtl.kd_part in (".$list_search_part.")
                                            group by faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part
                                        )	faktur
                                    )	faktur
                                    where	isnull(faktur.no, 0) = 1
                                )	faktur on part.kd_part=faktur.kd_part and
                                                part.companyid=faktur.companyid
                                left join
                                (
                                    select	pof.companyid, pof.no, pof.kd_part, pof.tgl_pof, pof.jml_order
                                    from
                                    (
                                        select	row_number() over(partition by pof.kd_part
                                                    order by pof.kd_part asc, pof.tgl_pof desc) as no,
                                                pof.companyid, pof.kd_part, pof.tgl_pof, pof.jml_order
                                        from
                                        (
                                            select	pof.companyid, pof.tgl_pof, pof_dtl.kd_part,
                                                    sum(isnull(pof_dtl.jml_order, 0)) as jml_order
                                            from
                                            (
                                                select	pof.companyid, pof.no_pof, pof.tgl_pof
                                                from	pof with (nolock)
                                                where	pof.companyid='".strtoupper(trim($companyid))."' and
                                                        pof.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                                        cast(pof.tgl_pof as date) >= dateadd(month, -3, convert(varchar(10), getdate(), 120))
                                            )	pof
                                                    inner join pof_dtl with (nolock) on pof.no_pof=pof_dtl.no_pof and
                                                                    pof.companyid=pof_dtl.companyid
                                            where	isnull(pof_dtl.jml_order, 0) > 0 and
                                                    pof_dtl.kd_part in (".$list_search_part.")
                                            group by pof.companyid, pof.tgl_pof, pof_dtl.kd_part
                                        )	pof
                                    )	pof
                                    where	isnull(pof.no, 0) = 1
                                )	pof on part.kd_part=pof.kd_part and
                                            part.companyid=pof.companyid
                                left join
                                (
                                    select	camp.companyid, camp.no_camp, camp.nm_camp,
                                            camp.tgl_prd1, camp.tgl_prd2, camp.kd_part
                                    from
                                    (
                                        select	row_number() over(order by
                                                    camp.tgl_prd2 asc) as nomor,
                                                camp.companyid, camp.no_camp, camp.nm_camp,
                                                camp.tgl_prd1, camp.tgl_prd2,
                                                camp_dtl.kd_part
                                        from
                                        (
                                            select	camp.companyid, camp.no_camp, camp.nm_camp,
                                                    camp.tgl_prd1, camp.tgl_prd2
                                            from	camp with (nolock)
                                            where	camp.tgl_prd1 >= convert(varchar(10), getdate(), 120) and
                                                    camp.tgl_prd2 <= convert(varchar(10), getdate(), 120) and
                                                    camp.companyid='".strtoupper(trim($companyid))."'
                                        )	camp
                                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                                            camp.companyid=camp_dtl.companyid
                                        where	camp_dtl.kd_part in (".$list_search_part.")
                                    )	camp
                                    where	camp.nomor = 1
                                )   camp on part.kd_part=camp.kd_part and
                                                part.companyid=camp.companyid
                                left join
                                (
                                    select	pvtm.kd_part, pvtm.typemkt, typemotor.ket
                                    from
                                    (
                                        select	pvtm.companyid, pvtm.kd_part, pvtm.typemkt
                                        from	pvtm with (nolock)
                                        where	pvtm.kd_part in (".$list_search_part.")
                                    )	pvtm
                                            inner join typemotor with (nolock) on pvtm.typemkt=typemotor.typemkt
                                )	typemotor on part.kd_part=typemotor.kd_part
                        order by part.kd_part asc, typemotor.typemkt asc";

                $sql = DB::select($sql);


                foreach($sql as $data) {
                    $stock_part = '';
                    $hotline_flag = 'YES';

                    if(strtoupper(trim($data->kode_sub)) != 'DSTO') {
                        $hotline_flag = 'NO';
                    } else {
                        if(strtoupper(trim($data->part_pho)) != '') {
                            $hotline_flag = 'NO';
                        }
                    }

                    if((double)$data->stock_total_part <= 0) {
                        $stock_part = 'Not Available';
                    } else {
                        if(strtoupper(trim($role_id)) == "MD_H3_MGMT") {
                            $stock_part = 'Available '.number_format((double)$data->stock_total_part).' pcs';
                        } else {
                            $stock_part = 'Available';
                        }
                    }

                    $data_type_motor[] = [
                        'part_number'   => trim($data->part_number),
                        'type_motor'    => trim($data->type_motor)
                    ];

                    $collection_data_part->push((object) [
                        'id'                => (int)$data->id,
                        'ms_dealer_id'      => (int)$request->get('ms_dealer_id'),
                        'dealer_code'       => strtoupper(trim($kode_dealer)),
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'part_description'  => strtoupper(trim($data->part_description)),
                        'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                        'item_group'        => strtoupper(trim($data->item_group)),
                        'bhs_pasar'         => strtoupper(trim($data->bhs_pasar)),
                        'kelas'             => strtoupper(trim($data->kelas)),
                        'frg'               => strtoupper(trim($data->frg)),
                        'type'              => strtoupper(trim($data->type)),
                        'jenis'             => strtoupper(trim($data->jenis)),
                        'kategori'          => strtoupper(trim($data->kategori)),
                        'pattern'           => strtoupper(trim($data->pattern)),
                        'het'               => (double)$data->het,
                        'hotline_flag'      => strtoupper(trim($hotline_flag)),
                        'hotline_max_qty'   => (strtoupper(trim($hotline_flag)) == 'YES') ? 1 : 0,
                        'total_part'        => (double)$data->stock_total_part,
                        'dus'               => (double)$data->dus,
                        'is_love'           => (int)$data->is_love,
                        'is_campaign'       => (strtoupper(trim($data->no_camp)) == '') ? 0 : 1,
                        'nama_campaign'     => strtoupper(trim($data->nama_camp)),
                        'periode_campaign'  => strtoupper(trim($data->tanggal_awal_camp)).' s/d '.strtoupper(trim($data->tanggal_akhir_camp)),
                        'available_part'    => $stock_part,
                        'keterangan_bo'     => ((double)$data->jumlah_bo > 0) ? 'Part number ini sudah ada di BO sejumlah : '.$data->jumlah_bo.' pcs' : '',
                        'keterangan_faktur' => ((double)$data->jumlah_faktur > 0) ? 'Faktur terakhir tanggal : '.$data->tgl_faktur.', sejumlah : '.number_format($data->jumlah_faktur) : '',
                        'keterangan_pof'    => ((double)$data->jumlah_pof > 0) ? 'POF terakhir tanggal : '.$data->tgl_pof.', sejumlah : '.number_format($data->jumlah_pof) : '',
                    ]);
                }

                $part_number = '';
                foreach($collection_data_part as $collection) {
                    if ($part_number != $collection->part_number) {
                        $data_part[] = [
                            'id'                => (int)$collection->id,
                            'ms_dealer_id'      => (int)$collection->ms_dealer_id,
                            'dealer_code'       => strtoupper(trim($collection->dealer_code)),
                            'part_number'       => strtoupper(trim($collection->part_number)),
                            'part_description'  => strtoupper(trim($collection->part_description)),
                            'part_pictures'     => trim($collection->part_pictures),
                            'item_group'        => strtoupper(trim($collection->item_group)),
                            'bhs_pasar'         => strtoupper(trim($collection->bhs_pasar)),
                            'kelas'             => strtoupper(trim($collection->kelas)),
                            'frg'               => strtoupper(trim($collection->frg)),
                            'type'              => strtoupper(trim($collection->type)),
                            'jenis'             => strtoupper(trim($collection->jenis)),
                            'kategori'          => strtoupper(trim($collection->kategori)),
                            'pattern'           => strtoupper(trim($collection->pattern)),
                            'het'               => (double)$collection->het,
                            'type_motor'        => collect($data_type_motor)
                                                    ->where('part_number', strtoupper(trim($collection->part_number)))
                                                    ->values()
                                                    ->all(),
                            'hotline_flag'      => strtoupper(trim($collection->hotline_flag)),
                            'hotline_max_qty'   => (int)$collection->hotline_max_qty,
                            'total_part'        => (double)$collection->total_part,
                            'dus'               => (double)$collection->dus,
                            'is_love'           => (int)$collection->is_love,
                            'is_campaign'       => (int)$collection->is_campaign,
                            'nama_campaign'     => strtoupper(trim($collection->nama_campaign)),
                            'periode_campaign'  => strtoupper(trim($collection->periode_campaign)),
                            'available_part'    => trim($collection->available_part),
                            'keterangan_bo'     => trim($collection->keterangan_bo),
                            'keterangan_faktur' => trim($collection->keterangan_faktur),
                            'keterangan_pof'    => trim($collection->keterangan_pof),
                        ];
                        $part_number = $collection->part_number;
                    }
                }

                if(!empty($request->get('sorting'))) {
                    if($request->get('sorting') == 'part_number|asc') {
                        $result_search_part = collect($data_part)->sortBy('part_number');
                    } elseif ($request->get('sorting') == 'part_number|desc') {
                        $result_search_part = collect($data_part)->sortByDesc('part_number');
                    }

                    if ($request->get('sorting') == 'description|asc') {
                        $result_search_part = collect($data_part)->sortBy('part_description');
                    } elseif ($request->get('sorting') == 'description|desc') {
                        $result_search_part = collect($data_part)->sortByDesc('part_description');
                    }

                    if ($request->get('sorting') == 'available_part|a') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortBy('available_part');
                    } elseif ($request->get('sorting') == 'available_part|na') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortByDesc('available_part');
                    }

                    if ($request->get('sorting') == 'promo|yes') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortByDesc('is_campaign');
                    } elseif ($request->get('sorting') == 'promo|no') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortBy('is_campaign');
                    }
                } else {
                    $result_search_part = collect($data_part)->sortBy('part_number');
                }

                $result_search_part->values()->all();
            }

            return ApiResponse::responseSuccess('success', [ 'data' => $result_search_part ]);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listPartFavorite(Request $request) {
        try {
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

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_dealer, '') as kode_dealer")
                    ->where('id', $request->get('ms_dealer_id'))
                    ->where('companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer)) {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan, hubungi IT Programmer');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            /* ========================================================================================== */
            /* Part Number Favorite */
            /* ========================================================================================== */
            $list_search_part = '';

            $sql = "select	isnull(part_fav.kd_part, '') as part_number
                    from
                    (
                        select	part_fav.companyid, part_fav.kd_part
                        from	part_fav with (nolock)
                        where	part_fav.user_id=? and
                                part_fav.kd_dealer=? and
                                part_fav.companyid=?
                    )	part_fav
                            inner join part with (nolock) on part_fav.kd_part=part.kd_part and
                                        part_fav.companyid=part.companyid
                    where	isnull(part.del_send, 0)=0
                    order by part_fav.kd_part asc";

            $result = DB::select($sql, [ strtoupper(trim($user_id)), strtoupper(trim($kode_dealer)), strtoupper(trim($companyid)) ]);

            $list_search_part = '';
            $data_type_motor = [];
            $collection_data_part = new Collection();
            $result_part_favorite = [];

            foreach($result as $data) {
                if(strtoupper(trim($list_search_part)) == '') {
                    $list_search_part = "'".strtoupper(trim($data->part_number))."'";
                } else {
                    $list_search_part .= ",'".strtoupper(trim($data->part_number))."'";
                }
            }

            /* ========================================================================================== */
            /* Result Detail Part Number Favorite */
            /* ========================================================================================== */
            if(trim($list_search_part) != '') {
                $sql = "select	isnull(part.id, 0) as id, isnull(rtrim(part.kd_part), '') as part_number,
                                isnull(rtrim(part.ket), '') as part_description, isnull(part.het, 0) as het,
                                iif(isnull(part.frg, '')='F', 'FIX', 'REGULER') as frg,
                                isnull(part.kd_sub, '') as kode_sub,
                                isnull(rtrim(part.produk), '') as item_group,
                                case
                                    when isnull(part.kelas, '')='A' then 'FAST MOVING'
                                    when isnull(part.kelas, '')='B' then 'SEMI FAST MOVING'
                                    when isnull(part.kelas, '')='C' then 'SEMI SLOW MOVING'
                                    when isnull(part.kelas, '')='D' then 'SLOW MOVING'
                                    when isnull(part.kelas, '')='E' then 'DEATH STOCK'
                                    when isnull(part.kelas, '')='F' then 'ITEM DELETE'
                                    when isnull(part.kelas, '')='G' then 'SP AHM'
                                    when isnull(part.kelas, '')='H' then 'TRANS=0 (> 1 THN)'
                                end as kelas, isnull(part.bhs_pasar, '') as bhs_pasar,
                                isnull(part.type, '') as type, isnull(part.jenis, '') as jenis,
                                isnull(part.kategori, '') as kategori, isnull(part.pattern, '') as pattern,
                                isnull(pho.kd_part, '') as part_pho, isnull(pdirect.kd_part, '') as part_pdirect,
                                iif(isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0) < 0, 0,
                                    isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0)) as stock_total_part,
                                isnull(part.jml1dus, 0) as dus, isnull(camp.no_camp, '') as no_camp, isnull(camp.nm_camp, '') as nama_camp,
                                isnull(convert(varchar(10), camp.tgl_prd1, 107), '') as tanggal_awal_camp,
                                isnull(convert(varchar(10), camp.tgl_prd2, 107), '') as tanggal_akhir_camp,
                                iif(isnull(part_fav.kd_part, '')='', 0, 1) as is_love,
                                isnull(typemotor.typemkt, '') as typemkt, rtrim(typemotor.typemkt) + ' ' + rtrim(typemotor.ket) as type_motor,
                                isnull(bo.jumlah, 0) as jumlah_bo, isnull(convert(varchar(50), cast(faktur.tgl_faktur as date), 106), '') as tgl_faktur,
                                isnull(faktur.jml_jual, 0) as jumlah_faktur,
                                isnull(convert(varchar(50), cast(pof.tgl_pof as date), 106), '') as tgl_pof,
                                isnull(pof.jml_order, 0) as jumlah_pof
                        from
                        (
                            select	part.companyid, mspart.id, part.kd_part, part.ket, produk.nama as produk,
                                    part.frg, part.het, part.jml1dus, part.kelas, part.jenis, part.type,
                                    part.kategori, part.pattern, part.bhs_pasar, part.kd_sub,
                                    isnull(tbstlokasirak.stock, 0) -
                                        (isnull(stlokasi.min, 0) + isnull(stlokasi.in_transit, 0) +
                                            isnull(part.kanvas, 0) + isnull(part.min_gudang, 0) + isnull(part.in_transit, 0) +
                                                    isnull(part.min_htl, 0)) as stock
                            from
                            (
                                select	part.companyid, part.kd_part, part.ket, part.bhs_pasar, part.kd_sub,
                                        part.het, part.min_gudang, part.in_transit, part.min_htl, part.frg,
                                        part.konsinyasi, part.kanvas, part.jml1dus, part.kelas,
                                        part.jenis, part.type, part.kategori, part.pattern
                                from	part with (nolock)
                                where	part.companyid='".strtoupper(trim($companyid))."' and
                                        part.kd_part in (".$list_search_part.")
                            )	part
                                    inner join company with (nolock) on part.companyid=company.companyid
                                    inner join mspart with (nolock) on part.kd_part=mspart.kd_part
                                    left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                    left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                                    left join stlokasi with (nolock) on part.kd_part=stlokasi.kd_part and
                                                    company.kd_lokasi=stlokasi.kd_lokasi and
                                                    part.companyid=stlokasi.companyid
                                    left join tbstlokasirak with (nolock) on part.kd_part=tbstlokasirak.kd_part and
                                                    company.kd_lokasi=tbstlokasirak.kd_lokasi and
                                                    company.kd_rak=tbstlokasirak.kd_rak and
                                                    part.companyid=tbstlokasirak.companyid
                        )	part
                                left join minshare with (nolock) on part.kd_part=minshare.kd_part and
                                            part.companyid=minshare.companyid
                                left join pho with (nolock) on part.kd_part=pho.kd_part and
                                            part.companyid=pho.companyid
                                left join pdirect with (nolock) on part.kd_part=pdirect.kd_part and
                                            part.companyid=pdirect.companyid
                                left join part_fav with (nolock) on part.kd_part=part_fav.kd_part and
                                            part.companyid=part_fav.companyid and part_fav.user_id='".strtoupper(trim($user_id))."'
                                left join bo with (nolock) on part.kd_part=bo.kd_part and
                                            '".strtoupper(trim($kode_dealer))."'=bo.kd_dealer and part.companyid=bo.companyid
                                left join
                                (
                                    select	faktur.companyid, faktur.no, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                                    from
                                    (
                                        select	row_number() over(partition by faktur.kd_part
                                                    order by faktur.kd_part asc, faktur.tgl_faktur desc) as no,
                                                faktur.companyid, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                                        from
                                        (
                                            select	faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part,
                                                    sum(isnull(fakt_dtl.jml_jual, 0)) as jml_jual
                                            from
                                            (
                                                select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur
                                                from	faktur with (nolock)
                                                where	faktur.companyid='".strtoupper(trim($companyid))."' and
                                                        faktur.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                                        cast(faktur.tgl_faktur as date) >= dateadd(month, -3, convert(varchar(10), getdate(), 120))
                                            )	faktur
                                                    inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                                    faktur.companyid=fakt_dtl.companyid
                                            where	isnull(fakt_dtl.jml_jual, 0) > 0 and
                                                    fakt_dtl.kd_part in (".$list_search_part.")
                                            group by faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part
                                        )	faktur
                                    )	faktur
                                    where	isnull(faktur.no, 0) = 1
                                )	faktur on part.kd_part=faktur.kd_part and
                                                part.companyid=faktur.companyid
                                left join
                                (
                                    select	pof.companyid, pof.no, pof.kd_part, pof.tgl_pof, pof.jml_order
                                    from
                                    (
                                        select	row_number() over(partition by pof.kd_part
                                                    order by pof.kd_part asc, pof.tgl_pof desc) as no,
                                                pof.companyid, pof.kd_part, pof.tgl_pof, pof.jml_order
                                        from
                                        (
                                            select	pof.companyid, pof.tgl_pof, pof_dtl.kd_part,
                                                    sum(isnull(pof_dtl.jml_order, 0)) as jml_order
                                            from
                                            (
                                                select	pof.companyid, pof.no_pof, pof.tgl_pof
                                                from	pof with (nolock)
                                                where	pof.companyid='".strtoupper(trim($companyid))."' and
                                                        pof.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                                        cast(pof.tgl_pof as date) >= dateadd(month, -3, convert(varchar(10), getdate(), 120))
                                            )	pof
                                                    inner join pof_dtl with (nolock) on pof.no_pof=pof_dtl.no_pof and
                                                                    pof.companyid=pof_dtl.companyid
                                            where	isnull(pof_dtl.jml_order, 0) > 0 and
                                                    pof_dtl.kd_part in (".$list_search_part.")
                                            group by pof.companyid, pof.tgl_pof, pof_dtl.kd_part
                                        )	pof
                                    )	pof
                                    where	isnull(pof.no, 0) = 1
                                )	pof on part.kd_part=pof.kd_part and
                                            part.companyid=pof.companyid
                                left join
                                (
                                    select	camp.companyid, camp.no_camp, camp.nm_camp,
                                            camp.tgl_prd1, camp.tgl_prd2, camp.kd_part
                                    from
                                    (
                                        select	row_number() over(order by
                                                    camp.tgl_prd2 asc) as nomor,
                                                camp.companyid, camp.no_camp, camp.nm_camp,
                                                camp.tgl_prd1, camp.tgl_prd2,
                                                camp_dtl.kd_part
                                        from
                                        (
                                            select	camp.companyid, camp.no_camp, camp.nm_camp,
                                                    camp.tgl_prd1, camp.tgl_prd2
                                            from	camp with (nolock)
                                            where	camp.tgl_prd1 >= convert(varchar(10), getdate(), 120) and
                                                    camp.tgl_prd2 <= convert(varchar(10), getdate(), 120) and
                                                    camp.companyid='".strtoupper(trim($companyid))."'
                                        )	camp
                                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                                            camp.companyid=camp_dtl.companyid
                                        where	camp_dtl.kd_part in (".$list_search_part.")
                                    )	camp
                                    where	camp.nomor = 1
                                )   camp on part.kd_part=camp.kd_part and
                                                part.companyid=camp.companyid
                                left join
                                (
                                    select	pvtm.kd_part, pvtm.typemkt, typemotor.ket
                                    from
                                    (
                                        select	pvtm.companyid, pvtm.kd_part, pvtm.typemkt
                                        from	pvtm with (nolock)
                                        where	pvtm.kd_part in (".$list_search_part.")
                                    )	pvtm
                                            inner join typemotor with (nolock) on pvtm.typemkt=typemotor.typemkt
                                )	typemotor on part.kd_part=typemotor.kd_part
                        order by part.kd_part asc, typemotor.typemkt asc";

                $sql = DB::select($sql);


                foreach($sql as $data) {
                    $stock_part = '';
                    $hotline_flag = 'YES';

                    if(strtoupper(trim($data->kode_sub)) != 'DSTO') {
                        $hotline_flag = 'NO';
                    } else {
                        if(strtoupper(trim($data->part_pho)) != '') {
                            $hotline_flag = 'NO';
                        }
                    }

                    if((double)$data->stock_total_part <= 0) {
                        $stock_part = 'Not Available';
                    } else {
                        if(strtoupper(trim($role_id)) == "MD_H3_MGMT") {
                            $stock_part = 'Available '.number_format((double)$data->stock_total_part).' pcs';
                        } else {
                            $stock_part = 'Available';
                        }
                    }

                    $data_type_motor[] = [
                        'part_number'   => trim($data->part_number),
                        'type_motor'    => trim($data->type_motor)
                    ];

                    $collection_data_part->push((object) [
                        'id'                => (int)$data->id,
                        'ms_dealer_id'      => (int)$request->get('ms_dealer_id'),
                        'dealer_code'       => strtoupper(trim($kode_dealer)),
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'part_description'  => strtoupper(trim($data->part_description)),
                        'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                        'item_group'        => strtoupper(trim($data->item_group)),
                        'bhs_pasar'         => strtoupper(trim($data->bhs_pasar)),
                        'kelas'             => strtoupper(trim($data->kelas)),
                        'frg'               => strtoupper(trim($data->frg)),
                        'type'              => strtoupper(trim($data->type)),
                        'jenis'             => strtoupper(trim($data->jenis)),
                        'kategori'          => strtoupper(trim($data->kategori)),
                        'pattern'           => strtoupper(trim($data->pattern)),
                        'het'               => (double)$data->het,
                        'hotline_flag'      => strtoupper(trim($hotline_flag)),
                        'hotline_max_qty'   => (strtoupper(trim($hotline_flag)) == 'YES') ? 1 : 0,
                        'total_part'        => (double)$data->stock_total_part,
                        'dus'               => (double)$data->dus,
                        'is_love'           => (int)$data->is_love,
                        'is_campaign'       => (strtoupper(trim($data->no_camp)) == '') ? 0 : 1,
                        'nama_campaign'     => strtoupper(trim($data->nama_camp)),
                        'periode_campaign'  => strtoupper(trim($data->tanggal_awal_camp)).' s/d '.strtoupper(trim($data->tanggal_akhir_camp)),
                        'available_part'    => $stock_part,
                        'keterangan_bo'     => ((double)$data->jumlah_bo > 0) ? 'Part number ini sudah ada di BO sejumlah : '.$data->jumlah_bo.' pcs' : '',
                        'keterangan_faktur' => ((double)$data->jumlah_faktur > 0) ? 'Faktur terakhir tanggal : '.$data->tgl_faktur.', sejumlah : '.number_format($data->jumlah_faktur) : '',
                        'keterangan_pof'    => ((double)$data->jumlah_pof > 0) ? 'POF terakhir tanggal : '.$data->tgl_pof.', sejumlah : '.number_format($data->jumlah_pof) : '',
                    ]);
                }

                $part_number = '';
                foreach($collection_data_part as $collection) {
                    if ($part_number != $collection->part_number) {
                        $data_part[] = [
                            'id'                => (int)$collection->id,
                            'ms_dealer_id'      => (int)$collection->ms_dealer_id,
                            'dealer_code'       => strtoupper(trim($collection->dealer_code)),
                            'part_number'       => strtoupper(trim($collection->part_number)),
                            'part_description'  => strtoupper(trim($collection->part_description)),
                            'part_pictures'     => trim($collection->part_pictures),
                            'item_group'        => strtoupper(trim($collection->item_group)),
                            'bhs_pasar'         => strtoupper(trim($collection->bhs_pasar)),
                            'kelas'             => strtoupper(trim($collection->kelas)),
                            'frg'               => strtoupper(trim($collection->frg)),
                            'type'              => strtoupper(trim($collection->type)),
                            'jenis'             => strtoupper(trim($collection->jenis)),
                            'kategori'          => strtoupper(trim($collection->kategori)),
                            'pattern'           => strtoupper(trim($collection->pattern)),
                            'het'               => (double)$collection->het,
                            'type_motor'        => collect($data_type_motor)
                                                    ->where('part_number', strtoupper(trim($collection->part_number)))
                                                    ->values()
                                                    ->all(),
                            'hotline_flag'      => strtoupper(trim($collection->hotline_flag)),
                            'hotline_max_qty'   => (int)$collection->hotline_max_qty,
                            'total_part'        => (double)$collection->total_part,
                            'dus'               => (double)$collection->dus,
                            'is_love'           => (int)$collection->is_love,
                            'is_campaign'       => (int)$collection->is_campaign,
                            'nama_campaign'     => strtoupper(trim($collection->nama_campaign)),
                            'periode_campaign'  => strtoupper(trim($collection->periode_campaign)),
                            'available_part'    => trim($collection->available_part),
                            'keterangan_bo'     => trim($collection->keterangan_bo),
                            'keterangan_faktur' => trim($collection->keterangan_faktur),
                            'keterangan_pof'    => trim($collection->keterangan_pof),
                        ];
                        $part_number = $collection->part_number;
                    }
                }

                if(!empty($request->get('sorting'))) {
                    if($request->get('sorting') == 'part_number|asc') {
                        $result_search_part = collect($data_part)->sortBy('part_number');
                    } elseif ($request->get('sorting') == 'part_number|desc') {
                        $result_search_part = collect($data_part)->sortByDesc('part_number');
                    }

                    if ($request->get('sorting') == 'description|asc') {
                        $result_search_part = collect($data_part)->sortBy('part_description');
                    } elseif ($request->get('sorting') == 'description|desc') {
                        $result_search_part = collect($data_part)->sortByDesc('part_description');
                    }

                    if ($request->get('sorting') == 'available_part|a') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortBy('available_part');
                    } elseif ($request->get('sorting') == 'available_part|na') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortByDesc('available_part');
                    }

                    if ($request->get('sorting') == 'promo|yes') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortByDesc('is_campaign');
                    } elseif ($request->get('sorting') == 'promo|no') {
                        $result_search_part = collect($data_part)->sortBy('part_number')->sortBy('is_campaign');
                    }
                } else {
                    $result_search_part = collect($data_part)->sortBy('part_number');
                }

                $result_search_part->values()->all();

                $result_part_favorite = [
                    'data'  => $result_search_part
                ];
            }

            return ApiResponse::responseSuccess('success', $result_part_favorite);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function addPartFavorite(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'id_part' => 'required',
                'is_love' => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Isi data part favorit secara lengkap');
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

            $sql = DB::table('mspart')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_part, '') as part_number")
                    ->where('id', $request->get('id_part'))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Id part pada part number yang anda pilih tidak terdaftar');
            }

            $part_number = strtoupper(trim($sql->part_number));

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_dealer, '') as kode_dealer")
                    ->where('id', $request->get('ms_dealer_id'))
                    ->where('companyid', $companyid)
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Id dealer pada data dealer yang anda pilih tidak terdaftar');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            DB::transaction(function () use ($request, $user_id, $kode_dealer, $part_number, $companyid) {
                DB::insert('exec SP_PartFavorite_Simpan ?,?,?,?,?', [
                    strtoupper(trim($user_id)), strtoupper(trim($kode_dealer)),
                    strtoupper(trim($part_number)), (int)$request->get('is_love'),
                    strtoupper(trim($companyid))
                ]);
            });

            $data = [
                'part_number'   => strtoupper(trim($part_number)),
                'kode_dealer'   => strtoupper(trim($kode_dealer)),
                'is_love'       => (int)$request->get('is_love')
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listBackOrder(Request $request) {
        try {
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

            $filter_salesman = $request->get('salesman');
            $filter_dealer = $request->get('dealer');
            $filter_part_number = $request->get('part_number');

            if ($role_id == "MD_H3_SM") {
                $filter_salesman = $user_id;
            }

            if ($role_id == "D_H3") {
                $filter_dealer = $user_id;
            }

            $sql = DB::table('bo')->lock('with (nolock)')
                    ->selectRaw("isnull(bo.kd_sales, '') as 'kode_sales', isnull(salesman.nm_sales, '') as 'nama_sales',
                                isnull(bo.kd_dealer, '') as 'kode_dealer', isnull(dealer.nm_dealer, '') as 'nama_dealer',
                                isnull(bo.kd_part, '') as 'part_number', isnull(part.ket, '') as 'nama_part',
                                isnull(produk.nama, '') as 'produk', isnull(bo.kd_tpc, '') as 'kode_tpc',
                                isnull(bo.jumlah, 0) as 'jumlah'")
                    ->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                        $join->on('salesman.kd_sales', '=', 'bo.kd_sales')
                            ->on('salesman.companyid', '=', 'bo.companyid');
                    })
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'bo.kd_dealer')
                            ->on('dealer.companyid', '=', 'bo.companyid');
                    })
                    ->leftJoin(DB::raw('part with (nolock)'), function($join) {
                        $join->on('part.kd_part', '=', 'bo.kd_part')
                            ->on('part.companyid', '=', 'bo.companyid');
                    })
                    ->leftJoin(DB::raw('sub with (nolock)'), function($join) {
                        $join->on('sub.kd_sub', '=', 'part.kd_sub');
                    })
                    ->leftJoin(DB::raw('produk with (nolock)'), function($join) {
                        $join->on('produk.kd_produk', '=', 'sub.kd_produk');
                    })
                    ->whereRaw("isnull(bo.jumlah, 0) > 0")
                    ->where('bo.companyid', strtoupper(trim($companyid)));

            if (!empty($filter_salesman) || trim($filter_salesman) != '') {
                $sql->where('bo.kd_sales', strtoupper(trim($filter_salesman)));
            }

            if (!empty($filter_dealer) || trim($filter_dealer) != '') {
                $sql->where('bo.kd_dealer', strtoupper(trim($filter_dealer)));
            }

            if (!empty($filter_part_number) || trim($filter_part_number) != '') {
                $sql->where('bo.kd_part', 'like', strtoupper(trim($filter_part_number)).'%');
            }

            $sql = $sql->orderByRaw('bo.kd_sales asc, bo.kd_dealer asc, bo.kd_part asc')
                        ->paginate(10);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $data_bo = [];

            foreach($data_result as $result) {
                $data_bo[] = [
                    'kode_sales'    => trim($result->kode_sales),
                    'nama_sales'    => trim($result->nama_sales),
                    'kode_dealer'   => trim($result->kode_dealer),
                    'nama_dealer'   => trim($result->nama_dealer),
                    'part_pictures' => config('constants.app.app_images_url').'/'.strtoupper(trim($result->part_number)).'.jpg',
                    'part_number'   => trim($result->part_number),
                    'nama_part'     => trim($result->nama_part),
                    'produk'        => trim($result->produk),
                    'kode_tpc'      =>trim($result->kode_tpc),
                    'jumlah_bo'     => (int)$result->jumlah
                ];
            }
            return ApiResponse::responseSuccess('success', $data_bo);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function skemaPembelian(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'id_part_cart'  => 'required',
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih dealer dan part number terlebih dahulu');
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

            $sql = DB::table('mspart')->lock('with (nolock)')
                    ->selectRaw("isnull(mspart.kd_part, '') as part_number")
                    ->where('id', $request->get('id_part_cart'))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Part number tidak ditemukan');
            }
            $part_number = strtoupper(trim($sql->part_number));

            $sql = "select	isnull(part.kd_part, '') as part_number, isnull(part.ket, '') as part_description,
                            isnull(part.produk, '') as item_group, isnull(part.jml1dus, 0) as jml_dus, isnull(part.het, 0) as  het,
                            iif(isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0) < 0, 0,
                                isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0)) as stock_total_part,
                            isnull(convert(varchar, pof.tanggal_order_terakhir, 107), '') as tanggal_order_terakhir,
                            isnull(convert(varchar, faktur_terakhir.tanggal_faktur_terakhir, 107), '') as tanggal_faktur_terakhir,
                            isnull(pof.jumlah_order_terakhir, 0) as jumlah_order_terakhir,
                            isnull(faktur_terakhir.jumlah_order_faktur_terakhir, 0) as jumlah_order_faktur_terakhir,
                            isnull(faktur_terakhir.jumlah_jual_terakhir, 0) as jumlah_jual_terakhir,
                            isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0)  as total_order,
                            isnull(faktur.jumlah_jual, 0) as total_sales,
                            isnull(bo.jumlah, 0) as back_order,
                            cast(round(iif(isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0) <= 0, 0,
                                (isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0)) / 3), 0) as decimal(13, 0)) as rata_order_3_bulan,
                            cast(round(iif(isnull(faktur.jumlah_jual, 0) <= 0, 0, isnull(faktur.jumlah_jual, 0) / 3), 0) as decimal(13, 0)) as rata_sales_3_bulan,
                            cast(iif(isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0) <= 0, 0,
                                (isnull(faktur.jumlah_jual, 0) /
                                    (isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0))) * 100) as decimal(5,2)) as service_rate,
                            cast(round(iif(isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0) <= 0, 0,
                                (((isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0)) / 3) +
                                    round(((((isnull(faktur.jumlah_order, 0) + isnull(bo.jumlah, 0)) / 3) * 10) / 100), 0)) - isnull(bo.jumlah, 0)
                            ), 0) as decimal(13,0)) as suggest_order,
                            iif(isnull(campaign.kd_part, '') = '' , 'NO', 'YES') as status_campaign

                    from
                    (
                        select	part.companyid, part.kd_part, part.ket, produk.nama as produk, part.jml1dus, part.het,
                                isnull(tbstlokasirak.stock, 0) -
                                    (isnull(stlokasi.min, 0) + isnull(stlokasi.in_transit, 0) +
                                        isnull(part.kanvas, 0) + isnull(part.min_gudang, 0) +
                                        isnull(part.in_transit, 0) + isnull(part.min_htl, 0)) as stock
                        from
                        (
                            select	part.companyid, part.kd_part, part.ket, part.het, part.kd_sub, part.jml1dus,
                                    part.in_transit, part.kanvas, part.min_gudang, part.min_htl
                            from	part with (nolock)
                            where	part.kd_part='".strtoupper(trim($part_number))."' and
                                    part.companyid='".strtoupper(trim($companyid))."'
                        )	part
                                inner join company with (nolock) on part.companyid=company.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                                left join stlokasi with (nolock) on part.kd_part=stlokasi.kd_part and
                                            company.kd_lokasi=stlokasi.kd_lokasi and
                                            part.companyid=stlokasi.companyid
                                left join tbstlokasirak with (nolock) on part.kd_part=tbstlokasirak.kd_part and
                                            company.kd_lokasi=tbstlokasirak.kd_lokasi and
                                            company.kd_rak=tbstlokasirak.kd_rak and
                                            part.companyid=tbstlokasirak.companyid
                    )	part
                    left join minshare  with (nolock) on part.companyid=minshare.companyid and
                                part.kd_part=minshare.kd_part
                    left join bo with (nolock) on part.companyid=bo.companyid and
                                part.kd_part=bo.kd_part and bo.kd_dealer='".strtoupper(trim($kode_dealer))."'
                    left join
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
                        where	fakt_dtl.kd_part='".strtoupper(trim($part_number))."'
                        group by faktur.companyid, fakt_dtl.kd_part
                    )	faktur on part.companyid=faktur.companyid and
                                    part.kd_part=faktur.kd_part
                    left join
                    (
                        select	faktur.companyid, faktur.tgl_faktur as tanggal_faktur_terakhir,
                                faktur.kd_part, faktur.jml_order as jumlah_order_faktur_terakhir,
                                faktur.jml_jual as jumlah_jual_terakhir
                        from
                        (
                            select	faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part,
                                    fakt_dtl.jml_jual, fakt_dtl.jml_order,
                                    row_number() over(order by faktur.companyid asc, faktur.tgl_faktur desc) as nomor
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur
                                from	faktur with (nolock)
                                where	faktur.companyid='".strtoupper(trim($companyid))."' and
                                        faktur.kd_dealer='".strtoupper(trim($kode_dealer))."'
                            )	faktur
                                    inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where	isnull(fakt_dtl.jml_jual, 0) > 0 and
                                    fakt_dtl.kd_part='".strtoupper(trim($part_number))."'
                        )	faktur
                        where	faktur.nomor = 1
                    )	faktur_terakhir on part.companyid=faktur_terakhir.companyid and
                                part.kd_part=faktur_terakhir.kd_part
                    left join
                    (
                        select	pof.companyid, pof.tgl_pof as tanggal_order_terakhir,
                                pof.kd_part, pof.jml_order as jumlah_order_terakhir
                        from
                        (
                            select	pof.companyid, pof.tgl_pof,
                                    pof_dtl.kd_part, pof_dtl.jml_order,
                                    row_number() over(order by pof.companyid asc, pof.tgl_pof desc) as nomor
                            from
                            (
                                select	pof.companyid, pof.no_pof, pof.tgl_pof
                                from	pof with (nolock)
                                where	pof.companyid='".strtoupper(trim($companyid))."' and
                                        pof.kd_dealer='".strtoupper(trim($kode_dealer))."'
                            )	pof
                                    inner join pof_dtl with (nolock) on pof.no_pof=pof_dtl.no_pof and
                                                pof.companyid=pof_dtl.companyid
                            where	pof_dtl.kd_part='".strtoupper(trim($part_number))."'
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
                        where	camp_dtl.kd_part='".strtoupper(trim($part_number))."'
                    )	campaign on part.companyid=campaign.companyid and
                                    part.kd_part=campaign.kd_part";

            $result = DB::select($sql);

            $jumlah_data = 0;
            $data_skema_pembelian = new Collection();

            foreach($result as $data) {
                $jumlah_data = (double)$jumlah_data + 1;

                if((double)$data->stock_total_part > 0) {
                    if(strtoupper(trim($role_id)) == 'MD_H3_MGMT') {
                        $available_part = 'Available '.trim($data->stock_total_part).' pcs';
                    } else {
                        $available_part = 'Available';
                    }
                } else {
                    $available_part = 'Not Available';
                }

                $data_skema_pembelian->push((object) [
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
                    'flag_campaign'             => strtoupper(trim($data->status_campaign)),
                ]);
            }

            if((double)$jumlah_data <= 0) {
                return ApiResponse::responseWarning('Data part number didalam cart tidak ditemukan');
            }

            return ApiResponse::responseSuccess('success', $data_skema_pembelian->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

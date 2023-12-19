<?php

namespace App\Http\Controllers\Api\Promo;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class PromoController extends Controller
{
    public function listBrosur(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('camp')->lock('with (nolock)')
                    ->selectRaw("isnull(camp.companyid, '') as companyid,
                                isnull(substring(camp.no_camp, 3, 3), 0) as id,
                                isnull(camp.no_camp, '') as no_camp,
                                isnull(camp.nm_camp, '') as nama_camp,
                                isnull(convert(varchar(10), camp.tgl_prd1, 120), '') as tanggal_awal,
                                isnull(convert(varchar(10), camp.tgl_prd2, 120), '') as tanggal_akhir,
                                isnull(camp.picture, '') as picture,
                                isnull(camp.usertime, '') as usertime")
                    ->where('camp.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->whereRaw("camp.tgl_prd1 <= convert(varchar(10), getdate(), 120) and
                                camp.tgl_prd2 >= convert(varchar(10), getdate(), 120)")
                    ->orderByRaw("camp.tgl_prd2 asc")
                    ->paginate(15);

            $data_campaign = [];
            $result = collect($sql)->toArray();
            $data_result = $result['data'];

            foreach($data_result as $data) {
                $data_campaign[] = [
                    'id'            => (int)$data->id,
                    'title'         => strtoupper(trim($data->nama_camp)),
                    'photo'         => (trim($data->picture) == '') ? 'https://suma-honda.id/assets/images/logo/bg_logo_suma.png' : trim($data->picture), // photo
                    'promo_start'   => trim($data->tanggal_awal),
                    'promo_end'     => trim($data->tanggal_akhir),
                    'content'       => strtoupper(trim($data->nama_camp)),
                    'note'          => strtoupper(trim($data->nama_camp)),
                    'code'          => strtoupper(trim($data->no_camp))
                ];
            }

            $data = [
                'data'  => $data_campaign
            ];
            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listBrosurDetail(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'code'      => 'required',
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi dan kode campaign terlebih dahulu');
            }

            /* ================================================================== */
            /* Ambil part number promo */
            /* ================================================================== */
            $sql = DB::connection($request->get('divisi'))
                    ->table('camp')->lock('with (nolock)')
                    ->selectRaw("isnull(camp_dtl.kd_part, '') as part_number")
                    ->leftJoin(DB::raw('camp_dtl with (nolock)'), function($join) {
                        $join->on('camp_dtl.no_camp', '=', 'camp.no_camp')
                            ->on('camp_dtl.companyid', '=', 'camp.companyid');
                    })
                    ->where('camp.no_camp', strtoupper(trim($request->get('code'))))
                    ->where('camp.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->groupByRaw("camp_dtl.kd_part")
                    ->orderByRaw("camp_dtl.kd_part asc")
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $list_part_number = '';

            foreach($data_result as $data) {
                if(strtoupper(trim($list_part_number)) == '') {
                    $list_part_number = "'".strtoupper(trim($data->part_number))."'";
                } else {
                    $list_part_number .= ",'".strtoupper(trim($data->part_number))."'";
                }
            }
            $data_promo = [];

            if(strtoupper(trim($list_part_number)) != '') {
                $sql = "select	isnull(camp.companyid, '') as companyid, isnull(substring(camp.no_camp, 3, 3), 0) as id,
                                isnull(camp.no_camp, '') as code, isnull(camp.nm_camp, '') as name,
                                isnull(camp_dtl.kd_part, '') as part_number, isnull(part.ket, '') as part_description,
                                isnull(part.het, 0) as het, iif(isnull(part.frg, '')='F', 'FIX', 'REGULER') as frg,
                                isnull(part.kd_sub, '') as kode_sub, isnull(rtrim(produk.nama), '') as item_group,
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
                                isnull(camp_dtl.point, 0) as poin,
                                isnull(convert(varchar(10), camp.tgl_prd1, 120), '') as begin_effdate,
                                isnull(convert(varchar(10), camp.tgl_prd2, 120), '') as end_effdate,
                                isnull(part.jml1dus, 0) as dus
                        from
                        (
                            select	*
                            from	camp with (nolock)
                            where	camp.no_camp=? and camp.companyid=?
                        )	camp
                                inner join company with (nolock) on camp.companyid=company.companyid
                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                            camp.companyid=camp_dtl.companyid
                                left join part with (nolock) on part.kd_part=camp_dtl.kd_part and
                                            camp.companyid=part.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                                left join pho with (nolock) on camp_dtl.kd_part=pho.kd_part and
                                            camp.companyid=pho.companyid
                                left join pdirect with (nolock) on part.kd_part=pdirect.kd_part and
                                            part.companyid=pdirect.companyid
                        where   camp_dtl.kd_part in (".strtoupper(trim($list_part_number)).")
                        order by camp.tgl_prd2 asc, camp_dtl.kd_part asc, camp.no_camp asc";

                $result = DB::connection($request->get('divisi'))->select($sql, [ strtoupper(trim($request->get('code'))), strtoupper(trim($request->userlogin['companyid'])) ]);


                foreach($result as $data) {
                    $hotline_flag = 'YES';

                    if(strtoupper(trim($data->kode_sub)) != 'DSTO') {
                        $hotline_flag = 'NO';
                    } else {
                        if(strtoupper(trim($data->part_pho)) != '') {
                            $hotline_flag = 'NO';
                        }
                    }

                    $data_promo[] = [
                        'id'                => (int)$data->id,
                        'code'              => strtoupper(trim($data->code)),
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'part_description'  => strtoupper(trim($data->part_description)),
                        'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                        'item_group'        => strtoupper(trim($data->item_group)),
                        'het'               => (double)$data->het,
                        'bhs_pasar'         => strtoupper(trim($data->bhs_pasar)),
                        'kelas'             => strtoupper(trim($data->kelas)),
                        'frg'               => strtoupper(trim($data->frg)),
                        'type'              => strtoupper(trim($data->type)),
                        'jenis'             => strtoupper(trim($data->jenis)),
                        'kategori'          => strtoupper(trim($data->kategori)),
                        'pattern'           => strtoupper(trim($data->pattern)),
                        'dus'               => (double)$data->dus,
                        'hotline_flag'      => strtoupper(trim($hotline_flag)),
                        'hotline_max_qty'   => (strtoupper(trim($hotline_flag)) == 'YES') ? 1 : 0,
                        'keterangan'        => (double)$data->poin.' POIN/PCS',
                        'begin_effdate'     => $data->begin_effdate,
                        'end_effdate'       => $data->end_effdate,
                        'description'       => strtoupper(trim($data->name)),
                        'note'              => strtoupper(trim($data->name))
                    ];
                }
            }
            return ApiResponse::responseSuccess('success', [ 'data' => $data_promo ]);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listPromoPart(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }
            /* ================================================================== */
            /* Ambil part number promo */
            /* ================================================================== */
            $sql = DB::connection($request->get('divisi'))
                    ->table('camp')->lock('with (nolock)')
                    ->selectRaw("isnull(camp_dtl.kd_part, '') as part_number")
                    ->leftJoin(DB::raw('camp_dtl with (nolock)'), function($join) {
                        $join->on('camp_dtl.no_camp', '=', 'camp.no_camp')
                            ->on('camp_dtl.companyid', '=', 'camp.companyid');
                    })
                    ->whereRaw("camp.tgl_prd1 <= convert(varchar(10), getdate(), 120) and
                                camp.tgl_prd2 >= convert(varchar(10), getdate(), 120)")
                    ->where('camp.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->groupByRaw("camp_dtl.kd_part")
                    ->orderByRaw("camp_dtl.kd_part asc")
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $list_part_number = '';

            foreach($data_result as $data) {
                if(strtoupper(trim($list_part_number)) == '') {
                    $list_part_number = "'".strtoupper(trim($data->part_number))."'";
                } else {
                    $list_part_number .= ",'".strtoupper(trim($data->part_number))."'";
                }
            }
            $data_promo = [];

            if(strtoupper(trim($list_part_number)) != '') {
                $sql = "select	isnull(camp.companyid, '') as companyid, isnull(substring(camp.no_camp, 3, 3), 0) as id,
                                isnull(camp.no_camp, '') as code, isnull(camp.nm_camp, '') as name,
                                isnull(camp_dtl.kd_part, '') as part_number, isnull(part.ket, '') as part_description,
                                isnull(part.het, 0) as het, iif(isnull(part.frg, '')='F', 'FIX', 'REGULER') as frg,
                                isnull(part.kd_sub, '') as kode_sub, isnull(rtrim(produk.nama), '') as item_group,
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
                                isnull(camp_dtl.point, 0) as poin,
                                isnull(convert(varchar(10), camp.tgl_prd1, 120), '') as begin_effdate,
                                isnull(convert(varchar(10), camp.tgl_prd2, 120), '') as end_effdate,
                                isnull(part.jml1dus, 0) as dus
                        from
                        (
                            select	*
                            from	camp with (nolock)
                            where	camp.companyid=? and
                                    camp.tgl_prd1 <= convert(varchar(10), getdate(), 120) and
                                    camp.tgl_prd2 >= convert(varchar(10), getdate(), 120)
                        )	camp
                                inner join company with (nolock) on camp.companyid=company.companyid
                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                            camp.companyid=camp_dtl.companyid
                                left join part with (nolock) on part.kd_part=camp_dtl.kd_part and
                                            camp.companyid=part.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                                left join pho with (nolock) on camp_dtl.kd_part=pho.kd_part and
                                            camp.companyid=pho.companyid
                                left join pdirect with (nolock) on part.kd_part=pdirect.kd_part and
                                            part.companyid=pdirect.companyid
                        where   camp_dtl.kd_part in (".strtoupper(trim($list_part_number)).")
                        order by camp.tgl_prd2 asc, camp_dtl.kd_part asc, camp.no_camp asc";

                $result = DB::connection($request->get('divisi'))->select($sql, [ strtoupper(trim($request->userlogin['companyid']))  ]);


                foreach($result as $data) {
                    $hotline_flag = 'YES';

                    if(strtoupper(trim($data->kode_sub)) != 'DSTO') {
                        $hotline_flag = 'NO';
                    } else {
                        if(strtoupper(trim($data->part_pho)) != '') {
                            $hotline_flag = 'NO';
                        }
                    }

                    $data_promo[] = [
                        'id'                => (int)$data->id,
                        'code'              => strtoupper(trim($data->code)),
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'part_description'  => strtoupper(trim($data->part_description)),
                        'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                        'item_group'        => strtoupper(trim($data->item_group)),
                        'het'               => (double)$data->het,
                        'bhs_pasar'         => strtoupper(trim($data->bhs_pasar)),
                        'kelas'             => strtoupper(trim($data->kelas)),
                        'frg'               => strtoupper(trim($data->frg)),
                        'type'              => strtoupper(trim($data->type)),
                        'jenis'             => strtoupper(trim($data->jenis)),
                        'kategori'          => strtoupper(trim($data->kategori)),
                        'pattern'           => strtoupper(trim($data->pattern)),
                        'dus'               => (double)$data->dus,
                        'hotline_flag'      => strtoupper(trim($hotline_flag)),
                        'hotline_max_qty'   => (strtoupper(trim($hotline_flag)) == 'YES') ? 1 : 0,
                        'keterangan'        => (double)$data->poin.' POIN/PCS',
                        'begin_effdate'     => $data->begin_effdate,
                        'end_effdate'       => $data->end_effdate,
                        'description'       => strtoupper(trim($data->name)),
                        'note'              => strtoupper(trim($data->name))
                    ];
                }
            }
            return ApiResponse::responseSuccess('success', [ 'data' => $data_promo ]);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

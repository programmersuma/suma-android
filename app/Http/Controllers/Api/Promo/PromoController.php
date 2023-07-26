<?php

namespace App\Http\Controllers\Api\Promo;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

class PromoController extends Controller
{
    public function listBrosur(Request $request) {
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

            $companyid = strtoupper(trim($sql->companyid));

            $sql = DB::table('camp')->lock('with (nolock)')
                    ->selectRaw("isnull(camp.companyid, '') as companyid,
                                isnull(substring(camp.no_camp, 3, 3), 0) as id,
                                isnull(camp.no_camp, '') as no_camp,
                                isnull(camp.nm_camp, '') as nama_camp,
                                isnull(convert(varchar(10), camp.tgl_prd1, 120), '') as tanggal_awal,
                                isnull(convert(varchar(10), camp.tgl_prd2, 120), '') as tanggal_akhir,
                                isnull(camp.picture, '') as picture,
                                isnull(camp.usertime, '') as usertime")
                    ->where('camp.companyid', strtoupper(trim($companyid)))
                    ->whereRaw("camp.tgl_prd1 <= convert(varchar(10), getdate(), 120) and
                                camp.tgl_prd2 >= convert(varchar(10), getdate(), 120)")
                    ->orderByRaw("camp.tgl_prd2 asc")
                    ->get();

            $data_campaign = [];

            foreach($sql as $data) {
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

    public function listPromoPart(Request $request) {
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

            /* ================================================================== */
            /* Ambil part number promo */
            /* ================================================================== */
            $sql  = "select	isnull(camp.kd_part, '') as part_number,
                            isnull(typemotor.typemkt, '') + ' - ' +
                                isnull(typemotor.ket, '') as type_motor
                    from
                    (
                        select	camp_dtl.kd_part
                        from
                        (
                            select	camp.companyid, camp.no_camp
                            from	camp with (nolock)
                            where	camp.companyid=? and
                                    camp.tgl_prd1 <= convert(varchar(10), getdate(), 120) and
                                    camp.tgl_prd2 >= convert(varchar(10), getdate(), 120)
                        )	camp
                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                            camp.companyid=camp_dtl.companyid
                        group by camp_dtl.kd_part
                    )	camp
                            inner join pvtm with (nolock) on camp.kd_part=pvtm.kd_part
                            inner join typemotor with (nolock) on pvtm.typemkt=typemotor.typemkt
                    group by camp.kd_part, typemotor.typemkt, typemotor.ket
                    order by camp.kd_part asc";

            $data_type_motor = DB::select($sql, [ strtoupper(trim($companyid)) ]);

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
                    order by camp.tgl_prd2 asc, camp_dtl.kd_part asc, camp.no_camp asc";

            $result = DB::select($sql, [ strtoupper(trim($companyid))  ]);
            $data_promo = [];

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

            return ApiResponse::responseSuccess('success', [ 'data' => $data_promo ]);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

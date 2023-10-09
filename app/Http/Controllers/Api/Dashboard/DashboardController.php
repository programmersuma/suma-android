<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    public function index(Request $request) {
        $validate = Validator::make($request->all(), [
            'year'      => 'required|string',
            'month'     => 'required|string',
            'category'  => 'required|string',
            'divisi'    => 'required|string',
        ]);

        if ($validate->fails()) {
            return ApiResponse::responseWarning('Data divisi, kategori, tahun, dan bulan harus terisi');
        }

        $kode_mkr = strtoupper(trim($request->userlogin['user_id']));

        if (strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_KORSM') {
            $sql = DB::connection($request->get('divisi'))
                    ->table("superspv")->lock('with (nolock)')
                    ->selectRaw("isnull(superspv.kd_spv, '') as kode_spv")
                    ->where('superspv.nm_spv', strtoupper(trim($request->userlogin['user_id'])))
                    ->where('superspv.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->first();

            if(empty($sql->kode_spv) || trim($sql->kode_spv) == '') {
                return ApiResponse::responseWarning('Kode supervisor anda tidak ditemukan, hubungi IT Programmer');
            }

            $kode_mkr = strtoupper(trim($sql->kode_spv));
        }

        if(strtoupper(trim($request->get('category'))) == 'SALESMAN') {
            // ======================================================================
            // RESULT RETURN
            // ======================================================================
            if(strtoupper(trim($request->get('divisi'))) == 'SQLSRV_GENERAL') {
                return $this->dashboardSalesmanFdr($request, $request->get('year'), $request->get('month'),
                        $request->get('item_group'), strtoupper(trim($request->userlogin['role_id'])), strtoupper(trim($kode_mkr)),
                        (int)$request->userlogin['id'], strtoupper(trim($request->userlogin['user_id'])),
                        strtoupper(trim($request->userlogin['companyid'])));
            } else {
                return $this->dashboardSalesman($request, $request->get('year'), $request->get('month'),
                        $request->get('item_group'), strtoupper(trim($request->userlogin['role_id'])),
                        strtoupper(trim($kode_mkr)), (int)$request->userlogin['id'],
                        strtoupper(trim($request->userlogin['user_id'])),
                        strtoupper(trim($request->userlogin['companyid'])));
            }
        } elseif (strtoupper(trim($request->get('category'))) == 'DEALER') {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id' => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Isi data dealer terlebih dahulu');
            }

            // ======================================================================
            // RESULT RETURN
            // ======================================================================
            return $this->dashboardDealer($request, $request->get('year'), $request->get('month'), $request->get('ms_dealer_id'),
                            $request->get('item_group'), strtoupper(trim($request->userlogin['role_id'])),
                            strtoupper(trim($request->userlogin['companyid'])));
        } else {
            if (strtoupper(trim($request->userlogin['role_id'])) != 'MD_H3_MGMT') {
                return ApiResponse::responseWarning('Anda tidak memiliki akses untuk masuk ke halaman ini');
            }
            $validate = Validator::make($request->all(), [
                'item_group' => 'required|string'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Isi data produk terlebih dahulu');
            }

            // ======================================================================
            // RESULT RETURN
            // ======================================================================
            return $this->dashboardManagement($request, $request->get('year'), $request->get('month'), $request->get('item_group'),
                            strtoupper(trim($request->userlogin['companyid'])));
        }
    }

    public function dashboardSalesman($request, $year, $month, $item_group, $role_id, $kode_mkr, $id_user, $user_id, $companyid) {
        $sql = "select  cast(isnull(sum(target_jual.amount_target_total), 0) as decimal(18,0)) as total_target,
                        cast(isnull(sum(faktur.amount_faktur_total), 0) - isnull(sum(retur.amount_total), 0) as decimal(18,0)) as total_omzet,
                        cast(isnull(sum(faktur.amount_faktur_total), 0) as decimal(18,0)) as total_penjualan,
                        cast(isnull(sum(retur.amount_total), 0) as decimal(18,0)) as total_retur,
                        cast(iif(isnull(sum(target_jual.amount_target_total), 0) <= 0, 0,
                            (((isnull(sum(faktur.amount_faktur_total), 0) - isnull(sum(retur.amount_total), 0)) / isnull(sum(target_jual.amount_target_total), 0)) * 100)) as decimal(18,2)) as total_prosentase,
                        cast(isnull(sum(campaign.poin_campaign), 0) as decimal(18,0)) as pencapaian_campaign,
                        isnull(sum(jumlah_visit.jumlah_visit), 0) as jumlah_realisasi_visit,
                        isnull(sum(target_visit.target_visit), 0) as target_realisasi_visit,
                        cast(isnull(sum(target_jual.amount_target_ksjs), 0) as decimal(18,0)) as target_handle,
                        cast(isnull(sum(faktur.amount_faktur_ksjs), 0) - isnull(sum(retur.amount_retur_ksjs), 0) as decimal(18,0)) as omzet_handle,
                        cast(iif(isnull(sum(target_jual.amount_target_ksjs), 0) <= 0, 0,
                            ((isnull(sum(faktur.amount_faktur_ksjs), 0) - isnull(sum(retur.amount_retur_ksjs), 0))  / isnull(sum(target_jual.amount_target_ksjs), 0)) * 100) as decimal(10,2)) as prosentase_handle,
                        cast(isnull(sum(target_jual.amount_target_mpm), 0) as decimal(18,0)) as target_non_handle,
                        cast(isnull(sum(faktur.amount_faktur_mpm), 0) - isnull(sum(retur.amount_retur_mpm), 0) as decimal(18,0)) as omzet_non_handle,
                        cast(iif(isnull(sum(target_jual.amount_target_mpm), 0) <= 0, 0,
                            ((isnull(sum(faktur.amount_faktur_mpm), 0) - isnull(sum(retur.amount_retur_mpm), 0)) / isnull(sum(target_jual.amount_target_mpm), 0)) * 100) as decimal(10,2)) as prosentase_non_handle,
                        cast(isnull(sum(target_jual.amount_target_tube), 0) as decimal(18,0)) as target_tube,
                        cast(isnull(sum(faktur.amount_faktur_tube), 0) - isnull(sum(retur.amount_retur_tube), 0) as decimal(18,0)) as omzet_tube,
                        cast(iif(isnull(sum(target_jual.amount_target_tube), 0) <= 0, 0,
                            ((isnull(sum(faktur.amount_faktur_tube), 0) - isnull(sum(retur.amount_retur_tube), 0)) / isnull(sum(target_jual.amount_target_tube), 0)) * 100) as decimal(10,2)) as prosentase_tube,
                        cast(isnull(sum(target_jual.amount_target_oli), 0) as decimal(18,0)) as target_oli,
                        cast(isnull(sum(faktur.amount_faktur_oli), 0) - isnull(sum(retur.amount_retur_oli), 0) as decimal(18,0)) as omzet_oli,
                        cast(iif(isnull(sum(target_jual.amount_target_oli), 0) <= 0, 0,
                            ((isnull(sum(faktur.amount_faktur_oli), 0) - isnull(sum(retur.amount_retur_oli), 0)) / isnull(sum(target_jual.amount_target_oli), 0)) * 100) as decimal(10,2)) as prosentase_oli
                from
                (
                    select	salesman.companyid, salesman.kd_sales
                    from	salesman with (nolock)
                    where	salesman.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and salesman.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	salesman
                left join
                (
                    select	target_jual.companyid, target_jual.kd_sales,
                            sum(target_jual.target_pcs) As 'pcs_target_total', sum(target_jual.target_amount) as 'amount_target_total',
                            sum(iif(produk.level='AHM' and produk.kd_mkr='G', target_jual.target_amount, 0)) as 'amount_target_ksjs',
                            sum(iif(produk.level='MPM' and produk.kd_mkr='G', target_jual.target_amount, 0)) as 'amount_target_mpm',
                            sum(iif(produk.level='AHM' and produk.kd_mkr='I', target_jual.target_amount, 0)) as 'amount_target_tube',
                            sum(iif(produk.level='AHM' and produk.kd_mkr='J', target_jual.target_amount, 0)) as 'amount_target_oli'
                    from
                    (
                        select	target_jual.companyid, target_jual.kd_sales, target_jual.kd_produk,
                                iif('".strtoupper(trim($role_id))."'='MD_H3_SM', target_jual.target, target_jual.target3) as 'target_amount',
                                iif('".strtoupper(trim($role_id))."'='MD_H3_SM', target_jual.target2, target_jual.target4) as 'target_pcs'
                        from	target_jual with (nolock)
                                    left join salesman with (nolock) on target_jual.kd_sales=salesman.kd_sales and
                                            target_jual.companyid=salesman.companyid
                        where	target_jual.companyid='".strtoupper(trim($companyid))."' and
                                target_jual.tahun='".$year."' and
                                target_jual.bulan='".$month."' ";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and target_jual.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	target_jual
                            left join produk with (nolock) on target_jual.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by target_jual.companyid, target_jual.kd_sales
                    )	target_jual on salesman.companyid=target_jual.companyid and
                                    salesman.kd_sales=target_jual.kd_sales
                    left join
                    (
                        select	faktur.companyid, faktur.kd_sales,
                                sum(faktur.jml_jual) As 'pcs_faktur_total', sum(faktur.amount) as 'amount_faktur_total',
                                sum(iif(produk.level='AHM' and produk.kd_mkr='G', faktur.jml_jual, 0)) as 'pcs_faktur_ksjs',
                                sum(iif(produk.level='AHM' and produk.kd_mkr='G', faktur.amount, 0)) as 'amount_faktur_ksjs',
                                sum(iif(produk.level='MPM' and produk.kd_mkr='G', faktur.jml_jual, 0)) as 'pcs_faktur_mpm',
                                sum(iif(produk.level='MPM' and produk.kd_mkr='G', faktur.amount, 0)) as 'amount_faktur_mpm',
                                sum(iif(produk.level='AHM' and produk.kd_mkr='I', faktur.jml_jual, 0)) as 'pcs_faktur_tube',
                                sum(iif(produk.level='AHM' and produk.kd_mkr='I', faktur.amount, 0)) as 'amount_faktur_tube',
                                sum(iif(produk.level='AHM' and produk.kd_mkr='J', faktur.jml_jual, 0)) as 'pcs_faktur_oli',
                                sum(iif(produk.level='AHM' and produk.kd_mkr='J', faktur.amount, 0)) as 'amount_faktur_oli'
                        from
                        (
                            select	faktur.companyid, faktur.kd_sales, faktur.kd_part, faktur.jml_jual,
                                    isnull(faktur.total, 0) -
                                        iif(isnull(faktur_total.total_faktur, 0) <= 0, 0,
                                            round(((isnull(faktur.discrp, 0) / isnull(faktur_total.total_faktur, 0))), 0)) -
                                        iif(isnull(faktur_total.total_faktur, 0) <= 0, 0,
                                            round(((isnull(faktur.discrp1, 0) / isnull(faktur_total.total_faktur, 0))), 0))  as 'amount'
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.kd_sales, fakt_dtl.kd_part,
                                        fakt_dtl.jml_jual, faktur.discrp, faktur.discrp1,
                                        round(isnull(fakt_dtl.jumlah, 0) - ((isnull(fakt_dtl.jumlah, 0) * isnull(faktur.disc2, 0)) / 100), 0) As 'total'
                                from
                                (
                                    select	faktur.companyid, faktur.no_faktur, faktur.kd_sales, faktur.disc2,
                                            faktur.discrp, faktur.discrp1
                                    from	faktur with (nolock)
                                                left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                                            faktur.companyid=salesman.companyid
                                    where	year(faktur.tgl_faktur)='".$year."' and
                                            month(faktur.tgl_faktur)='".$month."' and
                                            faktur.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and faktur.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	faktur
                                left join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                            faktur.companyid=fakt_dtl.companyid
                        where isnull(fakt_dtl.jml_jual, 0) > 0
                    )	faktur
                    left join
                    (
                        select	faktur.companyid, faktur.no_faktur, count(fakt_dtl.no_faktur) as total_faktur
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur
                            from	faktur with (nolock)
                                        left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                                    faktur.companyid=salesman.companyid
                            where	year(faktur.tgl_faktur)='".$year."' and month(faktur.tgl_faktur)='".$month."' and
                                    (isnull(faktur.discrp, 0) > 0 or isnull(faktur.discrp1, 0) > 0) and
                                    faktur.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and faktur.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	faktur
                                left join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                            faktur.companyid=fakt_dtl.companyid
                        where isnull(fakt_dtl.jml_jual, 0) > 0
                        group by faktur.companyid, faktur.no_faktur
                    )	faktur_total on faktur.companyid=faktur_total.companyid and
                                    faktur.no_faktur=faktur_total.no_faktur
                )	faktur
                        left join part with (nolock) on faktur.kd_part=part.kd_part and
                                    faktur.companyid=part.companyid
                        left join sub with (nolock) on part.kd_sub=sub.kd_sub
                        left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by faktur.companyid, faktur.kd_sales
            )	faktur on salesman.companyid=faktur.companyid and
                        salesman.kd_sales=faktur.kd_sales
            left join
            (
                select	rtoko.companyid, rtoko.kd_sales,
                        sum(rtoko_dtl.jumlah) As 'pcs_total', sum(isnull(rtoko_dtl.jumlah, 0) * isnull(part.hrg_pokok, 0)) as 'amount_total',
                        sum(iif(produk.level='AHM' and produk.kd_mkr='G', isnull(rtoko_dtl.jumlah, 0), 0)) as 'pcs_retur_ksjs',
                        sum(iif(produk.level='AHM' and produk.kd_mkr='G', isnull(rtoko_dtl.jumlah, 0) * isnull(part.hrg_pokok, 0), 0)) as 'amount_retur_ksjs',
                        sum(iif(produk.level='MPM' and produk.kd_mkr='G', isnull(rtoko_dtl.jumlah, 0), 0)) as 'pcs_retur_mpm',
                        sum(iif(produk.level='MPM' and produk.kd_mkr='G', isnull(rtoko_dtl.jumlah, 0) * isnull(part.hrg_pokok, 0), 0)) as 'amount_retur_mpm',
                        sum(iif(produk.level='AHM' and produk.kd_mkr='I', isnull(rtoko_dtl.jumlah, 0), 0)) as 'pcs_retur_tube',
                        sum(iif(produk.level='AHM' and produk.kd_mkr='I', isnull(rtoko_dtl.jumlah, 0) * isnull(part.hrg_pokok, 0), 0)) as 'amount_retur_tube',
                        sum(iif(produk.level='AHM' and produk.kd_mkr='J', isnull(rtoko_dtl.jumlah, 0), 0)) as 'pcs_retur_oli',
                        sum(iif(produk.level='AHM' and produk.kd_mkr='J', isnull(rtoko_dtl.jumlah, 0) * isnull(part.hrg_pokok, 0), 0)) as 'amount_retur_oli'
                from
                (
                    select	rtoko.companyid, rtoko.no_retur, rtoko.kd_sales
                    from	rtoko with (nolock)
                                left join salesman with (nolock) on rtoko.kd_sales=salesman.kd_sales and
                                            rtoko.companyid=salesman.companyid
                    where	year(rtoko.tanggal)='".$year."' and month(rtoko.tanggal)='".$month."' and
                            rtoko.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and rtoko.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	rtoko
                        left join rtoko_dtl with (nolock) on rtoko.no_retur=rtoko_dtl.no_retur and
                                    rtoko.companyid=rtoko_dtl.companyid
                        left join part with (nolock) on rtoko_dtl.kd_part=part.kd_part and
                                    rtoko.companyid=part.companyid
                        left join sub with (nolock) on part.kd_sub=sub.kd_sub
                        left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by rtoko.companyid, rtoko.kd_sales
            )	retur on salesman.companyid=retur.companyid and
                        salesman.kd_sales=retur.kd_sales
            left join
            (
                select	camp.companyid, camp.kd_sales, sum(camp.poin_campaign) as poin_campaign
                from
                (
                    select	faktur.companyid, faktur.kd_sales, faktur.kd_part,
                            sum(isnull(faktur.jml_jual, 0) * isnull(camp.point, 0)) as poin_campaign
                    from
                    (
                        select	faktur.companyid, faktur.kd_sales, fakt_dtl.kd_part, sum(fakt_dtl.jml_jual) as jml_jual
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.kd_sales
                            from	faktur with (nolock)
                                        left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                                    faktur.companyid=salesman.companyid
                            where	year(faktur.tgl_faktur)='".$year."' and month(faktur.tgl_faktur)='".$month."' and
                                    faktur.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and faktur.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	faktur
                                inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                            faktur.companyid=fakt_dtl.companyid
                        where	isnull(fakt_dtl.jml_jual, 0) > 0
                        group by faktur.companyid, faktur.kd_sales, fakt_dtl.kd_part
                    )	faktur
                    left join
                    (
                        select	camp.companyid, camp_dtl.kd_part, sum(camp_dtl.point) as point
                        from
                        (
                            select	camp.companyid, camp.no_camp
                            from	camp with (nolock)
                            where	camp.companyid='".strtoupper(trim($companyid))."' and
                                    camp.tgl_prd1 <= convert(varchar(10), getdate(), 120) and
                                    camp.tgl_prd2 >= convert(varchar(10), getdate(), 120)
                        )	camp
                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                            camp.companyid=camp_dtl.companyid
                        group by camp.companyid, camp_dtl.kd_part
                    )	camp on faktur.kd_part=camp.kd_part and
                                faktur.companyid=camp.companyid
                    group by faktur.companyid, faktur.kd_sales, faktur.kd_part
                )	camp
                        left join part with (nolock) on camp.kd_part=part.kd_part and
                                    camp.companyid=part.companyid
                        left join sub with (nolock) on part.kd_sub=sub.kd_sub
                        left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by camp.companyid, camp.kd_sales
            )	campaign on salesman.companyid=campaign.companyid and
                            salesman.kd_sales=campaign.kd_sales
            left join
            (
                select	visit_date.companyid, visit_date.kd_sales,
                        count(visit_date.kd_visit) as 'target_visit'
                from	visit_date with (nolock)
                            left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                        visit_date.companyid=salesman.companyid
                where	visit_date.companyid='".strtoupper(trim($companyid))."' and
                        year(visit_date.tanggal)='".$year."' and
                        month(visit_date.tanggal)='".$month."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and visit_date.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " group by visit_date.companyid, visit_date.kd_sales
            )	target_visit on salesman.companyid=target_visit.companyid and
                                salesman.kd_sales=target_visit.kd_sales
            left join
            (
                select	visit.companyid, visit.kd_sales, count(visit.kd_visit) as 'jumlah_visit'
                from
                (
                    select	visit_date.companyid, visit_date.kd_sales, visit_date.kd_visit
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales
                        from	visit_date with (nolock)
                                    left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                                visit_date.companyid=salesman.companyid
                        where	visit_date.companyid='".strtoupper(trim($companyid))."' and
                                year(visit_date.tanggal)='".$year."' and
                                month(visit_date.tanggal)='".$month."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and visit_date.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	visit_date
                        inner join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                    visit_date.companyid=visit.companyid
                group by visit_date.companyid, visit_date.kd_sales, visit_date.kd_visit
            )	visit
            group by visit.companyid, visit.kd_sales
        )	jumlah_visit on salesman.companyid=jumlah_visit.companyid and
                            salesman.kd_sales=jumlah_visit.kd_sales
        group by salesman.companyid ";

        $dashboard_salesman = DB::connection($request->get('divisi'))->select($sql);

        // ====================================================================================
        // Ranking Salesman
        // ====================================================================================
        $sql = "select	isnull(salesman.id_sales, 0) as id,
                        isnull(salesman_rank.rank, 0) as rank,
                        isnull(rtrim(users.role_id), '') as role_id,
                        isnull(rtrim(users.photo), '') as photo,
                        isnull(rtrim(salesman.kd_sales), '') as code,
                        isnull(rtrim(salesman.nm_sales), '') as name,
                        isnull(salesman_rank.type, '') as type,
                        isnull(salesman_rank.peringkat, '') as status,
                        isnull(salesman_rank.target, 0) as target,
                        isnull(salesman_rank.amount, 0) - isnull(salesman_rank.retur, 0) as omzet,
                        isnull(salesman_rank.prosentase, 0) as prosentase
                from
                (
                    select	salesman_rank.companyid, salesman_rank.tahun, salesman_rank.bulan,
                            salesman_rank.rank, salesman_rank.kd_sales, salesman_rank.target,
                            salesman_rank.amount, salesman_rank.retur, salesman_rank.prosentase,
                            salesman_rank.type, salesman_rank.peringkat
                    from	salesman_rank with (nolock)
                    where	salesman_rank.tahun='".$year."' and salesman_rank.bulan='".$month."' and
                            salesman_rank.companyid='".strtoupper(trim($companyid))."' and
                            (isnull(salesman_rank.amount, 0) - isnull(salesman_rank.retur, 0) > 0 or isnull(salesman_rank.target, 0) > 0)";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and salesman_rank.kd_sales='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	salesman_rank
                        left join salesman with (nolock) on salesman_rank.kd_sales=salesman.kd_sales and
                                    salesman_rank.companyid=salesman.companyid
                        left join users with (nolock) on salesman_rank.kd_sales=users.user_id and
                                    salesman_rank.companyid=users.companyid";

        if(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " where salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " order by isnull(salesman_rank.rank, 0) asc";

        $salesman_rank = DB::connection($request->get('divisi'))->select($sql);

        $data_my_rank = new Collection();
        $list_salesman_rank = [];

        $photo_default = config('constants.app.app_images_url').'/users/no-image.png';

        foreach($salesman_rank as $data) {
            $prosentase = number_format($data->omzet).' / '.number_format($data->target).' = '.number_format($data->prosentase, 2).'%';

            if(strtoupper((trim($role_id))) == 'MD_H3_SM') {
                if (strtoupper(trim($data->code)) == strtoupper(trim($kode_mkr))) {
                    $data_my_rank->push((object) [
                        'id'        => (int)$data->id,
                        'rank'      => (int)$data->rank,
                        'photo'     => (!empty($data->photo) && $data->photo != '') ? trim($data->photo) : $photo_default,
                        'name'      => trim($data->code).' • '.trim($data->name),
                        'type'      => trim($data->type),
                        'peringkat' => 'Peringkat '.$data->rank,
                        'status'    => trim($data->status),
                        'keterangan' => trim($prosentase)
                    ]);
                }
            }

            $list_salesman_rank[] = [
                'id'        => (int)$data->id,
                'rank'      => (int)$data->rank,
                'photo'     => (!empty($data->photo) && $data->photo != '') ? trim($data->photo) : $photo_default,
                'name'      => trim($data->code).' • '.trim($data->name),
                'type'      => trim($data->type),
                'peringkat' => 'Peringkat '.$data->rank,
                'status'    => trim($data->status),
                'keterangan' => trim($prosentase)
            ];
        }

        $kode_my_rank = $data_my_rank->first();

        if (empty($kode_my_rank)) {
            $data_my_rank->push((object) [
                'id'        => (int)$id_user,
                'rank'      => 0,
                'photo'     => (!empty($data->photo) && $data->photo != '') ? trim($data->photo) : $photo_default,
                'name'      => strtoupper(trim($user_id)),
                'type'      => "Bertahan",
                'peringkat' => "∞",
                'status'    => "Bertahan",
                'keterangan' => strtoupper(trim($role_id)),
            ]);
        }

        $data_dashboard = new Collection();

        foreach ($dashboard_salesman as $result) {
            $prosentase_visit = (empty($result->target_realisasi_visit) || (double)$result->target_realisasi_visit <= 0) ? 0 :
                                    ((double)$result->jumlah_realisasi_visit / (double)$result->target_realisasi_visit) * 100;
            $data_dashboard->push((object) [
                'total_target'              => (float)$result->total_target,
                'total_penjualan'           => (float)$result->total_penjualan,
                'total_retur'               => (float)$result->total_retur,
                'total_omzet'               => (float)$result->total_omzet,
                'total_prosentase'          => (float)$result->total_prosentase,
                'pencapaian_campaign'       => (float)$result->pencapaian_campaign,
                'target_handle'             => (float)$result->target_handle,
                'omzet_handle'              => (float)$result->omzet_handle,
                'prosentase_handle'         => (float)$result->prosentase_handle,
                'target_non_handle'         => (float)$result->target_non_handle,
                'omzet_non_handle'          => (float)$result->omzet_non_handle,
                'prosentase_non_handle'     => (float)$result->prosentase_non_handle,
                'target_tube'               => (float)$result->target_tube,
                'omzet_tube'                => (float)$result->omzet_tube,
                'prosentase_tube'           => (float)$result->prosentase_tube,
                'target_oli'                => (float)$result->target_oli,
                'omzet_oli'                 => (float)$result->omzet_oli,
                'prosentase_oli'            => (float)$result->prosentase_oli,
                'realisasi_visit'           => number_format($result->jumlah_realisasi_visit).'/'.number_format($result->target_realisasi_visit).'/'.number_format($prosentase_visit, 2) . "%",
                'my_rank'                   => $data_my_rank->first(),
                'sales_man_rank'            => $list_salesman_rank

            ]);
        }

        return ApiResponse::responseSuccess('success', $data_dashboard->first());
    }

    public function dashboardSalesmanFdr($request, $year, $month, $item_group, $role_id, $kode_mkr, $id_user, $user_id, $companyid) {
        $sql = "select	omzet.companyid,
                        cast(sum(omzet.target_amount) as decimal(18,0)) as 'target_amount',
                        cast(sum(omzet.target_pcs) as decimal(18,0)) as 'target_pcs',
                        cast(sum(omzet.penjualan_amount) as decimal(18,0)) as 'penjualan_amount',
                        cast(sum(omzet.penjualan_pcs) as decimal(18,0)) as 'penjualan_pcs',
                        cast(sum(omzet.retur_amount) as decimal(18,0)) as 'retur_amount',
                        cast(sum(omzet.retur_pcs) as decimal(18,0)) as 'retur_pcs',
                        cast(sum(omzet.omzet_amount) as decimal(18,0)) as 'omzet_amount',
                        cast(sum(omzet.omzet_pcs) as decimal(18,0)) as 'omzet_pcs',
                        cast(iif(sum(omzet.target_amount) <= 0, 0,
                            ((sum(omzet.penjualan_amount) - sum(omzet.retur_amount)) / sum(omzet.target_amount)) * 100) as decimal(10,2)) as 'prosentase_amount',
                        cast(iif(sum(omzet.target_pcs) <= 0, 0,
                            ((sum(omzet.penjualan_pcs) - sum(omzet.retur_pcs)) / sum(omzet.target_pcs)) * 100) as decimal(10,2)) as 'prosentase_pcs',
                        cast(sum(omzet.bo_pcs) as decimal(18,0)) as 'bo_pcs',
                        iif((isnull(sum(omzet.penjualan_pcs), 0) - isnull(sum(omzet.retur_pcs), 0)) + isnull(sum(omzet.bo_pcs), 0) <= 0, 0,
                            round(((isnull(sum(omzet.penjualan_pcs), 0) - isnull(sum(omzet.retur_pcs), 0)) / ((isnull(sum(omzet.penjualan_pcs), 0) - isnull(sum(omzet.retur_pcs), 0)) + isnull(sum(omzet.bo_pcs), 0))) * 100, 2)) as 'service_rate',
                        isnull(sum(jumlah_visit.jumlah_visit), 0) as jumlah_realisasi_visit,
                        isnull(sum(target_visit.target_visit), 0) as target_realisasi_visit
                from
                (
                    select	isnull(salesman.companyid, '') as 'CompanyID',
                            isnull(salesman.kd_sales, '') as 'kd_sales',
                            cast(isnull(sum(target_jual.amount_target_total), 0) as decimal(18,0)) as 'target_amount',
                            cast(isnull(sum(target_jual.pcs_target_total), 0) as decimal(18,0)) as 'target_pcs',
                            cast(isnull(sum(faktur.amount_faktur_total), 0) as decimal(18,0)) as 'penjualan_amount',
                            cast(isnull(sum(faktur.pcs_faktur_total), 0) as decimal(18,0)) as 'penjualan_pcs',
                            cast(isnull(sum(retur.amount_total), 0) as decimal(18,0)) as 'retur_amount',
                            cast(isnull(sum(retur.pcs_total), 0) as decimal(18,0)) as 'retur_pcs',
                            cast(isnull(sum(faktur.amount_faktur_total), 0) - isnull(sum(retur.amount_total), 0) as decimal(18,0)) as 'omzet_amount',
                            cast(isnull(sum(faktur.pcs_faktur_total), 0) - isnull(sum(retur.pcs_total), 0) as decimal(18,0)) as 'omzet_pcs',
                            cast(iif(isnull(sum(target_jual.amount_target_total), 0) <= 0, 0,
                                ((isnull(sum(faktur.amount_faktur_total), 0) - isnull(sum(retur.amount_total), 0)) / isnull(sum(target_jual.amount_target_total), 0))) * 100 as decimal(5,2)) as 'prosentase_amount',
                            cast(iif(isnull(sum(target_jual.pcs_target_total), 0) <= 0, 0,
                                ((isnull(sum(faktur.pcs_faktur_total), 0) - isnull(sum(retur.pcs_total), 0)) / isnull(sum(target_jual.pcs_target_total), 0))) * 100 as decimal(5,2)) as 'prosentase_pcs',
                            isnull(sum(bo.jumlah_bo), 0) as 'bo_pcs',
                            iif((isnull(sum(faktur.pcs_faktur_total), 0) - isnull(sum(retur.pcs_total), 0)) + isnull(sum(bo.jumlah_bo), 0) <= 0, 0,
                                round(((isnull(sum(faktur.pcs_faktur_total), 0) - isnull(sum(retur.pcs_total), 0)) / ((isnull(sum(faktur.pcs_faktur_total), 0) - isnull(sum(retur.pcs_total), 0)) + isnull(sum(bo.jumlah_bo), 0))) * 100, 2)) as 'service_rate'
                    from
                    (
                        select	'Omzet' as 'report', produk.kd_produk, produk.nama
                        from	produk with (nolock) ";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " )	produk
                    left join
                    (
                        select	'Omzet' as 'report', salesman.companyid, salesman.kd_sales
                        from	salesman with (nolock)
                        where	salesman.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and salesman.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	salesman on produk.report=salesman.report
                    left join
                    (
                        select	target_jual.companyid, target_jual.kd_produk, target_jual.kd_sales,
                                sum(target_jual.target_amount) as 'amount_target_total',
                                sum(target_jual.target_pcs) As 'pcs_target_total'
                        from
                        (
                            select	target_jual.companyid, target_jual.kd_sales, target_jual.kd_produk,
                                    iif('".strtoupper(trim($role_id))."'='MD_H3_SM', target_jual.target, target_jual.target3) as 'target_amount',
                                    iif('".strtoupper(trim($role_id))."'='MD_H3_SM', target_jual.target2, target_jual.target4) as 'target_pcs'
                            from	target_jual with (nolock)
                                        left join salesman with (nolock) on target_jual.kd_sales=salesman.kd_sales and
                                                    target_jual.companyid=salesman.companyid
                            where	target_jual.companyid='".strtoupper(trim($companyid))."' and
                                    target_jual.tahun='".$year."' and
                                    target_jual.bulan='".$month."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and target_jual.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	target_jual
                                left join produk with (nolock) on target_jual.kd_produk=produk.kd_produk
                        where   target_jual.companyid is not null";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " and produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by target_jual.companyid, target_jual.kd_produk, target_jual.kd_sales
                    )	target_jual on salesman.companyid=target_jual.companyid and
                                        salesman.kd_sales=target_jual.kd_sales and
                                        produk.kd_produk=target_jual.kd_produk
                    left join
                    (
                        select	faktur.companyid, produk.kd_produk, faktur.kd_sales,
                                sum(faktur.jml_jual) As 'pcs_faktur_total',
                                sum(faktur.amount) as 'amount_faktur_total'
                        from
                        (
                            select	faktur.companyid, faktur.kd_sales, faktur.kd_part, faktur.jml_jual,
                                    isnull(faktur.total, 0) -
                                        iif(isnull(faktur_total.total_faktur, 0) <= 0, 0,
                                            round(((isnull(faktur.discrp, 0) / isnull(faktur_total.total_faktur, 0))), 0)) -
                                        iif(isnull(faktur_total.total_faktur, 0) <= 0, 0,
                                            round(((isnull(faktur.discrp1, 0) / isnull(faktur_total.total_faktur, 0))), 0)) as 'amount'
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.kd_sales, fakt_dtl.kd_part, fakt_dtl.jml_jual, faktur.discrp, faktur.discrp1,
                                        round(isnull(fakt_dtl.jumlah, 0) - ((isnull(fakt_dtl.jumlah, 0) * isnull(faktur.disc2, 0)) / 100), 0) As 'total'
                                from
                                (
                                    select	faktur.companyid, faktur.no_faktur, faktur.kd_sales, faktur.disc2, faktur.discrp, faktur.discrp1
                                    from	faktur with (nolock)
                                                left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                                            faktur.companyid=salesman.companyid
                                    where	year(faktur.tgl_faktur)='".$year."' and month(faktur.tgl_faktur)='".$month."' and
                                            faktur.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and faktur.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	faktur
                                        left join fakt_dtl with (nolock) On faktur.no_faktur=fakt_dtl.no_faktur and
                                                    faktur.companyid=fakt_dtl.companyid
                                where isnull(fakt_dtl.jml_jual, 0) > 0
                            )	faktur
                            left join
                            (
                                select	faktur.companyid, faktur.no_faktur, count(fakt_dtl.no_faktur) as total_faktur
                                from
                                (
                                    select	faktur.companyid, faktur.no_faktur, faktur.discrp, faktur.discrp1
                                    from	faktur with (nolock)
                                                left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                                            faktur.companyid=salesman.companyid
                                    where	year(faktur.tgl_faktur)='".$year."' and month(faktur.tgl_faktur)='".$month."' and
                                            (isnull(faktur.discrp, 0) > 0 or isnull(faktur.discrp1, 0) > 0) and
                                            faktur.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and faktur.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	faktur
                                        left join fakt_dtl with (nolock) On faktur.no_faktur=fakt_dtl.no_faktur and
                                                    faktur.companyid=fakt_dtl.companyid
                                where isnull(fakt_dtl.jml_jual, 0) > 0
                                group by faktur.companyid, faktur.no_faktur
                            )	faktur_total on faktur.companyid=faktur_total.companyid and
                                                faktur.no_faktur=faktur_total.no_faktur
                        )	faktur
                                left join part with (nolock) on faktur.kd_part=part.kd_part and
                                            faktur.companyid=part.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by faktur.companyid, produk.kd_produk, faktur.kd_sales
                    )	faktur on salesman.companyid=faktur.companyid and
                                    salesman.kd_sales=faktur.kd_sales and
                                    produk.kd_produk=faktur.kd_produk
                    left join
                    (
                        select	rtoko.companyid, produk.kd_produk, rtoko.kd_sales,
                                sum(rtoko_dtl.jumlah) As 'pcs_total',
                                sum(isnull(rtoko_dtl.jumlah, 0) * isnull(part.hrg_pokok, 0)) as 'amount_total'
                        from
                        (
                            select	rtoko.companyid, rtoko.no_retur, rtoko.kd_sales
                            from	rtoko with (nolock)
                                        left join salesman with (nolock) on rtoko.kd_sales=salesman.kd_sales and
                                                    rtoko.companyid=salesman.companyid
                            where	year(rtoko.tanggal)='".$year."' and month(rtoko.tanggal)='".$month."' and
                                    rtoko.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and rtoko.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	rtoko
                                left join rtoko_dtl with (nolock) on rtoko.no_retur=rtoko_dtl.no_retur and
                                            rtoko.companyid=rtoko_dtl.companyid
                                left join part with (nolock) on rtoko_dtl.kd_part=part.kd_part and
                                            rtoko.companyid=part.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by rtoko.companyid, produk.kd_produk, rtoko.kd_sales
                    )	retur on salesman.companyid=retur.companyid and
                                salesman.kd_sales=retur.kd_sales and
                                produk.kd_produk=retur.kd_produk
                    left join
                    (
                        select	bo.companyid, produk.kd_produk, bo.kd_sales, sum(bo.jumlah) as 'jumlah_bo'
                        from
                        (
                            select	bo.companyid, bo.kd_sales, bo.kd_part, sum(bo.jumlah + bo.jumlah2) as 'jumlah'
                            from	bo with (nolock)
                                        left join salesman with (nolock) on bo.kd_sales=salesman.kd_sales and
                                                    bo.companyid=salesman.companyid
                            where	bo.companyid='".strtoupper(trim($companyid))."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and bo.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " group by bo.companyid, bo.kd_sales, bo.kd_part
                        )	bo
                                left join part with (nolock) on bo.kd_part=part.kd_part and
                                            bo.companyid=part.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by bo.companyid, produk.kd_produk, bo.kd_sales
                    )	bo on salesman.companyid=bo.companyid and salesman.kd_sales=bo.kd_sales and produk.kd_produk=bo.kd_produk
                    group by salesman.companyid, salesman.kd_sales
                )	omzet
                left join
                (
                    select	visit_date.companyid, count(visit_date.kd_visit) as 'target_visit'
                    from	visit_date with (nolock)
                                left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                            visit_date.companyid=salesman.companyid
                    where	visit_date.companyid='".strtoupper(trim($companyid))."' and
                            year(visit_date.tanggal)='".$year."' and
                            month(visit_date.tanggal)='".$month."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and visit_date.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " group by visit_date.companyid
            )	target_visit on omzet.companyid=target_visit.companyid
            left join
            (
                select	visit.companyid, count(visit.kd_visit) as 'jumlah_visit'
                from
                (
                    select	visit_date.companyid, visit_date.kd_sales, visit_date.kd_visit
                    from
                    (
                        select	visit_date.companyid, visit_date.kd_visit, visit_date.kd_sales
                        from	visit_date with (nolock)
                                    left join salesman with (nolock) on visit_date.kd_sales=salesman.kd_sales and
                                                visit_date.companyid=salesman.companyid
                        where	visit_date.companyid='".strtoupper(trim($companyid))."' and
                                year(visit_date.tanggal)='".$year."' and
                                month(visit_date.tanggal)='".$month."'";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and visit_date.kd_sales='".strtoupper(trim($kode_mkr))."'";
        } elseif(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " and salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	visit_date
                        inner join visit with (nolock) on visit_date.kd_visit=visit.kd_visit and
                                    visit_date.companyid=visit.companyid
                group by visit_date.companyid, visit_date.kd_sales, visit_date.kd_visit
            )	visit
            group by visit.companyid
        )	jumlah_visit on omzet.companyid=jumlah_visit.companyid
        group by omzet.companyid";

        $dashboard_salesman = DB::connection($request->get('divisi'))->select($sql);

        // ====================================================================================
        // Ranking Salesman
        // ====================================================================================
        $sql = "select	isnull(salesman.id_sales, 0) as id,
                        isnull(salesman_rank.rank, 0) as rank,
                        isnull(rtrim(users.role_id), '') as role_id,
                        isnull(rtrim(users.photo), '') as photo,
                        isnull(rtrim(salesman.kd_sales), '') as code,
                        isnull(rtrim(salesman.nm_sales), '') as name,
                        isnull(salesman_rank.type, '') as type,
                        isnull(salesman_rank.peringkat, '') as status,
                        isnull(salesman_rank.target, 0) as target,
                        isnull(salesman_rank.amount, 0) - isnull(salesman_rank.retur, 0) as omzet,
                        isnull(salesman_rank.prosentase, 0) as prosentase
                from
                (
                    select	salesman_rank.companyid, salesman_rank.tahun, salesman_rank.bulan,
                            salesman_rank.rank, salesman_rank.kd_sales, salesman_rank.target,
                            salesman_rank.amount, salesman_rank.retur, salesman_rank.prosentase,
                            salesman_rank.type, salesman_rank.peringkat
                    from	salesman_rank with (nolock)
                    where	salesman_rank.tahun='".$year."' and salesman_rank.bulan='".$month."' and
                            salesman_rank.companyid='".strtoupper(trim($companyid))."' and
                            (isnull(salesman_rank.amount, 0) - isnull(salesman_rank.retur, 0) > 0 or isnull(salesman_rank.target, 0) > 0)";

        if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
            $sql .= " and salesman_rank.kd_sales='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " )	salesman_rank
                        left join salesman with (nolock) on salesman_rank.kd_sales=salesman.kd_sales and
                                    salesman_rank.companyid=salesman.companyid
                        left join users with (nolock) on salesman_rank.kd_sales=users.user_id and
                                    salesman_rank.companyid=users.companyid";

        if(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
            $sql .= " where salesman.spv='".strtoupper(trim($kode_mkr))."'";
        }

        $sql .= " order by isnull(salesman_rank.rank, 0) asc";

        $salesman_rank = DB::connection($request->get('divisi'))->select($sql);

        $data_my_rank = new Collection();
        $list_salesman_rank = [];

        $photo_default = config('constants.app.app_images_url').'/users/no-image.png';

        foreach($salesman_rank as $data) {
            $prosentase = number_format($data->omzet).' / '.number_format($data->target).' = '.number_format($data->prosentase, 2).'%';

            if(strtoupper((trim($role_id))) == 'MD_H3_SM') {
                if (strtoupper(trim($data->code)) == strtoupper(trim($kode_mkr))) {
                    $data_my_rank->push((object) [
                        'id'        => (int)$data->id,
                        'rank'      => (int)$data->rank,
                        'photo'     => (!empty($data->photo) && $data->photo != '') ? trim($data->photo) : $photo_default,
                        'name'      => trim($data->code).' • '.trim($data->name),
                        'type'      => trim($data->type),
                        'peringkat' => 'Peringkat '.$data->rank,
                        'status'    => trim($data->status),
                        'keterangan' => trim($prosentase)
                    ]);
                }
            }

            $list_salesman_rank[] = [
                'id'        => (int)$data->id,
                'rank'      => (int)$data->rank,
                'photo'     => (!empty($data->photo) && $data->photo != '') ? trim($data->photo) : $photo_default,
                'name'      => trim($data->code).' • '.trim($data->name),
                'type'      => trim($data->type),
                'peringkat' => 'Peringkat '.$data->rank,
                'status'    => trim($data->status),
                'keterangan' => trim($prosentase)
            ];
        }

        $kode_my_rank = $data_my_rank->first();

        if (empty($kode_my_rank)) {
            $data_my_rank->push((object) [
                'id'        => (int)$id_user,
                'rank'      => 0,
                'photo'     => (!empty($data->photo) && $data->photo != '') ? trim($data->photo) : $photo_default,
                'name'      => strtoupper(trim($user_id)),
                'type'      => "Bertahan",
                'peringkat' => "∞",
                'status'    => "Bertahan",
                'keterangan' => strtoupper(trim($role_id)),
            ]);
        }

        $data_dashboard = new Collection;

        foreach($dashboard_salesman as $data) {
            $prosentase_visit = (empty($data->target_realisasi_visit) || (double)$data->target_realisasi_visit <= 0) ? 0 :
                                    ((double)$data->jumlah_realisasi_visit / (double)$data->target_realisasi_visit) * 100;
            $data_dashboard->push((object) [
                'omzet_amount'          => (!empty($data->omzet_amount)) ? (float)$data->omzet_amount : 0,
                'omzet_pcs'             => (!empty($data->omzet_pcs)) ? (float)$data->omzet_pcs : 0,
                'penjualan_amount'      => (!empty($data->penjualan_amount)) ? (float)$data->penjualan_amount : 0,
                'penjualan_pcs'         => (!empty($data->penjualan_pcs)) ? (float)$data->penjualan_pcs : 0,
                'retur_amount'          => (!empty($data->retur_amount)) ? (float)$data->retur_amount : 0,
                'retur_pcs'             => (!empty($data->retur_pcs)) ? (float)$data->retur_pcs : 0,
                'target_amount'         => (!empty($data->target_amount)) ? (float)$data->target_amount : 0,
                'target_pcs'            => (!empty($data->target_pcs)) ? (float)$data->target_pcs : 0,
                'prosentase_amount'     => (!empty($data->prosentase_amount)) ? (float)$data->prosentase_amount : 0,
                'prosentase_pcs'        => (!empty($data->prosentase_pcs)) ? (float)$data->prosentase_pcs : 0,
                'bo_pcs'                => (!empty($data->bo_pcs)) ? (float)$data->bo_pcs : 0,
                'service_rate'          => (!empty($data->service_rate)) ? (float)$data->service_rate : 0,
                'realisasi_visit'       => number_format($data->jumlah_realisasi_visit).'/'.number_format($data->target_realisasi_visit).'/'.number_format($prosentase_visit, 2)."%",
                'my_rank'               => collect($data_my_rank)->first(),
                'sales_man_rank'        => $list_salesman_rank

            ]);
        }
        return ApiResponse::responseSuccess('success', $data_dashboard->first());
    }

    public function dashboardDealer($request, $year, $month, $ms_dealer_id, $item_group, $role_id, $companyid) {
        $sql = DB::connection($request->get('divisi'))
            ->table('msdealer')->lock('with (nolock)')
            ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
            ->where('companyid', $companyid)
            ->where('id', $ms_dealer_id)
            ->first();

        if (empty($sql->kode_dealer)) {
            return ApiResponse::responseWarning('Kode dealer masih belum terdaftar');
        }

        $kode_dealer = strtoupper(trim($sql->kode_dealer));

        $sql = "select	isnull(target_toko.target, 0) as 'target',
                        isnull(faktur.faktur_amount, 0) as 'penjualan_amount',
                        isnull(faktur.faktur_pcs, 0) as 'penjualan_pcs',
                        isnull(retur.retur_amount, 0) as 'retur_amount',
                        isnull(retur.retur_pcs, 0) as 'retur_pcs',
                        isnull(faktur.faktur_amount, 0) - isnull(retur.retur_amount, 0) as 'omzet_amount',
                        isnull(faktur.faktur_pcs, 0) - isnull(retur.retur_pcs, 0) as 'omzet_pcs',
                        isnull(campaign.poin_campaign, 0) as 'pencapaian_campaign'
                from
                (
                    select	dealer.companyid, dealer.kd_dealer
                    from	dealer with (nolock)
                    where	dealer.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                            dealer.companyid='".strtoupper(trim($companyid))."'
                )	dealer
                left join
                (
                    select	target_toko.companyid, target_toko.kd_dealer, target_toko.target
                    from	target_toko with (nolock)
                    where	target_toko.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                            target_toko.tahun='".$year."' and target_toko.bulan='".$month."' and
                            target_toko.companyid='".strtoupper(trim($companyid))."'
                )	target_toko on dealer.kd_dealer=target_toko.kd_dealer and
                                    dealer.companyid=target_toko.companyid
                left join
                (
                    select	faktur.companyid, faktur.kd_dealer,
                            sum(isnull(faktur.jml_jual, 0)) as faktur_pcs,
                            sum(isnull(faktur.amount, 0)) as faktur_amount
                    from
                    (
                        select	faktur.companyid, faktur.kd_dealer, faktur.kd_part, faktur.jml_jual,
                                isnull(faktur.total, 0) -
                                    iif(isnull(faktur_total.total_faktur, 0) <= 0, 0,
                                        round(((isnull(faktur.discrp, 0) / isnull(faktur_total.total_faktur, 0))), 0)) -
                                    iif(isnull(faktur_total.total_faktur, 0) <= 0, 0,
                                        round(((isnull(faktur.discrp1, 0) / isnull(faktur_total.total_faktur, 0))), 0))  as 'amount'
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.kd_dealer, fakt_dtl.kd_part,
                                    fakt_dtl.jml_jual, faktur.discrp, faktur.discrp1,
                                    round(isnull(fakt_dtl.jumlah, 0) - ((isnull(fakt_dtl.jumlah, 0) * isnull(faktur.disc2, 0)) / 100), 0) As 'total'
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.kd_dealer,
                                        faktur.disc2, faktur.discrp, faktur.discrp1
                                from	faktur with (nolock)
                                where	year(faktur.tgl_faktur)='".$year."' and month(faktur.tgl_faktur)='".$month."' and
                                        faktur.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                        faktur.companyid='".strtoupper(trim($companyid))."'
                            )	faktur
                                    inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where	isnull(fakt_dtl.jml_jual, 0) > 0
                        )	faktur
                        left join
                        (
                            select	faktur.companyid, faktur.no_faktur, count(fakt_dtl.no_faktur) as total_faktur
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur
                                from	faktur with (nolock)
                                            left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                                            faktur.companyid=salesman.companyid
                                where	year(faktur.tgl_faktur)='".$year."' and month(faktur.tgl_faktur)='".$month."' and
                                        faktur.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                        faktur.companyid='".strtoupper(trim($companyid))."' and
                                        (isnull(faktur.discrp, 0) > 0 or isnull(faktur.discrp1, 0) > 0)

                            )	faktur
                                    left join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where isnull(fakt_dtl.jml_jual, 0) > 0
                            group by faktur.companyid, faktur.no_faktur
                        )	faktur_total on faktur.companyid=faktur_total.companyid and
                                            faktur.no_faktur=faktur_total.no_faktur
                    )	faktur
                            left join part with (nolock) on faktur.kd_part=part.kd_part and
                                        faktur.companyid=part.companyid
                            left join sub with (nolock) on part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by faktur.companyid, faktur.kd_dealer
                )	faktur on dealer.kd_dealer=faktur.kd_dealer and
                            dealer.companyid=faktur.companyid
                left join
                (
                    select	rtoko.companyid, rtoko.kd_dealer, sum(rtoko_dtl.jumlah) as retur_pcs,
                            sum(isnull(rtoko_dtl.jumlah, 0) * isnull(part.hrg_pokok, 0)) as retur_amount
                    from
                    (
                        select	rtoko.companyid, rtoko.no_retur, rtoko.kd_dealer
                        from	rtoko with (nolock)
                        where	year(rtoko.tanggal)='".$year."' and month(rtoko.tanggal)='".$month."' and
                                rtoko.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                rtoko.companyid='".strtoupper(trim($companyid))."'
                    )	rtoko
                            left join rtoko_dtl with (nolock) on rtoko.no_retur=rtoko_dtl.no_retur and
                                        rtoko.companyid=rtoko_dtl.companyid
                            left join part with (nolock) on rtoko_dtl.kd_part=part.kd_part and
                                        rtoko.companyid=part.companyid
                            left join sub with (nolock) on part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by rtoko.companyid, rtoko.kd_dealer
                )	retur on dealer.kd_dealer=retur.kd_dealer and
                            dealer.companyid=retur.companyid
                left join
                (
                    select	camp.companyid, camp.kd_dealer, sum(camp.poin_campaign) as poin_campaign
                    from
                    (
                        select	faktur.companyid, faktur.kd_dealer, faktur.kd_part,
                                sum(isnull(faktur.jml_jual, 0) * isnull(camp.point, 0)) as poin_campaign
                        from
                        (
                            select	faktur.companyid, faktur.kd_dealer, fakt_dtl.kd_part, sum(fakt_dtl.jml_jual) as jml_jual
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.kd_dealer
                                from	faktur with (nolock)
                                where	year(faktur.tgl_faktur)='".$year."' and month(faktur.tgl_faktur)='".$month."' and
                                        faktur.kd_dealer='".strtoupper(trim($kode_dealer))."' and
                                        faktur.companyid='".strtoupper(trim($companyid))."'
                            )	faktur
                                    inner join fakt_dtl with (nolock) on faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where	isnull(fakt_dtl.jml_jual, 0) > 0
                            group by faktur.companyid, faktur.kd_dealer, fakt_dtl.kd_part
                        )	faktur
                        left join
                        (
                            select	camp.companyid, camp_dtl.kd_part, camp_dtl.point
                            from
                            (
                                select	camp.companyid, camp.no_camp
                                from	camp with (nolock)
                                where	camp.companyid='".strtoupper(trim($companyid))."' and
                                        camp.tgl_prd1 <= convert(varchar(10), getdate(), 120) and
                                        camp.tgl_prd2 >= convert(varchar(10), getdate(), 120)
                            )	camp
                                    inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                                camp.companyid=camp_dtl.companyid
                        )	camp on faktur.kd_part=camp.kd_part and
                                    faktur.companyid=camp.companyid
                        group by faktur.companyid, faktur.kd_dealer, faktur.kd_part
                    )	camp
                            left join part with (nolock) on camp.kd_part=part.kd_part and
                                        camp.companyid=part.companyid
                            left join sub with (nolock) on part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

            if(!empty($item_group) && $item_group != '0' && $item_group != '') {
                $sql .= " where produk.id_mobile='".$item_group."'";
            }

            $sql .= " group by camp.companyid, camp.kd_dealer
                )	campaign on dealer.kd_dealer=campaign.kd_dealer and
                                dealer.companyid=campaign.companyid";

            $dashboard_dealer = DB::connection($request->get('divisi'))->select($sql);
            $data_dashboard = new Collection;

            foreach ($dashboard_dealer as $data) {
                $total_omzet = 0;


                $target_toko = 0;
                if(strtoupper(trim($role_id)) == "D_H3") {
                    $target_toko = 0;
                } else {
                    $target_toko = $data->target;
                }

                $data_dashboard[] = [
                    'target'                => (double)$target_toko,
                    'penjualan_amount'      => (double)$data->penjualan_amount,
                    'penjualan_pcs'         => (double)$data->penjualan_pcs,
                    'retur_amount'          => (double)$data->retur_amount,
                    'retur_pcs'             => (double)$data->retur_pcs,
                    'omzet_amount'          => (double)$data->omzet_amount,
                    'omzet_pcs'             => (double)$data->omzet_pcs,
                    'pencapaian_campaign'   => (double)$data->pencapaian_campaign
                ];
            }
            return ApiResponse::responseSuccess('success',  $data_dashboard->first());
    }

    public function dashboardManagement($request, $year, $month, $item_group, $companyid) {
        $sql = DB::connection($request->get('divisi'))
                ->table('stsclose')->lock('with (nolock)')
                ->where('companyid', $companyid)
                ->first();

        $tanggalClossing = (!empty($sql->close_mkr)) ? $sql->close_mkr : date('Y-m-d');
        $tanggalProses = $year.'-'.$month.'-01';

        $xStlokasiTahunan = 'stlokasi'.$year;
        $xPartTahunan = 'part'.$year;
        $xStockBulanan = 'stc'.(int)$month;
        $xHrgPokokBulanan = 'hpp'.(int)$month;
        $xBoBulanan = 'bo'.(int)$month;
        $xOnOrderBulanan = 'oo'.(int)$month;

        $sql = "select	isnull(company.companyid, '') as 'CompanyID',
                        isnull(sum(stock.stock_pcs), 0) As 'stock_pcs',
                        isnull(sum(stock.stock_amount), 0) as 'stock_amount',
                        isnull(sum(faktur.sales_non_ppn_amount), 0) As 'sales_non_ppn_amount',
                        isnull(sum(faktur.sales_non_ppn_pcs), 0) as 'sales_non_ppn_pcs',
                        isnull(sum(faktur.cost_sales_amount), 0) As 'cost_sales_amount',
                        cast(iif(isnull(sum(faktur.cost_sales_amount), 0) <= 0, 0,
                            (((isnull(sum(faktur.sales_non_ppn_amount), 0) - isnull(sum(faktur.cost_sales_amount), 0)) / isnull(sum(faktur.cost_sales_amount), 0)) * 100)) as decimal(5,2)) as 'gross_margin',
                        isnull(sum(isnull(bo.total, 0) + isnull(faktur.sales_non_ppn_amount, 0)), 0) as 'demand_amount',
                        isnull(sum(isnull(bo.qty, 0) + isnull(faktur.sales_non_ppn_pcs, 0)), 0) As 'demand_pcs',
                        isnull(sum(bo.total), 0) as 'loss_sales_amount',
                        isnull(sum(bo.qty), 0) as 'loss_sales_pcs',
                        cast(iif(isnull(sum(isnull(bo.total, 0) + isnull(faktur.sales_non_ppn_amount, 0)), 0) <= 0, 0,
                            (isnull(sum(faktur.sales_non_ppn_amount), 0) / isnull(sum(isnull(bo.total, 0) + isnull(faktur.sales_non_ppn_amount, 0)), 0)) * 100) as decimal(5,2)) as 'service_rate',
                        isnull(sum(packing.barang_masuk_amount), 0) As 'barang_masuk_amount',
                        isnull(sum(packing.barang_masuk_pcs), 0) as 'barang_masuk_pcs',
                        isnull(sum(stock.on_order_amount), 0) As 'on_order_amount',
                        isnull(sum(stock.on_order_pcs), 0) as 'on_order_pcs',
                        isnull(sum(faktur.amount_tunai), 0) as 'tunai',
                        isnull(sum(faktur.amount_rekening), 0) As 'rekening',
                        isnull(sum(stock.fast_moving_amount), 0) as 'fast_moving_amount',
                        isnull(sum(stock.fast_moving_pcs), 0) as 'fast_moving_pcs',
                        isnull(sum(stock.slow_moving_amount), 0) As 'slow_moving_amount',
                        isnull(sum(stock.slow_moving_pcs), 0) as 'slow_moving_pcs',
                        isnull(sum(stock.amount_current), 0) as 'current',
                        isnull(sum(stock.amount_non_current), 0) As 'non_current',
                        isnull(sum(stock.amount_others), 0) as 'others',
                        cast(round(((isnull(sum(stock.stock_amount), 0) / day(dateadd(day, -1, datefromparts(year(getdate()), datepart(q,getdate())*3-2,1)))) * day(getdate())) / isnull(sum(faktur.sales_non_ppn_amount), 0), 2) as decimal(5,2)) as 'stock_level'
                from
                (
                    select	'Versi2' as 'jenis', company.companyid, company.ket, company.nama, company.alamat, company.kota
                    from	company with (nolock)
                    where	company.companyid='".strtoupper(trim($companyid))."'
                )	company
                left join
                (
                    select	'Versi2' as 'jenis', rtrim(kd_produk) as 'kd_produk', nama, nourut
                    from	produk with (nolock) ";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " )	produk on company.jenis=produk.jenis
                left Join
                (
                    select  faktur.companyid, iif(isnull(Sub.kd_produk, '')='', 'LLL', sub.kd_produk) as 'kode_produk',
                            sum(round(isnull(faktur.sales_non_ppn_amount, 0) / ((100 + isnull(company.ppn, 0)) / Convert(Decimal(5, 2), 100)), 0)) As 'sales_non_ppn_amount',
                            sum(faktur.jml_jual) As 'sales_non_ppn_pcs', sum(faktur.cost_sales_amount) as 'cost_sales_amount',
                            sum(IIf(isnull(faktur.umur_faktur, 0) <= 0, round(isnull(faktur.sales_non_ppn_amount, 0) / ((100 + isnull(company.ppn, 0)) / Convert(Decimal(5, 2), 100)), 0), 0)) As 'amount_tunai',
                            sum(IIf(isnull(faktur.umur_faktur, 0) > 0, round(isnull(faktur.sales_non_ppn_amount, 0) / ((100 + isnull(company.ppn, 0)) / Convert(Decimal(5, 2), 100)), 0), 0)) As 'amount_rekening'
                    from
                    (
                        select	faktur.companyid, faktur.no_faktur, faktur.level, faktur.umur_faktur,
                                faktur.kd_part, faktur.jml_jual, faktur.hrg_pokok,
                                isnull(faktur.jml_jual, 0) * isnull(faktur.hrg_pokok, 0) As 'cost_sales_amount',
                                isnull(faktur.total, 0) - isnull(fakturdisc.discrp, 0) - isnull(fakturdisc.discrp1, 0) As 'sales_non_ppn_amount'
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.level, faktur.umur_faktur, faktur.disc2,
                                    fakt_dtl.kd_part, fakt_dtl.hrg_pokok, fakt_dtl.jml_jual,
                                    round(isnull(fakt_dtl.jumlah, 0) - ((isnull(fakt_dtl.jumlah, 0) * isnull(faktur.disc2, 0)) / 100), 0) As 'total'
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.umur_faktur, faktur.disc2,
                                        iif(isnull(dealer.nm_dealer, '') like '%PT.MITRA PINASTHIKA MUSTIKA%', 'MPM', 'AHM') as 'level'
                                from
                                (
                                    select	faktur.companyid, faktur.no_faktur, faktur.kd_dealer, faktur.umur_faktur, faktur.disc2
                                    from	faktur with (nolock)
                                                left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                                            faktur.companyid=salesman.companyid
                                    where	year(cast(faktur.tgl_faktur as date))='".$year."' and
                                            month(cast(faktur.tgl_faktur as date))='".$month."' and
                                            faktur.companyid='".strtoupper(trim($companyid))."'
                                )	faktur
                                        left join dealer with (nolock) On faktur.kd_dealer=dealer.kd_dealer and
                                                    faktur.companyid=dealer.companyid
                            )	faktur
                                    left Join fakt_dtl with (nolock) On faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where isnull(fakt_dtl.jml_jual, 0) > 0
                        )	faktur
                        left join
                        (
                            Select  faktur.companyid, faktur.no_faktur,
                                    round(isnull(faktur.discrp, 0) / isnull(count(faktur.no_faktur), 0), 0) As 'discrp',
                                    round(isnull(faktur.discrp1, 0) / isnull(count(faktur.no_faktur), 0), 0) As 'discrp1'
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.discrp, faktur.discrp1
                                from	faktur with (nolock)
                                where	year(cast(faktur.tgl_faktur as date))='".$year."' and
                                        month(cast(faktur.tgl_faktur as date))='".$month."' and
                                        (isnull(faktur.discrp, 0) > 0 or isnull(faktur.discrp1, 0) > 0) and
                                        faktur.companyid='".strtoupper(trim($companyid))."'
                            )	faktur
                                    left join fakt_dtl with (nolock) On faktur.no_faktur=fakt_dtl.no_faktur and
                                                faktur.companyid=fakt_dtl.companyid
                            where isnull(fakt_dtl.jml_jual, 0) > 0
                            group by faktur.companyid, faktur.no_faktur, isnull(faktur.discrp, 0), isnull(faktur.discrp1, 0)
                        )	fakturdisc on faktur.companyid=fakturdisc.companyid And faktur.no_faktur=fakturdisc.no_faktur
                    )	faktur
                            left join company with (nolock) On faktur.companyid=company.companyid
                            left Join part with (nolock) On faktur.kd_part=part.kd_part and
                                        '".strtoupper(trim($companyid))."'=part.companyid
                            left Join sub with (nolock) on part.kd_sub=sub.kd_sub
                            left join produk with (nolock) On Sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by faktur.companyid, iif(isnull(Sub.kd_produk, '')='', 'LLL', sub.kd_produk)
                )   faktur on company.companyid=faktur.companyid and produk.kd_produk=faktur.kode_produk
                left join
                ( ";

        if ($tanggalProses >= $tanggalClossing) {
            $sql .= " select	bo.companyid, iif(isnull(sub.kd_produk, '')='', 'LLL', sub.kd_produk) as 'kd_produk',
                                sum(bo.jumlah) As 'qty', sum(bo.total) as 'total'
                    from
                    (
                        select	bo.companyid, bo.kd_part, bo.level, bo.jumlah, bo.hrg_pokok, bo.total
                        from
                        (
                            select	bo.companyid, bo.kd_part, iif(isnull(dealer.nm_dealer, '') like '%PT.MITRA PINASTHIKA MUSTIKA%', 'MPM', 'AHM') as 'level',
                                    bo.jumlah, bo.hrg_pokok, isnull(bo.jumlah, 0) * isnull(bo.hrg_pokok, 0) As 'total'
                            from
                            (
                                select	bo.companyid, bo.kd_dealer, bo.kd_part, bo.jumlah, bo.hrg_pokok
                                from	bo with (nolock)
                                where	bo.companyid='".strtoupper(trim($companyid))."'
                            )	bo
                                    left join dealer with (nolock) on bo.kd_dealer=dealer.kd_dealer and
                                                bo.companyid=dealer.companyid
                    )	bo";
        } else {
            $sql .= " select	bo.companyid, iif(isnull(sub.kd_produk, '')='', 'LLL', sub.kd_produk) as 'kd_produk',
                                sum(bo.jumlah) As 'qty', sum(bo.total) as 'total'
                    from
                    (
                        select	bo.companyid, bo.kd_part, bo.level, bo.jumlah, bo.hrg_pokok, bo.total
                        from
                        (
                            select	bo.companyid, bo.kd_part,  'AHM' as 'level',
                                    bo.jumlah, bo.hrg_pokok, isnull(bo.jumlah, 0) * isnull(part.hrg_pokok, 0) As 'total'
                            from
                            (
                                select	".$xPartTahunan.".companyid,".$xPartTahunan.".kd_part,
                                        ".$xPartTahunan.".".$xBoBulanan." as 'jumlah',
                                        ".$xPartTahunan.".".$xHrgPokokBulanan." as 'hrg_pokok'
                                from	".$xPartTahunan." with (nolock)
                                where   ".$xPartTahunan.".companyid='".strtoupper(trim($companyid))."'
                            )	bo
                                    left join part with (nolock) on bo.kd_part=part.kd_part and
                                                bo.companyid=part.companyid
                    )	bo ";
        }

        $sql .= " )	bo
                            left join part with (nolock) on part.kd_part=bo.kd_part and
                                        bo.companyid=part.companyid
                            left join sub with (nolock) on part.kd_sub=Sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by bo.companyid, IIf(isnull(Sub.kd_produk, '')='', 'LLL', sub.kd_produk)
                )	bo on company.companyid=bo.companyid And produk.kd_produk=bo.kd_produk
                left join
                (
                    select	packing.companyid, iif(isnull(Sub.kd_produk, '')='', 'LLL', sub.kd_produk) as 'kd_produk',
                            sum(packing.jml_terima) As 'barang_masuk_pcs', sum(packing.amount) as 'barang_masuk_amount'
                    from
                    (
                        select	packing.companyid, pack_dtl.kd_part, sum(pack_dtl.jmlterima) as 'jml_terima',
                                sum(isnull(pack_dtl.harga, 0) * isnull(pack_dtl.jmlterima, 0)) As 'amount'
                        from
                        (
                            select	packing.companyid, packing.no_ps
                            from	packing with (nolock)
                            where	year(packing.tgl_terima)='".$year."' and
                                    month(packing.tgl_terima)='".$month."' and
                                    packing.companyid='".strtoupper(trim($companyid))."'
                        )	packing
                                left join pack_dtl with (nolock) on packing.no_ps=pack_dtl.no_ps and
                                            packing.companyid=pack_dtl.companyid
                        where isnull(pack_dtl.jmlterima, 0) > 0
                        group by packing.companyid, pack_dtl.kd_part
                    )	packing
                            left join part with (nolock) On packing.kd_part=part.kd_part and
                                        packing.companyid=part.companyid
                            left join sub with (nolock) On part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by packing.companyid, iif(isnull(Sub.kd_produk, '')='', 'LLL', sub.kd_produk)
                )	packing On company.companyid=packing.companyid And produk.kd_produk=packing.kd_produk
                left join
                (
                    select	part.companyid, iif(isnull(Sub.kd_produk, '')='', 'LLL', sub.kd_produk) as 'kd_produk',
                            sum(part.stock) 'stock_pcs', sum(part.amount) as 'stock_amount',
                            sum(part.on_order_pcs) As 'on_order_pcs', sum(part.on_order_amount) as 'on_order_amount',
                            sum(IIf(isnull(part.fs, '')='F', isnull(part.stock, 0) * isnull(part.hrg_pokok, 0), 0)) as 'fast_moving_amount',
                            sum(IIf(isnull(part.fs, '')='F', part.stock, 0)) as 'fast_moving_pcs',
                            sum(IIf(isnull(part.fs, '') <> 'F', isnull(part.stock, 0) * isnull(part.hrg_pokok, 0), 0)) as 'slow_moving_amount',
                            sum(IIf(isnull(part.fs, '') <> 'F', part.stock, 0)) as 'slow_moving_pcs',
                            sum(IIf(isnull(part.cno, '')='C', isnull(part.stock, 0) * isnull(part.hrg_pokok, 0), 0)) as 'amount_current',
                            sum(IIf(isnull(part.cno, '')='N', isnull(part.stock, 0) * isnull(part.hrg_pokok, 0), 0)) as 'amount_non_current',
                            sum(IIf(isnull(part.cno, '') <> 'C' and isnull(part.cno, '') <> 'N', isnull(part.stock, 0) * isnull(part.hrg_pokok, 0), 0)) as 'amount_others'
                    from
                    ( ";

        if ($tanggalProses >= $tanggalClossing) {
            $sql .= " select	part.companyid, part.kd_part, part.kd_sub, part.hrg_pokok, isnull(sum(stlokasi.jumlah), 0) as 'stock',
                                isnull(sum(stlokasi.jumlah), 0) * isnull(part.hrg_pokok, 0) as 'amount', part.on_order_pcs, part.on_order_amount,
                                part.cno, part.fs
                        from
                        (
                            select  part.companyid, part.kd_part, iif(isnull(part.kd_sub, '')='', 'LLLL', part.kd_sub) as 'kd_sub', part.hrg_pokok, part.stock as 'stock',
                                    isnull(part.stock, 0) * isnull(part.hrg_pokok, 0) As 'amount',
                                    IIf((isnull(part.on_order, 0) + isnull(part.on_order_l, 0)) - isnull(part.kum_mb, 0) < 0, 0,
                                        (isnull(part.on_order, 0) + isnull(part.on_order_l, 0)) - isnull(part.kum_mb, 0)) as 'on_order_pcs',
                                    IIf((isnull(part.on_order, 0) + isnull(part.on_order_l, 0)) - isnull(part.kum_mb, 0) < 0, 0,
                                        (isnull(part.on_order, 0) + isnull(part.on_order_l, 0)) - isnull(part.kum_mb, 0)) * isnull(part.hrg_pokok, 0) as 'on_order_amount',
                                    part.cno, part.fs
                            from	part with (nolock)
                            where	part.companyid='".strtoupper(trim($companyid))."'
                        )	part
                                left join company with (nolock) on part.companyid=company.companyid
                                left join stlokasi with (nolock) on part.kd_part=stlokasi.kd_part and
                                            company.kd_lokasi=stlokasi.kd_lokasi and
                                            part.companyid=stlokasi.companyid
                        group by part.companyid, part.kd_part, part.kd_sub, part.hrg_pokok, part.on_order_pcs,
                                part.on_order_amount, part.cno, part.fs ";
        } else {
            $sql .= " select	parttahunan.companyid, parttahunan.kd_part, iif(isnull(part.kd_sub, '')='', 'LLLL', part.kd_sub) as 'kd_sub',
                                isnull(parttahunan.stock, 0) As 'stock', isnull(parttahunan.hrg_pokok, 0) as 'hrg_pokok',
                                isnull(parttahunan.stock, 0) * isnull(parttahunan.hrg_pokok, 0) As 'amount',
                                isnull(parttahunan.on_order, 0) As 'on_order_pcs',
                                isnull(parttahunan.on_order, 0) * isnull(parttahunan.hrg_pokok, 0) As 'on_order_amount',
                                part.cno, part.fs
                    from
                    (
                            select	".$xPartTahunan.".companyid, ".$xPartTahunan.".kd_part,
                                    isnull(".$xPartTahunan.".".$xBoBulanan.", 0) As 'bo',
                                    isnull(".$xPartTahunan.".".$xHrgPokokBulanan.", 0) As 'hrg_pokok',
                                    sum(".$xStlokasiTahunan.".".$xStockBulanan.") As 'stock',
                                    sum(".$xStlokasiTahunan.".".$xOnOrderBulanan.") As 'on_order'
                            from
                            (
                                Select	".$xPartTahunan.".companyid, ".$xPartTahunan.".kd_part,
                                        ".$xPartTahunan.".".$xBoBulanan.",
                                        ".$xPartTahunan.".".$xHrgPokokBulanan."
                                from	".$xPartTahunan." with (nolock)
                                where   ".$xPartTahunan.".companyid='".strtoupper(trim($companyid))."'

                            ) ".$xPartTahunan."
                                    left join company with (nolock) on ".$xPartTahunan.".companyid=company.companyid
                                    left join ".$xStlokasiTahunan." with (nolock) on ".$xPartTahunan.".kd_part=".$xStlokasiTahunan.".kd_part and
                                                company.kd_lokasi=" . $xStlokasiTahunan . ".kd_lokasi and ".$xPartTahunan.".companyid=" . $xStlokasiTahunan . ".companyid
                            group by ".$xPartTahunan.".companyid, ".$xPartTahunan.".kd_part, ".$xPartTahunan.".".$xBoBulanan.",
                                    ".$xPartTahunan.".".$xHrgPokokBulanan."
                        )	parttahunan
                                left join part with (nolock) On parttahunan.kd_part=part.kd_part and
                                            parttahunan.companyid=part.companyid ";
        }

        $sql .= " )	part
                            left join sub with (nolock) On part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

        if(!empty($item_group) && $item_group != '0' && $item_group != '') {
            $sql .= " where produk.id_mobile='".$item_group."'";
        }

        $sql .= " group by part.companyid, iif(isnull(sub.kd_produk, '')='', 'LLL', sub.kd_produk)
                )	stock On company.companyid=stock.companyid And produk.kd_produk=stock.kd_produk
                group by isnull(company.companyid, '') ";

        $sql = DB::connection($request->get('divisi'))->select($sql);
        $data_dashboard = new Collection;

        foreach ($sql as $result) {
            $data_dashboard[] = [
                'stock_amount'          => (float)$result->stock_amount,
                'stock_pcs'             => (float)$result->stock_pcs,
                'sales_non_ppn_amount'  => (float)$result->sales_non_ppn_amount,
                'sales_non_ppn_pcs'     => (float)$result->sales_non_ppn_pcs,
                'tunai'                 => (float)$result->tunai,
                'rekening'              => (float)$result->rekening,
                'cost_sales_amount'     => (float)$result->cost_sales_amount,
                'gross_margin'          => (float)$result->gross_margin,
                'demand_amount'         => (float)$result->demand_amount,
                'demand_pcs'            => (float)$result->demand_pcs,
                'loss_sales_amount'     => (float)$result->loss_sales_amount,
                'loss_sales_pcs'        => (float)$result->loss_sales_pcs,
                'service_rate'          => (float)$result->service_rate,
                'barang_masuk_amount'   => (float)$result->barang_masuk_amount,
                'barang_masuk_pcs'      => (float)$result->barang_masuk_pcs,
                'on_order_amount'       => (float)$result->on_order_amount,
                'on_order_pcs'          => (float)$result->on_order_pcs,
                'fast_moving_amount'    => (float)$result->fast_moving_amount,
                'fast_moving_pcs'       => (float)$result->fast_moving_pcs,
                'slow_moving_amount'    => (float)$result->slow_moving_amount,
                'slow_moving_pcs'       => (float)$result->slow_moving_pcs,
                'non_current'           => (float)$result->non_current,
                'others'                => (float)$result->others,
                'stock_level'           => (string)(float)$result->stock_level . ' Kali/Bulan'
            ];
        }

        return ApiResponse::responseSuccess('success',  $data_dashboard->first());
    }
}

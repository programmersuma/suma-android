<?php

namespace App\Http\Controllers\Api\Part;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class PofController extends Controller
{
    public function listPofOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'month'     => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Silahkan isi data divisi, bulan, dan tahun terlebih dahulu');
            }

            $year = substr($request->get('month'), 0, 4);
            $month = substr($request->get('month'), 5, 2);

            $sql = DB::connection($request->get('divisi'))
                    ->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.companyid, '') as companyid,
                                isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.tgl_pof, '') as tanggal_pof,
                                isnull(pof.approve, 0) as approve")
                    ->leftJoin(DB::raw('pof_dtl with (nolock)'), function($join) {
                        $join->on('pof_dtl.no_pof', '=', 'pof.no_pof')
                            ->on('pof_dtl.companyid', '=', 'pof.companyid');
                    })
                    ->whereYear('pof.tgl_pof', $year)
                    ->whereMonth('pof.tgl_pof', $month)
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)));

            if(strtoupper(trim($request->userlogin->role_id)) == "MD_H3_SM") {
                $sql->where('pof.kd_sales', strtoupper(trim($request->userlogin->user_id)));
            } elseif(strtoupper(trim($request->userlogin->role_id)) == "D_H3") {
                $sql->where('pof.kd_dealer', strtoupper(trim($request->userlogin->user_id)));
            }

            if(!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $sql->where('pof.kd_sales', strtoupper(trim($request->get('salesman'))));
            }

            if(!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $sql->where('pof.kd_dealer', strtoupper(trim($request->get('dealer'))));
            }

            if(!empty($request->get('part_number')) && trim($request->get('part_number')) != '') {
                $sql->where('pof_dtl.kd_part', 'like', strtoupper(trim($request->get('part_number'))).'%');
            }

            $sql = $sql->groupByRaw("pof.companyid, pof.tgl_pof, pof.no_pof, pof.approve")
                        ->orderByRaw("pof.companyid asc,  pof.approve asc, pof.tgl_pof desc, pof.no_pof desc")
                        ->paginate(10);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $nomor_pof_result = '';
            $data_order_pof = [];

            foreach($data_result as $data) {
                if(strtoupper(trim($nomor_pof_result)) == '') {
                    $nomor_pof_result = "'".strtoupper(trim($data->nomor_pof))."'";
                } else {
                    $nomor_pof_result .= ",'".strtoupper(trim($data->nomor_pof))."'";
                }
            }

            if(strtoupper(trim($nomor_pof_result)) != '') {
                $sql = "select	isnull(pof.no_pof, '') as order_code,
                                isnull(convert(varchar(10), tgl_pof, 120), '') + ' ' +
                                    convert(varchar, getdate(), 114) as order_date,
                                isnull(pof.kd_sales, '') as sales_code, isnull(salesman.nm_sales, '') as sales_name,
                                isnull(pof.kd_dealer, '') as dealer_code, isnull(dealer.nm_dealer, '') as dealer_name,
                                isnull(pof.umur_pof, 0) as umur_pof,
                                isnull(convert(varchar, pof.tgl_akhir_pof, 107), '') as jatuh_tempo,
                                isnull(pof.kd_tpc, '') as 'tpc', isnull(pof.disc, 0) as discount_header,
                                isnull(pof.bo, 'T') as 'status_bo', isnull(pof.total, 0) as 'total',
                                isnull(pof.approve, 0) as approve, isnull(pof.appr_usr, '') as approve_user,
                                isnull(pof.sts_fakt, 0) as status_faktur, isnull(pof.usertime, '') as usertime
                        from
                        (
                            select	pof.companyid, pof.no_pof, pof.tgl_pof, pof.kd_sales, pof.kd_dealer,
                                    pof.umur_pof, pof.tgl_akhir_pof, pof.bo, pof.approve, pof.appr_usr,
                                    pof.kd_tpc, pof.disc, pof.total, pof.sts_fakt, pof.usertime
                            from	pof with (nolock)
                            where	pof.no_pof in (".$nomor_pof_result.") and
                                    pof.companyid='".strtoupper(trim($request->userlogin->companyid))."'
                        )	pof
                                left join salesman with (nolock) on pof.kd_sales=salesman.kd_sales and
                                            pof.companyid=salesman.companyid
                                left join dealer with (nolock) on pof.kd_dealer=dealer.kd_dealer and
                                            pof.companyid=dealer.companyid
                        order by isnull(pof.approve, 0) asc, pof.tgl_pof desc, pof.no_pof desc";

                $result = DB::connection($request->get('divisi'))->select($sql);

                foreach($result as $result) {
                    $data_order_pof[] = [
                        'order_code'        => strtoupper(trim($result->order_code)),
                        'order_date'        => strtoupper(trim($result->order_date)),
                        'sales_code'        => strtoupper(trim($result->sales_code)),
                        'sales_name'        => strtoupper(trim($result->sales_name)),
                        'dealer_code'       => strtoupper(trim($result->dealer_code)),
                        'dealer_name'       => strtoupper(trim($result->dealer_name)),
                        'umur_pof'          => (double)$result->umur_pof.' Hari',
                        'tanggal_jatuh_tempo' => trim($result->jatuh_tempo),
                        'tpc'               => (int)$result->tpc,
                        'status_bo'         => (strtoupper(trim($result->status_bo)) == 'B') ? 'BACK ORDER' : 'TIDAK BO',
                        'discount_header'   => (double)$result->discount_header,
                        'total'             => (double)$result->total,
                        'approve'           => (int)$result->approve,
                        'approve_user'      => strtoupper(trim($result->approve_user)),
                        'on_faktur'         => (int)$result->status_faktur,
                        'usertime'          => strtoupper(trim($result->usertime)),
                    ];
                }
            }
            return ApiResponse::responseSuccess('success', $data_order_pof);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function detailPofOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof' => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Silahkan isi divisi dan nomor pof terlebih dahulu');
            }

            $sql = "select	isnull(pof.no_pof, '') as order_code, isnull(pof.tgl_pof, '') as order_date,
                            isnull(pof.kd_sales, '') as sales_code, isnull(salesman.nm_sales, '') as sales_name,
                            isnull(pof.kd_dealer, '') as dealer_code, isnull(dealer.nm_dealer, '') as dealer_name,
                            isnull(pof.ket, '') as keterangan, isnull(pof.kd_tpc, '') as tpc_code,
                            isnull(pof.umur_pof, 0) as umur_pof, isnull(pof.bo, '') as bo,
                            isnull(pof.disc, 0) as disc_header, isnull(pof.total, 0) as total,
                            isnull(pof.approve, 0) as approve, isnull(pof.appr_usr, '') as approve_user,
                            isnull(pof.sts_fakt, '') as status_faktur_header, isnull(pof_dtl.kd_part, '') as part_number,
                            isnull(part.ket, '') as part_description, isnull(produk.nama, '') as item_group,
                            isnull(pof_dtl.harga, 0) as amount, isnull(pof_dtl.jml_order, 0) as order_quantity,
                            isnull(pof_dtl.terlayani, 0) as served_quantity, isnull(pof_dtl.disc1, 0) as discount,
                            isnull(pof_dtl.jumlah, 0) as amount_total, isnull(pof_dtl.sts_fakt, 0) as status_faktur_detail,
                            case
                                when isnull(discp.kd_produk, '') <> '' then
                                    case
                                        when isnull(dealer_setting.kd_dealer, '') <> '' then
                                            case when isnull(dealer_setting.paretto, 0) = 1 then
                                                iif(isnull(dealer_setting.[top], 'T')='T',
                                                    isnull(discp.discp_tunai_khusus, 0),
                                                    isnull(discp.discp_rekening_khusus, 0)
                                                )
                                            else
                                                iif(isnull(dealer_setting.[top], 'T')='T',
                                                    isnull(discp.discp_tunai, 0),
                                                    isnull(discp.discp_rekening, 0)
                                                )
                                            end
                                        else
                                            isnull(discp.discp_tunai, 0)
                                    end
                                else 0
                            end as disc_max_produk, isnull(part.hrg_netto, 0) as hrg_netto, isnull(bo.jumlah, 0) as jumlah_bo,
                            isnull(part.hrg_pokok, 0) + round(((isnull(part.hrg_pokok, 0) * isnull(company.ppn, 0)) / 100), 0) as hrg_netto_part,
                            isnull(pof_dtl.harga, 0) - round(((isnull(pof_dtl.harga, 0) * isnull(pof_dtl.disc1, 0)) / 100), 0) -
                                round((((isnull(pof_dtl.harga, 0) -
                                    round(((isnull(pof_dtl.harga, 0) * isnull(pof_dtl.disc1, 0)) / 100), 0)) *
                                        isnull(pof.disc, 0)) / 100), 0) as total_netto_part,
                            isnull(company.kd_file, '') as kode_file,
                            isnull(pof.usertime, '') as usertime
                    from
                    (
                        select	pof.companyid, pof.no_pof, pof.tgl_pof, pof.kd_sales,
                                pof.kd_dealer, pof.ket, pof.kd_tpc, pof.umur_pof,
                                pof.tgl_akhir_pof, pof.approve, pof.appr_usr,
                                pof.bo, pof.disc, pof.total, pof.sts_fakt,
                                pof.usertime
                        from	pof with (nolock)
                        where	pof.no_pof='".strtoupper(trim($request->get('nomor_pof')))."' and
                                pof.companyid='".strtoupper(trim($request->userlogin->companyid))."'";

            if (!empty($request->get('salesman')) && trim($request->get('salesman')) != '') {
                $sql .= " and pof.kd_sales='".strtoupper(trim($request->get('salesman')))."'";
            }

            if (!empty($request->get('dealer')) && trim($request->get('dealer')) != '') {
                $sql .= " and pof.kd_dealer='".strtoupper(trim($request->get('dealer')))."'";
            }

            $sql .= " )	pof
                            inner join company with (nolock) on pof.companyid=company.companyid
                            left join salesman with (nolock) on pof.kd_sales=salesman.kd_sales and
                                        pof.companyid=salesman.companyid
                            left join dealer with (nolock) on pof.kd_dealer=dealer.kd_dealer and
                                        pof.companyid=dealer.companyid
                            inner join pof_dtl with (nolock) on pof.no_pof=pof_dtl.no_pof and
                                        pof.companyid=pof_dtl.companyid
                            left join part with (nolock) on pof_dtl.kd_part=part.kd_part and
                                        pof.companyid=part.companyid
                            left join sub with (nolock) on part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                            left join bo with (nolock) on pof_dtl.kd_part=bo.kd_part and
                                        pof.kd_dealer=bo.kd_dealer and
                                        pof.companyid=bo.companyid
                            left join discp with (nolock) on produk.kd_produk=discp.kd_produk and
                                        iif(isnull(company.inisial, 0)=1, 'RK', 'PC')=discp.cabang
                            left join dealer_setting with (nolock) on pof.kd_dealer=dealer_setting.kd_dealer and
                                        pof.companyid=dealer_setting.companyid
                    order by pof_dtl.kd_part asc";

            $result = DB::connection($request->get('divisi'))->select($sql);

            $data_order = new Collection();
            $data_detail_order = [];

            foreach($result as $result) {
                $notes_diskon = '';
                $notes_harga = '';
                $notes_bo = '';
                $notes_marketing = '';

                if((double)$result->jumlah_bo > 0) {
                    $notes_bo = '*) Sudah ada di BO sejumlah '.number_format($result->jumlah_bo).' PCS';
                }

                if((double)$result->hrg_netto_part > (double)$result->total_netto_part) {
                    $notes_harga = '*) Penjualan rugi, total harga jual lebih rendah dari harga yang telah di tentukan';
                } else {
                    if((double)$result->hrg_netto > 0) {
                        if((double)$result->hrg_netto > (double)$result->total_netto_part) {
                            $notes_harga = '*) Penjualan rugi, total harga jual lebih rendah dari harga netto';
                        }
                    }
                }

                if(strtoupper(trim($result->tpc_code)) == '14') {
                    if((double)$result->disc_max_produk > 0) {
                        if((double)$result->disc_header > (double)$result->disc_max_produk) {
                            $notes_diskon = '*) Diskon maksimal produk '.strtoupper(trim($result->item_group)).' : '.number_format((double)$result->disc_header, 2);
                        }
                        if((double)$result->discount > (double)$result->disc_max_produk) {
                            $notes_diskon = '*) Diskon maksimal produk '.strtoupper(trim($result->item_group)).' : '.number_format((double)$result->disc_header, 2);
                        }
                    }
                }

                if(strtoupper(trim($result->kode_file)) == 'A') {
                    if((double)$result->disc_header > 0 && (double)$result->discount > 0) {
                        $notes_diskon = '*) Part number di diskon 2x';
                    }
                }

                $data_order->push((object) [
                    'order_code'    => strtoupper(trim($result->order_code)),
                    'order_date'    => strtoupper(trim($result->order_date)),
                    'sales_code'    => strtoupper(trim($result->sales_code)),
                    'sales_name'    => strtoupper(trim($result->sales_name)),
                    'dealer_code'   => strtoupper(trim($result->dealer_code)),
                    'dealer_name'   => strtoupper(trim($result->dealer_name)),
                    'keterangan'    => strtoupper(trim($result->keterangan)),
                    'tpc_code'      => strtoupper(trim($result->tpc_code)),
                    'umur_pof'      => (double)$result->umur_pof,
                    'back_order'    => (strtoupper(trim($result->bo)) == 'B') ? 'BACK ORDER' : 'TIDAK BO',
                    'discount'      => (double)$result->disc_header,
                    'total'         => (double)$result->total,
                    'approve'       => (int)$result->approve,
                    'approve_user'  => strtoupper(trim($result->approve_user)),
                    'usertime'      => strtoupper(trim($result->usertime)),
                    'status_faktur' => (int)$result->status_faktur_header,
                ]);

                $data_detail_order[] = [
                    'order_code'        => strtoupper(trim($result->order_code)),
                    'part_number'       => strtoupper(trim($result->part_number)),
                    'part_description'  => strtoupper(trim($result->part_description)),
                    'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($result->part_number)).'.jpg',
                    'item_group'        => strtoupper(trim($result->item_group)),
                    'order_quantity'    => (double)$result->order_quantity,
                    'served_quantity'   => (double)$result->served_quantity,
                    'amount'            => (double)$result->amount,
                    'discount'          => (double)$result->discount,
                    'amount_total'      => (double)$result->amount_total,
                    'status_faktur'     => (int)$result->status_faktur_detail,
                    'notes_diskon'      => trim($notes_diskon),
                    'notes_harga'       => trim($notes_harga),
                    'notes_bo'          => trim($notes_bo),
                    'notes_marketing'   => trim($notes_marketing),
                ];
            }

            $result_order = new Collection();

            foreach($data_order as $data) {
                $result_order->push((object) [
                    'order_code'    => strtoupper(trim($data->order_code)),
                    'order_date'    => strtoupper(trim($data->order_date))." ".date('h:i:s'),
                    'sales_code'    => strtoupper(trim($data->sales_code)),
                    'sales_name'    => strtoupper(trim($data->sales_name)),
                    'dealer_code'   => strtoupper(trim($data->dealer_code)),
                    'dealer_name'   => strtoupper(trim($data->dealer_name)),
                    'keterangan'    => strtoupper(trim($data->keterangan)),
                    'tpc_code'      => strtoupper(trim($data->tpc_code)),
                    'umur_pof'      => (double)$data->umur_pof,
                    'back_order'    => strtoupper(trim($data->back_order)),
                    'discount'      => (double)$data->discount,
                    'total'         => (double)$data->total,
                    'approve'       => (int)$data->approve,
                    'approve_user'  => strtoupper(trim($data->approve_user)),
                    'usertime'      => strtoupper(trim($data->usertime)),
                    'status_faktur' => (int)$data->status_faktur,
                    'detail'        => $data_detail_order
                ]);
            }

            $data = [
                'data'  => $result_order->first()
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function approveOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof' => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Silahkan isi data divisi dan nomor pof terlebih dahulu');
            }

            $sql = "select	isnull(pof.companyid, '') as companyid, isnull(pof.no_pof, '') as no_pof,
                            isnull(pof.kd_sales, '') as kode_sales, isnull(pof.kd_dealer, '') as kode_dealer,
                            isnull(pof.kd_dealer_discnol, '') as kode_dealer_disc_nol,
                            isnull(pof.kd_tpc, '') as kode_tpc, isnull(pof_dtl.kd_part, '') as part_number,
                            isnull(pof.disc, 0) as disc_header, isnull(pof_dtl.disc1, 0) as disc_detail,
                            isnull(pof.user_entry, '') as user_entry
                    from
                    (
                        select	top 1 pof.companyid, pof.no_pof, pof.kd_sales, pof.kd_dealer, pof.user_entry,
                                pof.kd_tpc, pof.disc, faktur_discnol.kd_dealer as kd_dealer_discnol
                        from	pof with (nolock)
                                    left join faktur_discnol with (nolock) on pof.kd_dealer=faktur_discnol.kd_dealer and
                                                pof.companyid=faktur_discnol.companyid
                        where	pof.no_pof='".strtoupper(trim($request->get('nomor_pof')))."' and
                                pof.companyid='".strtoupper(trim($request->userlogin->companyid))."'
                    )	pof
                            left join pof_dtl with (nolock) on pof.no_pof=pof_dtl.no_order and
                                        pof.companyid=pof_dtl.companyid";

            $result_check_disc = DB::connection($request->get('divisi'))->select($sql);

            $jumlah_data = 0;
            $user_entry = '';

            foreach($result_check_disc as $data) {
                $jumlah_data = (double)$jumlah_data + 1;

                $user_entry = strtoupper(trim($data->user_entry));

                if(trim($data->kode_tpc) == '14') {
                    if(trim($data->kode_dealer_disc_nol) == '') {
                        if((double)$data->disc_header <= 0) {
                            if((double)$data->disc_detail <= 0) {
                                return ApiResponse::responseWarning('Nomor pof tidak dapat di approve karena ada part number yang belum di discount');
                            }
                        }
                    }
                }
            }

            if((double)$jumlah_data <= 0) {
                return ApiResponse::responseWarning('Nomor POF tidak terdaftar');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))
                    ->insert('exec SP_Pof_Approve ?,?,?', [
                        strtoupper(trim($request->get('nomor_pof'))), strtoupper(trim($request->userlogin->user_id)),
                        strtoupper(trim($request->userlogin->companyid))
                ]);
            });

            // =======================================================================================================
            // Notification - Salesman / Dealer
            // =======================================================================================================
            $sql = DB::connection($request->get('divisi'))->table('users')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.companyid, '') as companyid,
                                isnull(user_api_sessions.id, 0) as id,
                                isnull(users.user_id, '') as user_id,
                                isnull(users.email, '') as email,
                                isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(user_api_sessions.fcm_id, '') as fcm_id")
                    ->leftJoin(DB::raw('user_api_sessions with (nolock)'), function($join) {
                        $join->on('user_api_sessions.user_id', '=', 'users.user_id')
                            ->on('user_api_sessions.companyid', '=', 'users.companyid');
                    })
                    ->where('users.user_id', strtoupper(trim($user_entry)))
                    ->orderBy('user_api_sessions.id', 'desc')
                    ->first();

            if(!empty($sql->fcm_id)) {
                if(trim($sql->fcm_id) != '') {
                    $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
                    $url = 'notification/push';
                    $header = [ 'Authorization' => $credential ];
                    $body = [
                        'email'         => trim($sql->email),
                        'type'          => 'POF',
                        'title'         => 'Approved Purchase Order Form',
                        'message'       => 'Nomor order '.strtoupper(trim($request->get('nomor_pof'))).' anda sudah di approve oleh supervisor',
                        'code'          => strtoupper(trim($request->get('nomor_pof'))),
                        'user_process'  => strtoupper(trim($request->userlogin->user_id)),
                        'divisi'        => $request->get('divisi')
                    ];
                    ApiRequest::requestPost($url, $header, $body);
                }
            }
            // =======================================================================================================
            //
            // =======================================================================================================

            return ApiResponse::responseSuccess('Data Berhasil Di Approve', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function cancelApprove(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof'  => 'required|string',
                'divisi'     => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Pilih data divisi dan nomor pof terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.kd_tpc, '') as kode_tpc,
                                isnull(pof.approve, 0) as approved,
                                isnull(pof.user_entry, '') as user_entry")
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Nomor POF tidak terdaftar');
            }

            $user_entry = strtoupper(trim($sql->user_entry));

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))
                    ->insert('exec SP_Pof_BatalApprove ?,?,?', [
                        strtoupper(trim($request->get('nomor_pof'))), strtoupper(trim($request->userlogin->user_id)),
                        strtoupper(trim($request->userlogin->companyid))
                    ]);
            });

            // =======================================================================================================
            // Notification - Salesman / Dealer
            // =======================================================================================================
            $sql = DB::connection($request->get('divisi'))->table('users')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.companyid, '') as companyid,
                                isnull(user_api_sessions.id, 0) as id,
                                isnull(users.user_id, '') as user_id,
                                isnull(users.email, '') as email,
                                isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(user_api_sessions.fcm_id, '') as fcm_id")
                    ->leftJoin(DB::raw('user_api_sessions with (nolock)'), function($join) {
                        $join->on('user_api_sessions.user_id', '=', 'users.user_id')
                            ->on('user_api_sessions.companyid', '=', 'users.companyid');
                    })
                    ->where('users.user_id', strtoupper(trim($user_entry)))
                    ->orderBy('user_api_sessions.id', 'desc')
                    ->first();

            if(!empty($sql->fcm_id)) {
                if(trim($sql->fcm_id) != '') {
                    $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
                    $url = 'notification/push';
                    $header = [ 'Authorization' => $credential ];
                    $body = [
                        'email'         => trim($sql->email),
                        'type'          => 'POF',
                        'title'         => 'Cancel Approve Purchase Order Form',
                        'message'       => 'Status approve nomor order '.strtoupper(trim($request->get('nomor_pof'))).' di cancel oleh supervisor',
                        'code'          => strtoupper(trim($request->get('nomor_pof'))),
                        'user_process'  => strtoupper(trim($request->userlogin->user_id)),
                        'divisi'        => $request->get('divisi')
                    ];
                    ApiRequest::requestPost($url, $header, $body);
                }
            }
            // =======================================================================================================
            //
            // =======================================================================================================

            return ApiResponse::responseSuccess('Approve POF berhasil dibatalkan', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateTpc(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof' => 'required|string',
                'tpc'       => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, nomor pof, dan kode tpc tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.kd_tpc, '') as kode_tpc,
                                isnull(pof.approve, 0) as approved")
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Nomor pof yang anda pilih atau entry tidak terdaftar');
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            if(trim($request->get('tpc')) != trim($sql->kode_tpc)) {
                DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                    DB::connection($request->get('divisi'))
                        ->insert('exec SP_Pof_UpdateTpc ?,?,?,?', [
                            strtoupper(trim($request->get('nomor_pof'))),
                            strtoupper(trim($request->get('tpc'))),
                            strtoupper(trim($request->userlogin->user_id)),
                            strtoupper(trim($request->userlogin->companyid))
                        ]);
                });
            }

            return ApiResponse::responseSuccess('Data TPC Berhasil Diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateStatusBo(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof' => 'required|string',
                'status_bo' => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, nomor pof, dan status back order tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.approve, 0) as approved")
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Nomor pof yang anda pilih atau entry tidak terdaftar');
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->update('update pof set bo=? where no_pof=? and companyid=?', [
                    strtoupper(trim($request->get('status_bo'))),
                    strtoupper(trim($request->get('nomor_pof'))),
                    strtoupper(trim($request->userlogin->companyid)),
                ]);
            });

            return ApiResponse::responseSuccess('Data Status Back Order Berhasil Diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateUmurPof(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof' => 'required|string',
                'umur_pof'  => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, nomor pof, dan umur pof tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.approve, 0) as approved")
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Nomor pof yang anda pilih atau entry tidak terdaftar');
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                $umur_pof = (int)$request->get('umur_pof');

                DB::connection($request->get('divisi'))->update('update  pof
                            set     umur_pof=?,
                                    tgl_akhir_pof=convert(varchar(10),dateadd(day,'.(int)$umur_pof.',pof.tgl_pof), 120)
                            where   no_pof=? and companyid=?', [
                    (int)$umur_pof, strtoupper(trim($request->get('nomor_pof'))),
                    strtoupper(trim($request->userlogin->companyid)),
                ]);
            });

            return ApiResponse::responseSuccess('Data Umur POF Berhasil Diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateKeterangan(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof'     => 'required|string',
                'divisi'        => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi dan nomor pof tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.approve, 0) as approved")
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Nomor pof yang anda pilih atau entry tidak terdaftar');
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->update('update pof set ket=? where no_pof=? and companyid=?', [
                    strtoupper(trim($request->get('keterangan'))),
                    strtoupper(trim($request->get('nomor_pof'))),
                    strtoupper(trim($request->userlogin->companyid)),
                ]);
            });

            return ApiResponse::responseSuccess('Data Keterangan Berhasil Diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateDiscHeader(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof' => 'required|string',
                'discount'  => 'required|string',
                'divisi'    => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, nomor pof, dan discount tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.approve, 0) as approved")
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Nomor pof yang anda pilih atau entry tidak terdaftar');
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->insert('exec SP_Pof_UpdateDiscount ?,?,?', [
                    strtoupper(trim($request->get('nomor_pof'))),
                    (double)$request->get('discount'),
                    strtoupper(trim($request->userlogin->companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data Discount Berhasil Diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function hapusPofOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof'     => 'required|string',
                'divisi'        => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Pilih divisi dan nomor pof terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.kd_tpc, '') as kode_tpc,
                                isnull(pof.approve, 0) as approved")
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Nomor POF tidak terdaftar');
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di hapus');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->insert('exec SP_Pof_Hapus ?,?', [
                    strtoupper(trim($request->get('nomor_pof'))), strtoupper(trim($request->userlogin->companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data part number berhasil dihapus', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateHargaDetail(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof'     => 'required|string',
                'part_number'   => 'required|string',
                'harga'         => 'required|string',
                'divisi'        => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, nomor pof, part number, dan harga part tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.kd_tpc, '') as kode_tpc,
                                isnull(pof.approve, 0) as approved")
                    ->leftJoin(DB::raw('pof_dtl with (nolock)'), function($join) {
                        $join->on('pof_dtl.no_pof', '=', 'pof.no_pof')
                            ->on('pof_dtl.companyid', '=', 'pof.companyid');
                    })
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof_dtl.kd_part', strtoupper(trim($request->get('part_number'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Part number '.strtoupper(trim($request->get('part_number'))).' tidak terdaftar di dalam nomor pof'.strtoupper(trim($request->get('nomor_pof'))));
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            if(trim($sql->kode_tpc) == '14') {
                return ApiResponse::responseWarning('TPC nomor pof '.strtoupper(trim($request->get('nomor_pof'))).' adalah TPC 14. Kode TPC 14 tidak boleh mengganti harga');
            }

            $sql = DB::connection($request->get('divisi'))->table('part')->lock('with (nolock)')
                    ->selectRaw("isnull(part.kd_part, '') as part_number,
                                isnull(part.hrg_netto, 0) as harga_netto,
                                isnull(part.cek_hpp, 0) as status_cek_hpp,
                                cast(isnull(part.hrg_pokok, 0) +
                                    round(((isnull(part.hrg_pokok, 0) * isnull(company.ppn, 0)) / 100), 0) as decimal(13, 0)) as harga_pokok")
                    ->leftJoin(DB::raw('company with (nolock)'), function($join) {
                        $join->on('company.companyid', '=', 'part.companyid');
                    })
                    ->where('part.kd_part', strtoupper(trim($request->get('part_number'))))
                    ->where('part.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Part number yang anda entry tidak terdaftar');
            }

            if((int)$sql->status_cek_hpp == 1) {
                if((double)$sql->harga_pokok > (double)$request->get('harga')) {
                    return ApiResponse::responseWarning('Penjualan Rugi, Harga yang anda entry tidak boleh lebih rendah dari yang telah ditentukan');
                }
            }

            if ((double)$sql->harga_netto > (double)$request->get('harga')) {
                if((double)$sql->harga_netto > 0) {
                    return ApiResponse::responseWarning('Penjualan Rugi, Harga yang anda entry lebih rendah dari harga netto terendah');
                }
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->insert('exec SP_PofDtl_UpdateHarga ?,?,?,?,?', [
                    strtoupper(trim($request->get('nomor_pof'))), strtoupper(trim($request->get('part_number'))),
                    (double)$request->get('harga'), strtoupper(trim($request->userlogin->user_id)),
                    strtoupper(trim($request->userlogin->companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data harga part number '.strtoupper(trim($request->get('part_number'))).' berhasil diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateQuantity(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof'     => 'required|string',
                'part_number'   => 'required|string',
                'quantity'      => 'required|string',
                'divisi'        => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, nomor pof, part number, dan jumlah order tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.approve, 0) as approved")
                    ->leftJoin(DB::raw('pof_dtl with (nolock)'), function($join) {
                        $join->on('pof_dtl.no_pof', '=', 'pof.no_pof')
                            ->on('pof_dtl.companyid', '=', 'pof.companyid');
                    })
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof_dtl.kd_part', strtoupper(trim($request->get('part_number'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Part number '.strtoupper(trim($request->get('part_number'))).' tidak terdaftar di dalam nomor pof'.strtoupper(trim($request->get('nomor_pof'))));
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->insert('exec SP_PofDtl_UpdateJmlOrder ?,?,?,?,?', [
                    strtoupper(trim($request->get('nomor_pof'))), strtoupper(trim($request->get('part_number'))),
                    (double)$request->get('quantity'), strtoupper(trim($request->userlogin->user_id)),
                    strtoupper(trim($request->userlogin->companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data quantity part number '.strtoupper(trim($request->get('part_number'))).' berhasil diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateDiscDetail(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof'     => 'required|string',
                'part_number'   => 'required|string',
                'discount'      => 'required|string',
                'divisi'        => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, nomor pof, part number, dan discount tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.kd_tpc, '') as kode_tpc,
                                isnull(pof.approve, 0) as approved")
                    ->leftJoin(DB::raw('pof_dtl with (nolock)'), function($join) {
                        $join->on('pof_dtl.no_pof', '=', 'pof.no_pof')
                            ->on('pof_dtl.companyid', '=', 'pof.companyid');
                    })
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof_dtl.kd_part', strtoupper(trim($request->get('part_number'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Part number '.strtoupper(trim($request->get('part_number'))).' tidak terdaftar di dalam nomor pof'.strtoupper(trim($request->get('nomor_pof'))));
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            if(trim($sql->kode_tpc) == '20') {
                return ApiResponse::responseWarning('TPC nomor pof '.strtoupper(trim($request->get('nomor_pof'))).' adalah TPC 20. Kode TPC 20 tidak boleh mengganti diskon');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->insert('exec SP_PofDtl_UpdateDiscDetail ?,?,?,?,?', [
                    strtoupper(trim($request->get('nomor_pof'))), strtoupper(trim($request->get('part_number'))),
                    (double)$request->get('discount'), strtoupper(trim($request->userlogin->user_id)),
                    strtoupper(trim($request->userlogin->companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data discount part number '.strtoupper(trim($request->get('part_number'))).' berhasil diubah', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function hapusPartNumber(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_pof'     => 'required|string',
                'part_number'   => 'required|string',
                'divisi'        => 'required|string',
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Pilih data divisi, nomor pof, dan part number terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof,
                                isnull(pof.kd_tpc, '') as kode_tpc,
                                isnull(pof.approve, 0) as approved")
                    ->leftJoin(DB::raw('pof_dtl with (nolock)'), function($join) {
                        $join->on('pof_dtl.no_pof', '=', 'pof.no_pof')
                            ->on('pof_dtl.companyid', '=', 'pof.companyid');
                    })
                    ->where('pof.no_pof', strtoupper(trim($request->get('nomor_pof'))))
                    ->where('pof_dtl.kd_part', strtoupper(trim($request->get('part_number'))))
                    ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->first();

            if(empty($sql->nomor_pof) || trim($sql->nomor_pof) == '') {
                return ApiResponse::responseWarning('Part number '.strtoupper(trim($request->get('part_number'))).
                                        ' tidak terdaftar di dalam nomor pof'.strtoupper(trim($request->get('nomor_pof'))));
            }

            if($sql->approved == 1) {
                return ApiResponse::responseWarning('POF yang sudah di approve tidak bisa di edit');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request) {
                DB::connection($request->get('divisi'))->insert('exec SP_PofDtl_HapusPart ?,?,?,?', [
                    strtoupper(trim($request->get('nomor_pof'))), strtoupper(trim($request->get('part_number'))),
                    strtoupper(trim($request->userlogin->user_id)), strtoupper(trim($request->userlogin->companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data part number berhasil dihapus', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

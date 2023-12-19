<?php

namespace App\Http\Controllers\Api\Part;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller {

    public function addCart(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'item_part'     => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Silahkan isi data divisi, data dealer, dan part number terlebih dahulu');
            }

            $data_item_part = json_decode(str_replace('\\', '', trim($request->get('item_part'))));

            return ApiResponse::responseSuccess('success', $data_item_part);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function addToCart(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'id_part'       => 'required',
                'quantity'      => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Data divisi, dealer Id, dan part Id tidak boleh kosong');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $role_id = strtoupper(trim($request->userlogin['role_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))
                    ->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = DB::connection($request->get('divisi'))
                    ->table('mspart')->lock('with (nolock)')
                    ->selectRaw("isnull(mspart.kd_part, '') as part_number")
                    ->where('mspart.id', $request->get('id_part'))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Id part yang anda entry tidak terdaftar');
            }
            $part_number = strtoupper(trim($sql->part_number));

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $part_number, $role_id, $user_id, $companyid) {
                DB::connection($request->get('divisi'))
                    ->insert("exec SP_CartDtlTmp_AddToCart ?,?,?,?,?,?", [
                        strtoupper(trim($kode_dealer)), strtoupper(trim(trim($part_number))),
                        (double)$request->get('quantity'), strtoupper(trim($role_id)),
                        strtoupper(trim($user_id)), strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('success', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listCart(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()){
                return ApiResponse::responseWarning('Isi data divisi dan dealer Id tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));
            $kode_key = strtoupper(trim($request->userlogin['user_id']))."/".strtoupper(trim($kode_dealer));

            $sql = DB::connection($request->get('divisi'))
                    ->table('carttmp')->lock('with (nolock)')
                    ->selectRaw("isnull(carttmp.kd_key, '') as kode_key, isnull(carttmp.no_order, '') as no_order,
                                isnull(carttmp.kd_dealer, '') as dealer_code, isnull(dealer.nm_dealer, '') as dealer_name,
                                isnull(carttmp.kd_tpc, 0) as tpc_code, isnull(carttmp.user_id, '') as user_id,
                                isnull(carttmp.total, 0) as total_price, isnull(carttmp.sub_total, 0) as sub_price,
                                isnull(carttmp.disc2, 0) as discount, count(cart_dtltmp.kd_key) as total_item")
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'carttmp.kd_dealer')
                            ->on('dealer.companyid', '=', 'carttmp.companyid');
                    })
                    ->leftJoin(DB::raw('cart_dtltmp with (nolock)'), function($join) {
                        $join->on('cart_dtltmp.kd_key', '=', 'carttmp.kd_key')
                            ->on('cart_dtltmp.companyid', '=', 'carttmp.companyid');
                    })
                    ->where('carttmp.kd_key', strtoupper(trim($kode_key)))
                    ->where('carttmp.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->groupByRaw("carttmp.kd_key, carttmp.no_order, carttmp.kd_dealer,
                                dealer.nm_dealer, carttmp.kd_tpc, carttmp.user_id,
                                carttmp.total, carttmp.sub_total, carttmp.disc2")
                    ->first();

            if(empty($sql->no_order)) {
                $data_cart_part = [
                    'id'                => trim($request->userlogin['id']),
                    'no_order'          => null,
                    'dealer_code'       => strtoupper(trim($kode_dealer)),
                    'dealer_name'       => null,
                    'tpc_code'          => null,
                    'users_id'          => (int)$request->userlogin['id'],
                    'total_item'        => 0,
                    'total_price'       => 0,
                    'sub_price'         => 0,
                    'discount'          => 0,
                    'month_delivery'    => null,
                    'total_next_page'   => 0,
                    'detail'            => []
                ];

                $data_list_cart = [
                    'data' => $data_cart_part
                ];

                return ApiResponse::responseSuccess('success', $data_list_cart);
            }

            $data_cart_part = [
                'id'                => trim($request->userlogin['id']),
                'no_order'          => $sql->no_order,
                'dealer_code'       => $sql->dealer_code,
                'dealer_name'       => $sql->dealer_name,
                'tpc_code'          => (int)$sql->tpc_code,
                'users_id'          => (int)$request->userlogin['id'],
                'total_item'        => (double)$sql->total_item,
                'total_price'       => (double)$sql->total_price,
                'sub_price'         => (double)$sql->sub_price,
                'discount'          => (double)$sql->discount,
                'month_delivery'    => null
            ];

            $sql = DB::connection($request->get('divisi'))
                    ->table('cart_dtltmp')->lock('with (nolock)')
                    ->selectRaw("isnull(cart_dtltmp.kd_part, '') as part_number")
                    ->where('cart_dtltmp.kd_key', strtoupper(trim($kode_key)))
                    ->where('cart_dtltmp.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->paginate(15);

            $data = $sql->items();
            $next_page = (double)$sql->total() - ((double)$sql->perPage() * $request->get('page'));
            $total_next_page = ((double)$next_page <= 0) ? 0: $next_page;

            $list_part_number = '';
            $jumlah_data = 0;

            foreach($data as $data) {
                $jumlah_data = (double)$jumlah_data + 1;

                if(trim($list_part_number) == '') {
                    $list_part_number = "'".strtoupper(trim($data->part_number))."'";
                } else {
                    $list_part_number .= ",'".strtoupper(trim($data->part_number))."'";
                }
            }

            $data_detail_cart = [];

            if((double)$jumlah_data > 0) {
                $sql = "select	'".trim($request->userlogin['id'])."' as id, isnull(carttmp.no_order, '') as no_order,
                                isnull(company.kd_file, '') as kode_file, isnull(company.ppn, 0) as ppn,
                                isnull(carttmp.total, 0) as total_price, isnull(carttmp.kd_tpc, '14') as tpc_code,
                                isnull(carttmp.status, '') as status, isnull(carttmp.disc2, 0) as discount,
                                isnull(carttmp.month_delivery, '') as month_delivery,
                                isnull(msdealer.id, 0) as ms_dealer_id, isnull(carttmp.kd_dealer, '') as dealer_code,
                                isnull(dealer.nm_dealer, '') as dealer_name, isnull(carttmp.kd_part, '') as part_number,
                                isnull(carttmp.part_description, '') as part_description,
                                isnull(carttmp.het, 0) as het, isnull(produk.nama, 0) as item_group,
                                iif(isnull(carttmp.stock, 0) < 0, 0, isnull(carttmp.stock, 0)) as stock,
                                isnull(carttmp.id_part, 0) as id_part, isnull(carttmp.jml_order, 0) as qty,
                                isnull(carttmp.harga, 0) as sub_price, isnull(carttmp.disc1, 0) as disc_detail,
                                isnull(carttmp.jumlah, 0) as amount_total, isnull(discp.discp, 0) as discount_produk,
                                isnull(carttmp.hrg_netto, 0) as harga_netto,
                                isnull(carttmp.hrg_pokok, 0) +
                                    round(((isnull(carttmp.hrg_pokok, 0) * isnull(company.ppn, 0)) / 100), 0) as harga_terendah,
                                isnull(carttmp.harga, 0) -
                                    round(((isnull(carttmp.harga, 0) * isnull(carttmp.disc1, 0)) / 100), 0) -
                                        round((((isnull(carttmp.harga, 0) -
                                            round(((isnull(carttmp.harga, 0) * isnull(carttmp.disc1, 0)) / 100), 0)) *
                                                isnull(carttmp.disc2, 0)) / 100), 0) as harga_netto_part,
                                isnull(bo.jumlah, 0) as jumlah_bo, isnull(carttmp.usertime, '') usertime
                        from
                        (
                            select	carttmp.companyid, carttmp.kd_key,
                                    carttmp.no_order, carttmp.tgl_order, carttmp.kd_sales,
                                    carttmp.kd_dealer, carttmp.kd_tpc, carttmp.status,
                                    carttmp.created_at, carttmp.updated_at, carttmp.month_delivery,
                                    carttmp.sub_total, carttmp.total, carttmp.disc2, company.kd_lokasi,
                                    mspart.id as id_part, carttmp.kd_part, part.ket as part_description, part.kd_sub,
                                    carttmp.jml_order, carttmp.harga, carttmp.disc1, carttmp.jumlah,
                                    part.het, part.jml1dus, part.hrg_netto, part.hrg_pokok,
                                    isnull(tbstlokasirak.stock, 0) -
                                        (isnull(stlokasi.min, 0) + isnull(stlokasi.in_transit, 0) +
                                            isnull(part.kanvas, 0) + isnull(part.min_gudang, 0) + isnull(part.in_transit, 0) +
                                                isnull(part.konsinyasi, 0) + isnull(part.min_htl, 0)) as stock,
                                    carttmp.usertime
                            from
                            (
                                select  carttmp.companyid, carttmp.kd_key, carttmp.no_order,
                                        carttmp.tgl_order, carttmp.kd_sales, carttmp.kd_dealer, carttmp.kd_tpc,
                                        carttmp.created_at, carttmp.updated_at, carttmp.sub_total,
                                        carttmp.disc2, carttmp.total, carttmp.status,
                                        carttmp.month_delivery, cart_dtltmp.kd_part, cart_dtltmp.jml_order,
                                        cart_dtltmp.harga, cart_dtltmp.disc1, cart_dtltmp.jumlah,
                                        cart_dtltmp.usertime
                                from
                                (
                                    select	carttmp.companyid, carttmp.kd_key, carttmp.no_order,
                                            carttmp.tgl_order, carttmp.kd_sales, carttmp.kd_dealer, carttmp.kd_tpc,
                                            carttmp.created_at, carttmp.updated_at, carttmp.sub_total,
                                            carttmp.disc2, carttmp.total, carttmp.status,
                                            carttmp.month_delivery
                                    from	carttmp with (nolock)
                                    where	carttmp.kd_key='".strtoupper(trim($request->userlogin['user_id']))."/".strtoupper(trim($kode_dealer))."' and
                                            carttmp.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                                )   carttmp
                                        inner join cart_dtltmp with (nolock) on carttmp.kd_key=cart_dtltmp.kd_key and
                                                    carttmp.companyid=cart_dtltmp.companyid
                                where	cart_dtltmp.kd_part in (".$list_part_number.")
                            )	carttmp
                                    inner join company with (nolock) on carttmp.companyid=company.companyid
                                    inner join mspart with (nolock) on carttmp.kd_part=mspart.kd_part
                                    left join part with (nolock) on carttmp.kd_part=part.kd_part and
                                                carttmp.companyid=part.companyid
                                    left join stlokasi with (nolock) on carttmp.kd_part=stlokasi.kd_part and
                                                company.kd_lokasi=stlokasi.kd_lokasi and
                                                carttmp.companyid=stlokasi.companyid
                                    left join tbstlokasirak with (nolock) on carttmp.kd_part=tbstlokasirak.kd_part and
                                                company.kd_lokasi=tbstlokasirak.kd_lokasi and
                                                company.kd_rak=tbstlokasirak.kd_rak and
                                                carttmp.companyid=tbstlokasirak.companyid
                        )	carttmp
                                inner join company with (nolock) on carttmp.companyid=company.companyid
                                left join msdealer with (nolock) on carttmp.kd_dealer=msdealer.kd_dealer and
                                            carttmp.companyid=msdealer.companyid
                                left join dealer with (nolock) on carttmp.kd_dealer=dealer.kd_dealer and
                                            carttmp.companyid=dealer.companyid
                                left join sub with (nolock) on carttmp.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                                left join bo with (nolock) on carttmp.kd_part=bo.kd_part and
                                            carttmp.companyid=bo.companyid and
                                            '".strtoupper(trim($kode_dealer))."'=bo.kd_dealer
                                left join discp with (nolock) on produk.kd_produk=discp.kd_produk and
                                            discp.cabang=iif(isnull(company.inisial, 0) = 1, 'RK', 'PC')
                        order by carttmp.kd_part asc";

                $result = DB::connection($request->get('divisi'))->select($sql);

                foreach($result as $data) {
                    $available_part = '';

                    if((double)$data->stock <= 0) {
                        $available_part = 'Not Available';
                    } else {
                        if(strtoupper(trim($request->userlogin['role_id'])) != 'MD_H3_MGMT') {
                            $available_part = 'Available';
                        } else {
                            $available_part = 'Available '.number_format($data->stock).' pcs';
                        }
                    }

                    $notes_marketing = '';
                    $notes_diskon = '';
                    $notes_harga = '';
                    $notes_bo = '';

                    if(strtoupper(trim($request->userlogin['role_id'])) != 'D_H3') {
                        if((double)$data->discount_produk > 0) {
                            if((double)$data->discount > (double)$data->discount_produk) {
                                $notes_diskon = '*) Disc Max Produk '.trim($data->item_group).' : '.number_format($data->discount_produk, 2).' %';
                            } elseif((double)$data->disc_detail > (double)$data->discount_produk) {
                                $notes_diskon = '*) Disc Max Produk '.trim($data->item_group).' : '.number_format($data->discount_produk, 2).' %';
                            }
                        }

                        if((double)$data->harga_terendah > (double)$data->harga_netto_part) {
                            $notes_harga = '*) Total harga parts lebih rendah dari harga yang telah ditentukan';
                        } else {
                            if((double)$data->harga_netto > 0) {
                                if((double)$data->harga_netto > (double)$data->harga_netto_part) {
                                    $notes_harga = '*) Harga parts lebih rendah dari harga netto terendah';
                                }
                            }
                        }

                        if(strtoupper(trim($data->kode_file)) == 'A') {
                            if((double)$data->discount > 0 && (double)$data->disc_detail > 0) {
                                $notes_diskon = '*) Part number di diskon 2x';
                            }
                        }

                        if((double)$data->jumlah_bo > 0) {
                            $notes_bo = '*) Sudah ada di BO sejumlah '.number_format($data->jumlah_bo).' pcs';
                        }
                    }


                    $data_detail_cart[] = [
                        'ms_dealer_id'      => (int)$data->ms_dealer_id,
                        'id'                => (int)$data->id_part,
                        'id_part'           => (int)$data->id_part,
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'part_description'  => strtoupper(trim($data->part_description)),
                        'part_pictures'     => config('constants.app.app_images_url').'/'.strtoupper(trim($data->part_number)).'.jpg',
                        'item_group'        => strtoupper(trim($data->item_group)),
                        'sub_price'         => (double)$data->sub_price,
                        'qty'               => (double)$data->qty,
                        'discount_detail'   => (double)$data->disc_detail,
                        'amount_total'      => (double)$data->amount_total,
                        'total_part'        => (double)$data->stock,
                        'het'               => (double)$data->het,
                        'netto_part'        => (double)$data->harga_netto_part,
                        'harga_netto'       => (double)$data->harga_netto,
                        'harga_terendah'    => (double)$data->harga_terendah,
                        'discount_produk'   => (double)$data->discount_produk,
                        'available_part'    => $available_part,
                        'notes_marketing'   => $notes_marketing,
                        'notes_disc'        => $notes_diskon,
                        'notes_harga'       => $notes_harga,
                        'notes_bo'          => $notes_bo,
                    ];
                }
            }

            $data = array_merge($data_cart_part, [
                'total_next_page'   => (double)$total_next_page,
                'detail'            => $data_detail_cart
            ]);

            $data_list_cart = [
                'data' => $data
            ];

            return ApiResponse::responseSuccess('success', $data_list_cart);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateTpc(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'tpc'           => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih divisi, dealer Id, dan kode tpc terlebih dahulu');
            }

            if($request->get('tpc') != '14' ) {
                if($request->get('tpc') != '20' ) {
                    return ApiResponse::responseWarning('Kode TPC hanya bisa diisi 14 atau 20');
                }
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))
                    ->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));


            $sql = DB::connection($request->get('divisi'))
                    ->table('carttmp')->lock('with (nolock)')
                    ->selectRaw("isnull(carttmp.kd_tpc, '') as kode_tpc")
                    ->where('kd_dealer', strtoupper(trim($kode_dealer)))
                    ->where('user_id', strtoupper(trim($user_id)))
                    ->where('companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_tpc) || trim($sql->kode_tpc) == '') {
                return ApiResponse::responseWarning('Data cart masih kosong, isi data cart terlebih dahulu');
            }

            if(trim($sql->kode_tpc) != trim($request->get('tpc'))) {
                DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $user_id, $companyid) {
                    DB::connection($request->get('divisi'))
                        ->update("exec SP_CartTmp_UpdateTpc1 ?,?,?,?", [
                            strtoupper(trim($kode_dealer)), trim($request->get('tpc')),
                            strtoupper(trim($user_id)), strtoupper(trim($companyid))
                    ]);
                });
            }

            $data = [
                'kode_tpc'  => $request->get('tpc')
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateQuantity(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'id_part_cart'  => 'required',
                'quantity'      => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih atau isi divisi, dealer Id, part Id, dan jumlah quantity terlebih dahulu');
            }

            if((double)str_replace(',','.',$request->get('quantity')) <= 0) {
                return ApiResponse::responseWarning('Jumlah order harus lebih besar dari nol (0)');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))
                    ->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = DB::connection($request->get('divisi'))
                    ->table('mspart')->lock('with (nolock)')
                    ->selectRaw("isnull(mspart.kd_part, '') as part_number")
                    ->where('mspart.id', $request->get('id_part_cart'))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Id part yang anda entry tidak terdaftar');
            }
            $part_number = strtoupper(trim($sql->part_number));


            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $part_number, $user_id, $companyid) {
                DB::connection($request->get('divisi'))->insert("exec SP_CartDtlTmp_UpdateQty1 ?,?,?,?,?", [
                    strtoupper(trim($kode_dealer)), strtoupper(trim($part_number)),
                    (double)str_replace(',','.',$request->get('quantity')),
                    strtoupper(trim($user_id)), strtoupper(trim(trim($companyid))),
                ]);
            });

            return ApiResponse::responseSuccess('success', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateHarga(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'id_part_cart'  => 'required',
                'harga'         => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih divisi, dealer Id, part Id, dan nominal harga terlebih dahulu');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = DB::connection($request->get('divisi'))->table('carttmp')->lock('with (nolock)')
                    ->selectRaw("isnull(carttmp.kd_key, '') as kode_key,
                                isnull(carttmp.kd_tpc, '') as kode_tpc")
                    ->where('carttmp.kd_key', strtoupper(trim($user_id)).'/'.strtoupper(trim($kode_dealer)))
                    ->where('carttmp.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_key) || trim($sql->kode_key) == '') {
                return ApiResponse::responseWarning('Data cart anda masih kosong, isi detail cart terlebih dahulu');
            }

            if(trim($sql->kode_tpc) == '14') {
                return ApiResponse::responseWarning('Kode TPC cart anda adalah TPC 14. Kode TPC 14 tidak boleh mengganti diskon');
            }

            $sql = "select  top 1 isnull(mspart.kd_part, '') as part_number,
                            isnull(part.hrg_netto, 0) as harga_netto,
                            isnull(part.cek_hpp, 0) as status_cek_hpp,
                            cast(isnull(part.hrg_pokok, 0) +
                                round(((isnull(part.hrg_pokok, 0) * isnull(company.ppn, 0)) / 100), 0) as decimal(13, 0)) as harga_pokok
                    from
                    (
                        select  top 1 mspart.kd_part
                        from    mspart with (nolock)
                        where   mspart.id=?
                    )   mspart
                            inner join company with (nolock) on company.companyid=?
                            inner join part with (nolock) on mspart.kd_part=part.kd_part and
                                        part.companyid=?";

            $result = DB::connection($request->get('divisi'))->select($sql, [ $request->get('id_part_cart'), strtoupper(trim($companyid)),
                            strtoupper(trim($companyid)) ]);
            $jumlah_data = 0;
            $part_number = '';
            $harga_netto = 0;
            $harga_pokok = 0;
            $status_cek_hpp = 0;

            foreach($result as $data) {
                $jumlah_data = (double)$jumlah_data + 1;

                $part_number = strtoupper(trim($data->part_number));
                $harga_netto = (double)$data->harga_netto;
                $status_cek_hpp = (int)$data->status_cek_hpp;
                $harga_pokok = (double)$data->harga_pokok;
            }

            if((double)$jumlah_data <= 0) {
                return ApiResponse::responseWarning('Part number yang anda entry atau pilih tidak terdaftar, coba lagi');
            }

            if((int)$status_cek_hpp == 1) {
                if((double)$harga_pokok > (double)str_replace(',','.',$request->get('harga'))) {
                    return ApiResponse::responseWarning('Penjualan Rugi, Harga yang anda entry tidak boleh lebih rendah dari yang telah ditentukan');
                }
            }

            if ((double)$harga_netto > (double)str_replace(',','.',$request->get('harga'))) {
                if((double)$harga_netto > 0) {
                    return ApiResponse::responseWarning('Penjualan Rugi, Harga yang anda entry lebih rendah dari harga netto terendah');
                }
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $part_number, $user_id, $companyid) {
                DB::connection($request->get('divisi'))->insert("exec SP_CartDtlTmp_UpdateHarga1 ?,?,?,?,?", [
                    strtoupper(trim($kode_dealer)), strtoupper(trim($part_number)),
                    (double)str_replace(',','.',$request->get('harga')),
                    strtoupper(trim($user_id)), strtoupper(trim($companyid)),
                ]);
            });

            return ApiResponse::responseSuccess('success', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateDiscDetail(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'id_part_cart'  => 'required',
                'discount'      => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih divisi, dealer Id, part Id, dan prosentase discount terlebih dahulu');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = DB::connection($request->get('divisi'))->table('carttmp')->lock('with (nolock)')
                    ->selectRaw("isnull(carttmp.kd_key, '') as kode_key,
                                isnull(carttmp.kd_tpc, '') as kode_tpc")
                    ->where('carttmp.kd_key', strtoupper(trim($user_id)).'/'.strtoupper(trim($kode_dealer)))
                    ->where('carttmp.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_key) || trim($sql->kode_key) == '') {
                return ApiResponse::responseWarning('Data cart anda masih kosong, isi detail cart terlebih dahulu');
            }

            if(trim($sql->kode_tpc) == '20') {
                return ApiResponse::responseWarning('Kode TPC cart anda adalah TPC 20. Kode TPC 20 tidak boleh mengganti harga');
            }

            $sql = DB::connection($request->get('divisi'))->table('mspart')->lock('with (nolock)')
                    ->selectRaw("isnull(mspart.kd_part, '') as part_number")
                    ->where('mspart.id', $request->get('id_part_cart'))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Part number yang anda entry atau pilih tidak terdaftar');
            }
            $part_number = strtoupper(trim($sql->part_number));

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $part_number, $user_id, $companyid) {
                DB::connection($request->get('divisi'))->insert("exec SP_CartDtlTmp_UpdateDiscDetail1 ?,?,?,?,?", [
                    strtoupper(trim($kode_dealer)), strtoupper(trim($part_number)),
                    (float)str_replace(',','.',$request->get('discount')),
                    strtoupper(trim($user_id)), strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('success', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateDiscHeader(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'discount'      => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih divisi, dealer Id, dan prosentase discount terlebih dahulu');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = DB::connection($request->get('divisi'))->table('carttmp')->lock('with (nolock)')
                    ->selectRaw("isnull(carttmp.kd_key, '') as kode_key,
                                isnull(carttmp.kd_tpc, '') as kode_tpc")
                    ->where('carttmp.kd_key', strtoupper(trim($user_id)).'/'.strtoupper(trim($kode_dealer)))
                    ->where('carttmp.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_key) || trim($sql->kode_key) == '') {
                return ApiResponse::responseWarning('Data cart anda masih kosong, isi detail cart terlebih dahulu');
            }

            if(trim($sql->kode_tpc) == '20') {
                return ApiResponse::responseWarning('Kode TPC cart anda adalah TPC 20. Kode TPC 20 tidak boleh mengganti harga');
            }

            if((float)str_replace(',','.',$request->get('discount')) > 5.00) {
                return ApiResponse::responseWarning('Discount bawah maksimal hanya bisa dientry 5.00, selebihnya hubungi Manager Marketing');
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $user_id, $companyid) {
                DB::connection($request->get('divisi'))->update("exec SP_CartTmp_UpdateDiscount ?,?,?,?", [
                    strtoupper(trim($kode_dealer)), (float)str_replace(',','.',$request->get('discount')),
                    strtoupper(trim($user_id)), strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('success', null);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function removeCart(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id' => 'required',
                'id_part_cart' => 'required',
                'divisi'       => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih divisi, dealer Id, dan Part Id terlebih dahulu');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))->table('mspart')->lock('with (nolock)')
                    ->selectRaw("isnull(mspart.kd_part, '') as part_number")
                    ->where('mspart.id', $request->get('id_part_cart'))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Part number yang anda entry atau pilih tidak terdaftar');
            }
            $part_number = strtoupper(trim($sql->part_number));

            $sql = DB::connection($request->get('divisi'))->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $part_number, $kode_dealer, $user_id, $companyid) {
                DB::connection($request->get('divisi'))->delete("exec SP_CartDtlTmp_Del1 ?,?,?,?", [
                    strtoupper(trim($kode_dealer)), strtoupper(trim($part_number)),
                    strtoupper(trim($user_id)), strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data part number berhasil dihapus', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function submitOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'bo'            => 'required',
                'umur_faktur'   => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi, status BO, dan umur faktur tidak boleh kosong');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $role_id = strtoupper(trim($request->userlogin['role_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $sql = DB::connection($request->get('divisi'))->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $kode_key = strtoupper(trim($user_id)).'/'.strtoupper(trim($kode_dealer));
            $keterangan = (empty($request->get('keterangan') || trim($request->get('keterangan')) == '') ? '' : $request->get('keterangan'));
            $statusBO = (empty($request->get('bo') || trim($request->get('bo')) == '') ? 'B' : $request->get('bo'));

            $sql = "select  isnull(carttmp.companyid, '') as companyid, isnull(company.kd_file, '') as kode_file,
                            isnull(carttmp.kd_key, '') as kode_key, isnull(carttmp.kd_tpc, '') as kode_tpc,
                            isnull(carttmp.disc2, 0) as disc2, isnull(cart_dtltmp.kd_part, '') as part_number,
                            isnull(cart_dtltmp.harga, 0) as harga, isnull(cart_dtltmp.jml_order, 0) as jml_order,
                            isnull(cart_dtltmp.disc1, 0) as disc1, isnull(part.het, 0) as het,
                            isnull(part.hrg_netto, 0) as harga_netto, isnull(part.cek_hpp, 0) as status_cek_hpp,
                            cast(isnull(part.hrg_pokok, 0) +
                                round(((isnull(part.hrg_pokok, 0) * isnull(company.ppn, 0)) / 100), 0) as decimal(13, 0)) as harga_pokok,
                            isnull(cart_dtltmp.harga, 0) -
                                round(((isnull(cart_dtltmp.harga, 0) * isnull(cart_dtltmp.disc1, 0)) / 100), 0) -
                                    round((((isnull(cart_dtltmp.harga, 0) -
                                        round(((isnull(cart_dtltmp.harga, 0) * isnull(cart_dtltmp.disc1, 0)) / 100), 0)) *
                                            isnull(carttmp.disc2, 0)) / 100), 0) as harga_satuan
                    from
                    (
                        select  carttmp.companyid, carttmp.kd_key, carttmp.kd_tpc, carttmp.disc2
                        from    carttmp with (nolock)
                        where   carttmp.kd_key=? and carttmp.companyid=?
                    )   carttmp
                            inner join company with (nolock) on carttmp.companyid=company.companyid
                            left join cart_dtltmp with (nolock) on carttmp.kd_key=cart_dtltmp.kd_key and
                                        carttmp.companyid=cart_dtltmp.companyid
                            left join part with (nolock) on cart_dtltmp.kd_part=part.kd_part and
                                        carttmp.companyid=part.companyid
                            left join sub with (nolock) on part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk
                            left join discp with (nolock) on produk.kd_produk=discp.kd_produk and
                                        iif(isnull(company.inisial, 0) = 1, 'RK', 'PC')=discp.cabang";

            $result = DB::connection($request->get('divisi'))->select($sql, [ strtoupper(trim($kode_key)), strtoupper(trim($companyid)) ]);

            foreach($result as $data) {
                if(trim($data->kode_tpc) == '20') {
                    if((float)$data->disc1 > 0 || (float)$data->disc2 > 0) {
                        return ApiResponse::responseWarning('Ada diskon pada Part number '.strtoupper(trim($data->part_number)).'.
                                    (TPC 20 tidak boleh menggunakan data diskon)');
                    }

                    if((double)$data->harga <= 0) {
                        return ApiResponse::responseWarning('Kolom harga pada Part number '.strtoupper(trim($data->part_number)).' masih belum di entry');
                    }

                    if((int)$data->status_cek_hpp == 1) {
                        if((double)$data->harga_pokok > (double)$data->harga) {
                            return ApiResponse::responseWarning('Part number '.strtoupper(trim($data->part_number)).' penjualan rugi. '.
                                    '(Harga tidak boleh lebih rendah dari harga yang telah ditentukan)');
                        }
                    }

                    if ((double)$data->harga_netto > (double)$data->harga) {
                        if((double)$data->harga_netto > 0) {
                            return ApiResponse::responseWarning('Part number '.strtoupper(trim($data->part_number)).' penjualan rugi. '.
                                    '(Harga tidak boleh lebih rendah dari harga netto terendah)');
                        }
                    }
                }

                if(strtoupper(trim($role_id)) != 'D_H3') {
                    if(trim($data->kode_tpc) == '14') {
                        if(strtoupper(trim($data->kode_file)) == 'A') {
                            if((float)$data->disc1 > 0 && (float)$data->disc2 > 0) {
                                return ApiResponse::responseWarning('Part number '.strtoupper(trim($data->part_number)).' di diskon 2x');
                            }
                        }

                        if((float)$data->disc1 <= 0 && (float)$data->disc2 <= 0) {
                            return ApiResponse::responseWarning('Part number '.strtoupper(trim($data->part_number)).' belum ada diskon');
                        }
                    }
                }


                if((double)$data->harga_pokok > (double)$data->harga_satuan) {
                    return ApiResponse::responseWarning('Part number '.strtoupper(trim($data->part_number)).' penjualan rugi.
                                (Total harga tidak boleh lebih rendah dari harga yang telah ditentukan)');
                }
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $keterangan, $user_id,
                                                                                $role_id, $statusBO, $companyid) {
                DB::connection($request->get('divisi'))->insert("exec SP_Cart_Submit1 ?,?,?,?,?,?,?", [
                    strtoupper(trim($kode_dealer)), strtoupper(trim($statusBO)), $request->get('umur_faktur'),
                    strtoupper(trim($keterangan)), strtoupper(trim($user_id)), strtoupper(trim($role_id)),
                    strtoupper(trim($companyid))
                ]);
            });

            $sql = DB::connection($request->get('divisi'))->table('cart')->lock('with (nolock)')
                    ->selectRaw("isnull(cart.no_order, '') as nomor_order")
                    ->where('cart.user_id', strtoupper(trim($user_id)))
                    ->where('cart.kd_dealer', strtoupper(trim($kode_dealer)))
                    ->where('cart.companyid', strtoupper(trim($companyid)))
                    ->orderBy('cart.usertime', 'desc')
                    ->first();

            if(empty($sql->nomor_order)) {
                return ApiResponse::responseWarning('Nomor order cart tidak ditemukan, coba lagi');
            }
            $nomor_order = strtoupper(trim($sql->nomor_order));

            $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                    ->selectRaw("isnull(pof.no_pof, '') as nomor_pof, count(pof_dtl.no_pof) as jumlah_item,
                                isnull(pof.kd_sales, '') as kode_sales, isnull(salesman.nm_sales, '') as nama_sales,
                                isnull(pof.kd_dealer, '') as kode_dealer, isnull(dealer.nm_dealer, '') as nama_dealer,
                                isnull(convert(varchar(10), pof.tgl_akhir_pof, 105), '') as tanggal_akhir_pof,
                                isnull(pof.bo, '') as status_bo, isnull(pof.total, 0) as total,
                                isnull(salesman.spv, '') as kode_supervisor")
                    ->leftJoin(DB::raw('pof_dtl with (nolock)'), function($join) {
                        $join->on('pof_dtl.no_pof', '=', 'pof.no_pof')
                            ->on('pof_dtl.companyid', '=', 'pof.companyid');
                    })
                    ->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                        $join->on('salesman.kd_sales', '=', 'pof.kd_sales')
                            ->on('salesman.companyid', '=', 'pof.companyid');
                    })
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'pof.kd_dealer')
                            ->on('dealer.companyid', '=', 'pof.companyid');
                    })
                    ->where('pof.no_order', strtoupper(trim($nomor_order)))
                    ->where('pof.companyid', strtoupper(trim($companyid)))
                    ->groupByRaw('pof.no_pof, pof.kd_sales, salesman.nm_sales, pof.kd_dealer,
                                dealer.nm_dealer, pof.tgl_akhir_pof, pof.bo, pof.total,
                                salesman.spv')
                    ->first();

            if(empty($sql->nomor_pof)) {
                return ApiResponse::responseWarning('Nomor purchase order form tidak ditemukan, coba lagi');
            }

            $nomor_pof = strtoupper(trim($sql->nomor_pof));
            $kode_sales = strtoupper(trim($sql->kode_sales));
            $nama_sales = strtoupper(trim($sql->nama_sales));
            $kode_dealer = strtoupper(trim($sql->kode_dealer));
            $nama_dealer = strtoupper(trim($sql->nama_dealer));
            $tanggal_akhir_pof = strtoupper(trim($sql->tanggal_akhir_pof));
            $status_bo = (strtoupper(trim($sql->status_bo)) == 'B') ? 'BACK ORDER' : 'TIDAK BO';
            $jumlah_item = (double)$sql->jumlah_item;
            $total = (double)$sql->total;
            $kode_supervisor = strtoupper(trim($sql->kode_supervisor));

            // =======================================================================================================
            // Notification - Supervisor
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
                    ->where('users.user_id', strtoupper(trim($kode_supervisor)))
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
                        'title'         => 'New Order',
                        'message'       => 'Ada purchase order form baru nomor '.strtoupper(trim($nomor_pof)).' dari user '.strtoupper(trim($request->userlogin['user_id'])),
                        'code'          => strtoupper(trim($nomor_pof)),
                        'user_process'  => strtoupper(trim($request->userlogin['user_id'])),
                        'divisi'        => $request->get('divisi')
                    ];
                    ApiRequest::requestPost($url, $header, $body);
                }
            }
            // =======================================================================================================
            //
            // =======================================================================================================
            $data = [
                'code_order'    => strtoupper(trim($nomor_pof)),
                'salesman'      => strtoupper(trim($kode_sales)).' - '.strtoupper(trim($nama_sales)),
                'dealer'        => strtoupper(trim($kode_dealer)).' - '.strtoupper(trim($nama_dealer)),
                'umur_faktur'   => trim($tanggal_akhir_pof),
                'status_bo'     => strtoupper(trim($status_bo)),
                'jumlah_item'   => (double)$jumlah_item,
                'grand_total'   => (double)$total
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function importExcel(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'imports'       => 'required',
                'ms_dealer_id'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Data file import, dealer Id, dan divisi tidak boleh kosong');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $role_id = strtoupper(trim($request->userlogin['role_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $kode_key = '';
            $kode_sales = '';
            $kode_dealer = '';

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer,
                                isnull(dealer.kd_sales, '') as kode_sales")
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'msdealer.kd_dealer')
                            ->on('dealer.companyid', '=', 'msdealer.companyid');
                    })
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer)) {
                return ApiResponse::responseWarning('Dealer yang anda pilih tidak terdaftar');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            if(strtoupper(trim($role_id)) == 'MD_H3_SM') {
                $kode_sales = strtoupper(trim($user_id));
            } else {
                $kode_sales = strtoupper(trim($sql->kode_sales));
            }

            $kode_key = strtoupper(trim($user_id)).'/'.strtoupper(trim($kode_dealer));

            $data_excel = [];
            $data_imports = json_decode($request->get('imports'), false);

            foreach($data_imports as $data) {
                $data_excel[] = [
                    'kd_key'    => strtoupper(trim($kode_key)),
                    'no_order'  => strtoupper(trim($kode_key)),
                    'pn'        => strtoupper(trim($data->part_number)),
                    'jml_order' => (int)$data->order,
                    'companyid' => strtoupper(trim($companyid)),
                    'usertime'  => strtoupper(trim($user_id))
                ];
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_key, $kode_sales,
                                        $kode_dealer, $companyid, $user_id, $data_excel) {
                DB::connection($request->get('divisi'))->delete("delete from carttmp_exceltmp where kd_key=? and companyid=?", [
                    strtoupper(trim($kode_key)), strtoupper(trim($companyid))
                ]);

                DB::table('carttmp_exceltmp')->insert($data_excel);

                DB::connection($request->get('divisi'))->insert("exec SP_CartTmp_ImportExcel ?,?,?,?,?,?", [
                    strtoupper(trim($kode_key)), strtoupper(trim($kode_key)), strtoupper(trim($kode_sales)),
                    strtoupper(trim($kode_dealer)), strtoupper(trim($companyid)),
                    strtoupper(trim($user_id))
                ]);
            });

            $data_result_excel = [];

            $sql = DB::table('carttmp_excel')->lock('with (nolock)')
                    ->selectRaw("isnull(carttmp_excel.pn, '') as pn,
                                isnull(carttmp_excel.keterangan, '') as keterangan")
                    ->where('carttmp_excel.kd_key', strtoupper(trim($kode_key)))
                    ->where('carttmp_excel.no_order', strtoupper(trim($kode_key)))
                    ->where('carttmp_excel.companyid', strtoupper(trim($companyid)))
                    ->get();

            $data_result_excel = $sql;

            return ApiResponse::responseSuccess('success', $data_result_excel);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function importExcelResult(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Data dealer Id, dan divisi tidak boleh kosong');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $kode_key = '';
            $kode_dealer = '';

            $sql = DB::table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer,
                                isnull(dealer.kd_sales, '') as kode_sales")
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'msdealer.kd_dealer')
                            ->on('dealer.companyid', '=', 'msdealer.companyid');
                    })
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($companyid)))
                    ->first();

            if(empty($sql->kode_dealer)) {
                return ApiResponse::responseWarning('Dealer yang anda pilih tidak terdaftar');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $kode_key = strtoupper(trim($user_id)).'/'.strtoupper(trim($kode_dealer));

            $sql = DB::table('carttmp_excel')->lock('with (nolock)')
                    ->selectRaw("isnull(carttmp_excel.pn, '') as pn,
                                isnull(carttmp_excel.keterangan, '') as keterangan")
                    ->where('carttmp_excel.kd_key', strtoupper(trim($kode_key)))
                    ->where('carttmp_excel.no_order', strtoupper(trim($kode_key)))
                    ->where('carttmp_excel.companyid', strtoupper(trim($companyid)))
                    ->get();

            $data_result_excel = $sql;

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_key, $companyid) {
                DB::connection($request->get('divisi'))->delete("delete from carttmp_excel where kd_key=? and companyid=?", [
                    strtoupper(trim($kode_key)), strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('success', $data_result_excel);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

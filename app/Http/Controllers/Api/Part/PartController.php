<?php

namespace App\Http\Controllers\Api\Part;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class PartController extends Controller {

    public function listMotorType(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('typemotor')->lock('with (nolock)')
                    ->selectRaw("isnull(typemotor.id, 0) as id,
                                isnull(typemotor.typemkt, '') as code,
                                isnull(typemotor.ket, '') as name,
                                iif(isnull(typemotor_fav.typemkt, '')='', 0, 1) as favorite")
                    ->leftJoin(DB::raw('typemotor_fav with (nolock)'), function($join) use($request) {
                        $join->on('typemotor_fav.typemkt', '=', 'typemotor.typemkt')
                            ->on('typemotor_fav.user_id', '=', DB::raw("'".strtoupper(trim($request->userlogin['user_id']))."'"))
                            ->on('typemotor_fav.companyid', '=', DB::raw("'".strtoupper(trim($request->userlogin['companyid']))."'"));
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

    public function listItemClassProduk(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('classprod')->lock('with (nolock)')
                    ->selectRaw("isnull(classprod.id, 0) as id,
                                isnull(classprod.kd_class, '') as kode_class,
                                isnull(classprod.nama, '') as nama_class,
                                0 as favorite");

            if(!empty('search') && trim($request->get('search') != '')) {
                $sql->where('classprod.kd_class', 'like', strtoupper(trim($request->get('search'))).'%')
                    ->orWhere('classprod.nama', 'like', strtoupper(trim($request->get('search'))).'%');
            }

            $sql = $sql->orderBy('classprod.kd_class', 'asc')
                        ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];

            $data_favorite = [];
            $data_list_produk = [];
            $jumlah_data = 0;

            foreach($data_result as $result) {
                if((int)$result->favorite == 1) {
                    $data_favorite[] = [
                        'id'    => (int)$result->id,
                        'code'  => strtoupper(trim($result->kode_class)),
                        'name'  => strtoupper(trim($result->nama_class))
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
                    'code'  => strtoupper(trim($result->kode_class)),
                    'name'  => strtoupper(trim($result->nama_class))
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

    public function listItemGroup(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('produk')->lock('with (nolock)')
                    ->selectRaw("isnull(produk.id, 0) as id,
                                isnull(produk.kd_produk, '') as kode_produk,
                                isnull(produk.nama, '') as nama_produk,
                                iif(isnull(produk_fav.kd_produk, '')='', 0, 1) as favorite")
                    ->leftJoin(DB::raw('produk_fav with (nolock)'), function($join) use($request) {
                        $join->on('produk_fav.kd_produk', '=', 'produk.kd_produk')
                            ->on('produk_fav.user_id', '=', DB::raw("'".strtoupper(trim($request->userlogin['user_id']))."'"))
                            ->on('produk_fav.companyid', '=', DB::raw("'".strtoupper(trim($request->userlogin['companyid']))."'"));
                    });

            if(!empty('classproduk') && trim($request->get('classproduk') != '')) {
                $sql->where('produk.kd_class', strtoupper(trim($request->get('classproduk'))));
            }

            if(!empty('search') && trim($request->get('search') != '')) {
                $sql->where('produk.kd_produk', 'like', strtoupper(trim($request->get('search'))).'%')
                    ->orWhere('produk.nama', 'like', strtoupper(trim($request->get('search'))).'%');
            }

            $sql = $sql->orderBy('produk.nourut', 'asc')
                        ->paginate(15);

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

    public function listItemSubProduk(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('sub')->lock('with (nolock)')
                    ->selectRaw("isnull(sub.id, 0) as id,
                                isnull(sub.kd_sub, '') as kode_sub,
                                isnull(sub.nama, '') as nama_sub,
                                0 as favorite");

            if(!empty('produk') && trim($request->get('produk') != '')) {
                $sql->where('sub.kd_produk', strtoupper(trim($request->get('produk'))));
            }

            if(!empty('search') && trim($request->get('search') != '')) {
                $sql->where('sub.kd_class', 'like', strtoupper(trim($request->get('search'))).'%')
                    ->orWhere('sub.nama', 'like', strtoupper(trim($request->get('search'))).'%');
            }

            $sql = $sql->orderBy('sub.kd_sub', 'asc')
                        ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];

            $data_favorite = [];
            $data_list_produk = [];
            $jumlah_data = 0;

            foreach($data_result as $result) {
                if((int)$result->favorite == 1) {
                    $data_favorite[] = [
                        'id'    => (int)$result->id,
                        'code'  => strtoupper(trim($result->kode_sub)),
                        'name'  => strtoupper(trim($result->nama_sub))
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
                    'code'  => strtoupper(trim($result->kode_sub)),
                    'name'  => strtoupper(trim($result->nama_sub))
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
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_dealer, '') as kode_dealer")
                    ->where('id', $request->get('ms_dealer_id'))
                    ->where('companyid', $request->userlogin['companyid'])
                    ->first();

            if(empty($sql->kode_dealer)) {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));
            $kode_key = strtoupper(trim($request->userlogin['user_id'])).'/'.strtoupper(trim($kode_dealer));

            $total_item_cart = 0;

            $sql = DB::connection($request->get('divisi'))
                    ->table('cart_dtltmp')->lock('with (nolock)')
                    ->selectRaw("isnull(cart_dtltmp.companyid, '') as companyid,
                                isnull(cart_dtltmp.kd_key, '') as kode_key,
                                count(cart_dtltmp.kd_key) as total_item")
                    ->where('cart_dtltmp.kd_key', strtoupper(trim($kode_key)))
                    ->where('cart_dtltmp.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->groupByRaw("cart_dtltmp.companyid, cart_dtltmp.kd_key")
                    ->first();

            if(!empty($sql->companyid)) {
                $total_item_cart = (double)$sql->total_item;
            } else {
                $total_item_cart = 0;
            }

            /* ========================================================================================== */
            /* Part Number Search */
            /* ========================================================================================== */
            $list_search_part = '';

            $sql = DB::connection($request->get('divisi'))
                    ->table('part')->lock('with (nolock)')
                    ->selectRaw("isnull(part.kd_part, '') as part_number")
                    ->whereRaw("isnull(part.del_send, 0) = 0")
                    ->whereRaw("part.kd_sub <> 'DSTO'")
                    ->where('part.companyid', strtoupper(trim($request->userlogin['companyid'])));

            if(!empty($request->get('item_group')) || $request->get('item_group') != '') {
                if($request->get('item_group') != 0) {
                    $sql->leftJoin(DB::raw('sub with (nolock)'), function($join) {
                            $join->on('sub.kd_sub', '=', 'part.kd_sub');
                        });
                    $sql->leftJoin(DB::raw('produk with (nolock)'), function($join) {
                            $join->on('produk.kd_produk', '=', 'sub.kd_produk');
                        });
                    $sql->where('produk.id', $request->get('item_group'));
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

            if(!empty($request->get('similarity')) && trim($request->get('similarity')) != '') {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('part.ket', 'like', '%'.trim($request->get('similarity')).'%')
                            ->orWhere('part.bhs_pasar', 'like', '%'.trim($request->get('similarity')).'%');
                });
            }

            $result = $sql->paginate(15);

            $list_search_part = '';
            $data_part = new Collection();
            $data_type_motor = [];
            $result_search_part = [];

            $data_list_part = $result->items();

            foreach($data_list_part as $data) {
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
                                isnull(part.jml1dus, 0) as dus,
                                iif(isnull(part_fav.kd_part, '')='', 0, 1) as is_love,
                                isnull(typemotor.typemkt, '') as typemkt, rtrim(typemotor.typemkt) + ' ' + rtrim(typemotor.ket) as type_motor
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
                                where	part.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                                            part.companyid=part_fav.companyid and part_fav.user_id='".strtoupper(trim($request->userlogin['user_id']))."'
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

                $sql = DB::connection($request->get('divisi'))->select($sql);

                $data_info_part = new Collection();
                $data_transaksi_part = new Collection();

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
                        if(strtoupper(trim($request->userlogin['role_id'])) == "MD_H3_MGMT") {
                            $stock_part = 'Available '.number_format((double)$data->stock_total_part).' pcs';
                        } else {
                            $stock_part = 'Available';
                        }
                    }

                    $data_type_motor[] = [
                        'part_number'   => trim($data->part_number),
                        'type_motor'    => trim($data->type_motor)
                    ];

                    $data_info_part->push((object) [
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
                        'available_part'    => $stock_part
                    ]);
                }

                $sql = "select	isnull(part.companyid, '') as companyid, isnull(part.kd_part, '') as part_number,
                                isnull(camp.no_camp, '') as no_camp, isnull(camp.nm_camp, '') as nama_camp,
                                isnull(convert(varchar(10), camp.tgl_prd1, 107), '') as tanggal_awal_camp,
                                isnull(convert(varchar(10), camp.tgl_prd2, 107), '') as tanggal_akhir_camp,
                                isnull(convert(varchar(50), cast(faktur.tgl_faktur as date), 106), '') as tgl_faktur,
                                isnull(faktur.jml_jual, 0) as jumlah_faktur,
                                isnull(convert(varchar(50), cast(pof.tgl_pof as date), 106), '') as tgl_pof,
                                isnull(pof.jml_order, 0) as jumlah_pof, isnull(bo.jumlah, 0) as jumlah_bo,
		                        isnull(cart_dtltmp.kd_part, '') as part_cart
                        from
                        (
                            select	part.companyid, part.kd_part
                            from	part with (nolock)
                            where	part.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                    part.kd_part in (".$list_search_part.")
                        )	part
                        left join bo with (nolock) on part.kd_part=bo.kd_part and
                                    '".strtoupper(trim($kode_dealer))."'=bo.kd_dealer and part.companyid=bo.companyid
                        left join
                        (
                            select	faktur.companyid, faktur.no, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                            from
                            (
                                select	row_number() over(partition by faktur.kd_part order by faktur.kd_part asc, faktur.tgl_faktur desc) as no,
                                        faktur.companyid, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                                from
                                (
                                    select	faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part,
                                            sum(isnull(fakt_dtl.jml_jual, 0)) as jml_jual
                                    from
                                    (
                                        select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur
                                        from	faktur with (nolock)
                                        where	faktur.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                        )	faktur on part.kd_part=faktur.kd_part and part.companyid=faktur.companyid
                        left join
                        (
                            select	pof.companyid, pof.no, pof.kd_part, pof.tgl_pof, pof.jml_order
                            from
                            (
                                select	row_number() over(partition by pof.kd_part order by pof.kd_part asc, pof.tgl_pof desc) as no,
                                        pof.companyid, pof.kd_part, pof.tgl_pof, pof.jml_order
                                from
                                (
                                    select	pof.companyid, pof.tgl_pof, pof_dtl.kd_part,
                                            sum(isnull(pof_dtl.jml_order, 0)) as jml_order
                                    from
                                    (
                                        select	pof.companyid, pof.no_pof, pof.tgl_pof
                                        from	pof with (nolock)
                                        where	pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                        )	pof on part.kd_part=pof.kd_part and part.companyid=pof.companyid
                        left join
                        (
                            select	camp.companyid, camp.no_camp, camp.nm_camp,
                                    camp.tgl_prd1, camp.tgl_prd2, camp.kd_part
                            from
                            (
                                select	row_number() over(order by camp.tgl_prd2 asc) as nomor,
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
                                            camp.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                                )	camp
                                        inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                                    camp.companyid=camp_dtl.companyid
                                where	camp_dtl.kd_part in (".$list_search_part.")
                            )	camp
                            where	camp.nomor = 1
                        )   camp on part.kd_part=camp.kd_part and part.companyid=camp.companyid
                        left join
                        (
                            select	cart_dtltmp.companyid, cart_dtltmp.kd_part
                            from	cart_dtltmp with (nolock)
                            where	cart_dtltmp.kd_key='".strtoupper(trim($kode_key))."' and
                                    cart_dtltmp.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                    cart_dtltmp.kd_part in (".$list_search_part.")
                        )	cart_dtltmp on part.kd_part=cart_dtltmp.kd_part and part.companyid=cart_dtltmp.companyid
                        order by part.kd_part asc";

                $sql = DB::connection($request->get('divisi'))->select($sql);

                foreach($sql as $data) {
                    $data_transaksi_part->push((object) [
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'is_cart'           => (trim($data->part_cart) == '') ? false : true,
                        'is_campaign'       => (strtoupper(trim($data->no_camp)) == '') ? 0 : 1,
                        'nama_campaign'     => strtoupper(trim($data->nama_camp)),
                        'periode_campaign'  => strtoupper(trim($data->tanggal_awal_camp)).' s/d '.strtoupper(trim($data->tanggal_akhir_camp)),
                        'keterangan_bo'     => ((double)$data->jumlah_bo > 0) ? '*) Part number ini sudah ada di BO sejumlah : '.$data->jumlah_bo.' pcs' : '',
                        'keterangan_faktur' => ((double)$data->jumlah_faktur > 0) ? '*) Faktur terakhir tanggal : '.$data->tgl_faktur.', sejumlah : '.number_format($data->jumlah_faktur) : '',
                        'keterangan_pof'    => ((double)$data->jumlah_pof > 0) ? '*) POF terakhir tanggal : '.$data->tgl_pof.', sejumlah : '.number_format($data->jumlah_pof) : '',
                    ]);
                }

                $part_number = '';
                foreach($data_info_part as $collection) {
                    if ($part_number != $collection->part_number) {
                        $is_cart = false;
                        $is_campaign = 0;
                        $nama_campaign = '';
                        $periode_campaign = '';
                        $keterangan_bo = '';
                        $keterangan_faktur = '';
                        $keterangan_pof = '';

                        $data_transaksi = $data_transaksi_part
                                            ->where('part_number', strtoupper(trim($collection->part_number)))
                                            ->values()
                                            ->all();

                        foreach($data_transaksi as $data) {
                            $is_cart = $data->is_cart;
                            $is_campaign = $data->is_campaign;
                            $nama_campaign = $data->nama_campaign;
                            $periode_campaign = $data->periode_campaign;
                            $keterangan_bo = $data->keterangan_bo;
                            $keterangan_faktur = $data->keterangan_faktur;
                            $keterangan_pof = $data->keterangan_pof;
                        }

                        $data_part->push((object) [
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
                            'available_part'    => trim($collection->available_part),
                            'is_cart'           => $is_cart,
                            'is_campaign'       => $is_campaign,
                            'nama_campaign'     => $nama_campaign,
                            'periode_campaign'  => $periode_campaign,
                            'keterangan_bo'     => $keterangan_bo,
                            'keterangan_faktur' => $keterangan_faktur,
                            'keterangan_pof'    => $keterangan_pof,
                        ]);

                        $part_number = $collection->part_number;
                    }
                }

                if(!empty($request->get('sorting'))) {
                    if($request->get('sorting') == 'part_number|asc') {
                        $result_search_part = $data_part->sortBy('part_number');
                    } elseif ($request->get('sorting') == 'part_number|desc') {
                        $result_search_part = $data_part->sortByDesc('part_number');
                    }

                    if ($request->get('sorting') == 'description|asc') {
                        $result_search_part = $data_part->sortBy('part_description');
                    } elseif ($request->get('sorting') == 'description|desc') {
                        $result_search_part = $data_part->sortByDesc('part_description');
                    }

                    if ($request->get('sorting') == 'available_part|a') {
                        $result_search_part = $data_part->sortBy('part_number')->sortBy('available_part');
                    } elseif ($request->get('sorting') == 'available_part|na') {
                        $result_search_part = $data_part->sortBy('part_number')->sortByDesc('available_part');
                    }

                    if ($request->get('sorting') == 'promo|yes') {
                        $result_search_part = $data_part->sortBy('part_number')->sortByDesc('is_campaign');
                    } elseif ($request->get('sorting') == 'promo|no') {
                        $result_search_part = $data_part->sortBy('part_number')->sortBy('is_campaign');
                    }
                } else {
                    $result_search_part = $data_part->sortBy('part_number');
                }
                $result_search_part = $result_search_part->values()->all();
            }

            $result = [
                'total_item_cart'   => (double)$total_item_cart,
                'data'              => $result_search_part
            ];

            return ApiResponse::responseSuccess('success', $result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listPartFavorite(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_dealer, '') as kode_dealer")
                    ->where('id', $request->get('ms_dealer_id'))
                    ->where('companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->first();

            if(empty($sql->kode_dealer)) {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan, hubungi IT Programmer');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));
            $kode_key = strtoupper(trim($request->userlogin['user_id'])).'/'.strtoupper(trim($kode_dealer));

            $total_item_cart = 0;

            $sql = DB::connection($request->get('divisi'))
                    ->table('cart_dtltmp')->lock('with (nolock)')
                    ->selectRaw("isnull(cart_dtltmp.companyid, '') as companyid,
                                isnull(cart_dtltmp.kd_key, '') as kode_key,
                                count(cart_dtltmp.kd_key) as total_item")
                    ->where('cart_dtltmp.kd_key', strtoupper(trim($kode_key)))
                    ->where('cart_dtltmp.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->groupByRaw("cart_dtltmp.companyid, cart_dtltmp.kd_key")
                    ->first();

            if(!empty($sql->companyid)) {
                $total_item_cart = (double)$sql->total_item;
            } else {
                $total_item_cart = 0;
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('part_fav')->lock('with (nolock)')
                    ->selectRaw("isnull(part_fav.kd_part, '') as part_number")
                    ->leftJoin(DB::raw('part with (nolock)'), function($join) {
                        $join->on('part.kd_part', '=', 'part_fav.kd_part')
                            ->on('part.companyid', '=', 'part_fav.companyid');
                    })
                    ->where('part_fav.user_id', strtoupper(trim($request->userlogin['user_id'])))
                    ->where('part_fav.kd_dealer', strtoupper(trim($kode_dealer)))
                    ->where('part_fav.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->whereRaw("isnull(part.del_send, 0)=0")
                    ->orderBy('part_fav.kd_part', 'asc')
                    ->paginate(15);


            $list_search_part = '';
            $data_part = new Collection();
            $data_type_motor = [];
            $result_search_part = [];

            $data_list_part = $sql->items();

            foreach($data_list_part as $data) {
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
                                isnull(part.jml1dus, 0) as dus,
                                iif(isnull(part_fav.kd_part, '')='', 0, 1) as is_love,
                                isnull(typemotor.typemkt, '') as typemkt, rtrim(typemotor.typemkt) + ' ' + rtrim(typemotor.ket) as type_motor
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
                                where	part.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                                            part.companyid=part_fav.companyid and part_fav.user_id='".strtoupper(trim($request->userlogin['user_id']))."'
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

                $sql = DB::connection($request->get('divisi'))->select($sql);

                $data_info_part = new Collection();
                $data_transaksi_part = new Collection();

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
                        if(strtoupper(trim($request->userlogin['role_id'])) == "MD_H3_MGMT") {
                            $stock_part = 'Available '.number_format((double)$data->stock_total_part).' pcs';
                        } else {
                            $stock_part = 'Available';
                        }
                    }

                    $data_type_motor[] = [
                        'part_number'   => trim($data->part_number),
                        'type_motor'    => trim($data->type_motor)
                    ];

                    $data_info_part->push((object) [
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
                        'available_part'    => $stock_part
                    ]);
                }

                $sql = "select	isnull(part.companyid, '') as companyid, isnull(part.kd_part, '') as part_number,
                                isnull(camp.no_camp, '') as no_camp, isnull(camp.nm_camp, '') as nama_camp,
                                isnull(convert(varchar(10), camp.tgl_prd1, 107), '') as tanggal_awal_camp,
                                isnull(convert(varchar(10), camp.tgl_prd2, 107), '') as tanggal_akhir_camp,
                                isnull(convert(varchar(50), cast(faktur.tgl_faktur as date), 106), '') as tgl_faktur,
                                isnull(faktur.jml_jual, 0) as jumlah_faktur,
                                isnull(convert(varchar(50), cast(pof.tgl_pof as date), 106), '') as tgl_pof,
                                isnull(pof.jml_order, 0) as jumlah_pof, isnull(bo.jumlah, 0) as jumlah_bo,
		                        isnull(cart_dtltmp.kd_part, '') as part_cart
                        from
                        (
                            select	part.companyid, part.kd_part
                            from	part with (nolock)
                            where	part.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                    part.kd_part in (".$list_search_part.")
                        )	part
                        left join bo with (nolock) on part.kd_part=bo.kd_part and
                                    '".strtoupper(trim($kode_dealer))."'=bo.kd_dealer and part.companyid=bo.companyid
                        left join
                        (
                            select	faktur.companyid, faktur.no, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                            from
                            (
                                select	row_number() over(partition by faktur.kd_part order by faktur.kd_part asc, faktur.tgl_faktur desc) as no,
                                        faktur.companyid, faktur.kd_part, faktur.tgl_faktur, faktur.jml_jual
                                from
                                (
                                    select	faktur.companyid, faktur.tgl_faktur, fakt_dtl.kd_part,
                                            sum(isnull(fakt_dtl.jml_jual, 0)) as jml_jual
                                    from
                                    (
                                        select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur
                                        from	faktur with (nolock)
                                        where	faktur.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                        )	faktur on part.kd_part=faktur.kd_part and part.companyid=faktur.companyid
                        left join
                        (
                            select	pof.companyid, pof.no, pof.kd_part, pof.tgl_pof, pof.jml_order
                            from
                            (
                                select	row_number() over(partition by pof.kd_part order by pof.kd_part asc, pof.tgl_pof desc) as no,
                                        pof.companyid, pof.kd_part, pof.tgl_pof, pof.jml_order
                                from
                                (
                                    select	pof.companyid, pof.tgl_pof, pof_dtl.kd_part,
                                            sum(isnull(pof_dtl.jml_order, 0)) as jml_order
                                    from
                                    (
                                        select	pof.companyid, pof.no_pof, pof.tgl_pof
                                        from	pof with (nolock)
                                        where	pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                        )	pof on part.kd_part=pof.kd_part and part.companyid=pof.companyid
                        left join
                        (
                            select	camp.companyid, camp.no_camp, camp.nm_camp,
                                    camp.tgl_prd1, camp.tgl_prd2, camp.kd_part
                            from
                            (
                                select	row_number() over(order by camp.tgl_prd2 asc) as nomor,
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
                                            camp.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
                                )	camp
                                        inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                                    camp.companyid=camp_dtl.companyid
                                where	camp_dtl.kd_part in (".$list_search_part.")
                            )	camp
                            where	camp.nomor = 1
                        )   camp on part.kd_part=camp.kd_part and part.companyid=camp.companyid
                        left join
                        (
                            select	cart_dtltmp.companyid, cart_dtltmp.kd_part
                            from	cart_dtltmp with (nolock)
                            where	cart_dtltmp.kd_key='".strtoupper(trim($kode_key))."' and
                                    cart_dtltmp.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                    cart_dtltmp.kd_part in (".$list_search_part.")
                        )	cart_dtltmp on part.kd_part=cart_dtltmp.kd_part and part.companyid=cart_dtltmp.companyid
                        order by part.kd_part asc";

                $sql = DB::connection($request->get('divisi'))->select($sql);

                foreach($sql as $data) {
                    $data_transaksi_part->push((object) [
                        'part_number'       => strtoupper(trim($data->part_number)),
                        'is_cart'           => (trim($data->part_cart) == '') ? false : true,
                        'is_campaign'       => (strtoupper(trim($data->no_camp)) == '') ? 0 : 1,
                        'nama_campaign'     => strtoupper(trim($data->nama_camp)),
                        'periode_campaign'  => strtoupper(trim($data->tanggal_awal_camp)).' s/d '.strtoupper(trim($data->tanggal_akhir_camp)),
                        'keterangan_bo'     => ((double)$data->jumlah_bo > 0) ? '*) Part number ini sudah ada di BO sejumlah : '.$data->jumlah_bo.' pcs' : '',
                        'keterangan_faktur' => ((double)$data->jumlah_faktur > 0) ? '*) Faktur terakhir tanggal : '.$data->tgl_faktur.', sejumlah : '.number_format($data->jumlah_faktur) : '',
                        'keterangan_pof'    => ((double)$data->jumlah_pof > 0) ? '*) POF terakhir tanggal : '.$data->tgl_pof.', sejumlah : '.number_format($data->jumlah_pof) : '',
                    ]);
                }

                $part_number = '';
                foreach($data_info_part as $collection) {
                    if ($part_number != $collection->part_number) {
                        $is_cart = false;
                        $is_campaign = 0;
                        $nama_campaign = '';
                        $periode_campaign = '';
                        $keterangan_bo = '';
                        $keterangan_faktur = '';
                        $keterangan_pof = '';

                        $data_transaksi = $data_transaksi_part
                                            ->where('part_number', strtoupper(trim($collection->part_number)))
                                            ->values()
                                            ->all();

                        foreach($data_transaksi as $data) {
                            $is_cart = $data->is_cart;
                            $is_campaign = $data->is_campaign;
                            $nama_campaign = $data->nama_campaign;
                            $periode_campaign = $data->periode_campaign;
                            $keterangan_bo = $data->keterangan_bo;
                            $keterangan_faktur = $data->keterangan_faktur;
                            $keterangan_pof = $data->keterangan_pof;
                        }

                        $data_part->push((object) [
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
                            'available_part'    => trim($collection->available_part),
                            'is_cart'           => $is_cart,
                            'is_campaign'       => $is_campaign,
                            'nama_campaign'     => $nama_campaign,
                            'periode_campaign'  => $periode_campaign,
                            'keterangan_bo'     => $keterangan_bo,
                            'keterangan_faktur' => $keterangan_faktur,
                            'keterangan_pof'    => $keterangan_pof,
                        ]);

                        $part_number = $collection->part_number;
                    }
                }

                $result_search_part = $data_part->sortBy('part_number');
            }

            $result = [
                'total_item_cart'   => (double)$total_item_cart,
                'data' => $result_search_part
            ];

            return ApiResponse::responseSuccess('success', $result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function addPartFavorite(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'id_part'   => 'required',
                'is_love'   => 'required',
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi dan data part favorit');
            }

            $sql = DB::connection($request->get('divisi'))->table('mspart')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_part, '') as part_number")
                    ->where('id', $request->get('id_part'))
                    ->first();

            if(empty($sql->part_number) || trim($sql->part_number) == '') {
                return ApiResponse::responseWarning('Id part pada part number yang anda pilih tidak terdaftar');
            }

            $part_number = strtoupper(trim($sql->part_number));

            $sql = DB::connection($request->get('divisi'))->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(kd_dealer, '') as kode_dealer")
                    ->where('id', $request->get('ms_dealer_id'))
                    ->where('companyid', $request->userlogin['companyid'])
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Id dealer pada data dealer yang anda pilih tidak terdaftar');
            }

            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_dealer, $part_number) {
                DB::connection($request->get('divisi'))->insert('exec SP_PartFavorite_Simpan ?,?,?,?,?', [
                    strtoupper(trim($request->userlogin['user_id'])), strtoupper(trim($kode_dealer)),
                    strtoupper(trim($part_number)), (int)$request->get('is_love'),
                    strtoupper(trim($request->userlogin['companyid']))
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

    public function skemaPembelian(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'id_part_cart'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih divisi, dealer, dan part number terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))->table('msdealer')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.kd_dealer, '') as kode_dealer")
                    ->where('msdealer.id', $request->get('ms_dealer_id'))
                    ->where('msdealer.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->first();

            if(empty($sql->kode_dealer) || trim($sql->kode_dealer) == '') {
                return ApiResponse::responseWarning('Kode dealer tidak ditemukan');
            }
            $kode_dealer = strtoupper(trim($sql->kode_dealer));

            $sql = DB::connection($request->get('divisi'))->table('mspart')->lock('with (nolock)')
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
                                    part.companyid='".strtoupper(trim($request->userlogin['companyid']))."'
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
                                    faktur.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                                where	faktur.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                                where	pof.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
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
                            where	camp.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                    camp.tgl_prd1 >= convert(varchar(10), getdate(), 120) and
                                    camp.tgl_prd2 <= convert(varchar(10), getdate(), 120)
                        )	camp
                                inner join camp_dtl with (nolock) on camp.no_camp=camp_dtl.no_camp and
                                            camp.companyid=camp_dtl.companyid
                        where	camp_dtl.kd_part='".strtoupper(trim($part_number))."'
                    )	campaign on part.companyid=campaign.companyid and
                                    part.kd_part=campaign.kd_part";

            $result = DB::connection($request->get('divisi'))->select($sql);

            $jumlah_data = 0;
            $data_skema_pembelian = new Collection();

            foreach($result as $data) {
                $jumlah_data = (double)$jumlah_data + 1;

                if((double)$data->stock_total_part > 0) {
                    if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_MGMT') {
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

    public function priceList(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('pricelist')->lock('with (nolock)')
                    ->selectRaw("isnull(pricelist.tanggal, '') as tanggal,
                                isnull(pricelist.keterangan, '') as keterangan,
                                isnull(pricelist.nama_file, '') as nama_file,
                                isnull(pricelist.lokasi_file, '') as lokasi_file,
                                isnull(pricelist.ukuran_file, '') as ukuran_file")
                    ->orderBy('pricelist.tanggal', 'desc')
                    ->orderBy('pricelist.nama_file', 'desc')
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];

            return ApiResponse::responseSuccess('success', $data_result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function readyStock(Request $request) {
        try {
            $sql = "select	isnull(part.companyid, '') as companyid, isnull(part.kd_part, '') as part_number,
                            isnull(part.ket, '') as nama_part, isnull(part.kd_class, '') as class_produk,
                            isnull(part.kd_produk, '') as produk, isnull(part.kd_sub, '') as sub_produk,
                            iif(isnull(part.frg, '')='F', 'FIX', 'REGULER') as frg,
                            isnull(part.het, 0) as het, isnull(part.stock, 0) as stock
                    from
                    (
                        select
                                part.companyid, part.kd_part, part.ket, part.frg, part.het,
                                part.kd_sub, part.kd_produk, part.kd_class,
                                iif(isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0) < 0, 0,
                                    isnull(part.stock, 0) - isnull(minshare.qtymkr, 0) - isnull(minshare.qtypc, 0)) as stock
                        from
                        (
                            select	part.companyid, part.kd_part, part.ket, part.frg, part.het,
                                    isnull(sub.nama, '') as kd_sub,
                                    isnull(produk.nama, '') as kd_produk,
                                    isnull(classprod.nama, '') as kd_class,
                                    isnull(tbstlokasirak.stock, 0) -
                                        (isnull(stlokasi.min, 0) + isnull(stlokasi.in_transit, 0) +
                                        isnull(part.min_gudang, 0) + isnull(part.in_transit, 0) +
                                        isnull(part.kanvas, 0) + isnull(part.min_htl, 0)) as stock

                            from
                            (
                                select	part.companyid, part.kd_part, part.ket, part.kd_sub, part.frg, part.het,
                                        part.in_transit, part.min_gudang, part.kanvas, part.min_htl
                                from	part with (nolock)
                                where	part.companyid='".$request->userlogin['companyid']."' and
                                        part.kd_sub <> 'DSTO' and
                                        isnull(part.del_send, 0) = 0";

            if(!empty($request->get('frg')) && trim($request->get('frg')) != '') {
                $sql .= " and part.frg='".strtoupper(trim($request->get('frg')))."'";
            }

            $sql .= " )	part
                                    inner join company with (nolock) on part.companyid=company.companyid
                                    left join sub on part.kd_sub=sub.kd_sub
                                    left join produk on sub.kd_produk=produk.kd_produk
                                    left join classprod on produk.kd_class=classprod.kd_class
                                    left join stlokasi with (nolock) on part.kd_part=stlokasi.kd_part and
                                                company.kd_lokasi=stlokasi.kd_lokasi and
                                                part.companyid=stlokasi.companyid
                                    left join tbstlokasirak with (nolock) on part.kd_part=tbstlokasirak.kd_part and
                                                company.kd_lokasi=tbstlokasirak.kd_lokasi and
                                                company.kd_rak=tbstlokasirak.kd_rak and
                                                part.companyid=tbstlokasirak.companyid
                            where   part.companyid is not null";

            if(!empty($request->get('class_produk')) && trim($request->get('class_produk')) != '') {
                $sql .= " and classprod.kd_class='".strtoupper(trim($request->get('class_produk')))."'";
            }

            if(!empty($request->get('produk')) && trim($request->get('produk')) != '') {
                $sql .= " and produk.kd_produk='".strtoupper(trim($request->get('produk')))."'";
            }

            if(!empty($request->get('sub_produk')) && trim($request->get('sub_produk')) != '') {
                $sql .= " and sub.kd_sub='".strtoupper(trim($request->get('sub_produk')))."'";
            }

            $sql .= " )	part
                                left join minshare with (nolock) on part.kd_part=minshare.kd_part and
                                            part.companyid=minshare.companyid
                    )	part";

            if(strtoupper(trim($request->get('status_stock'))) == 'READY_STOCK') {
                $sql .= "  where part.stock > 0 ";
            }

            $sql .= " order by part.kd_part asc";

            $result = DB::connection($request->get('divisi'))->select($sql);

            $data_ready_stock = [];
            $nomor_urut = 0;
            $jumlah_data = 0;

            foreach($result as $data) {
                $jumlah_data = (double)$jumlah_data + 1;
                $nomor_urut = (double)$nomor_urut + 1;

                $data_ready_stock[] = [
                    'no'            => (double)$nomor_urut,
                    'part_number'   => strtoupper(trim($data->part_number)),
                    'nama_part'     => strtoupper(trim($data->nama_part)),
                    'het'           => (double)$data->het,
                ];
            }

            if((double)$jumlah_data <= 0) {
                return ApiResponse::responseWarning('Tidak ada data yang ditemukan');
            }

            return ApiResponse::responseSuccess('success', $data_ready_stock);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listBackOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $filter_salesman = $request->get('salesman');
            $filter_dealer = $request->get('dealer');
            $filter_produk = $request->get('item_group');
            $filter_part_number = $request->get('part_number');

            if ($request->userlogin['role_id'] == "MD_H3_SM") {
                $filter_salesman = $request->userlogin['user_id'];
            }

            if ($request->userlogin['role_id'] == "D_H3") {
                $filter_dealer = $request->userlogin['user_id'];
            }

            $sql = DB::connection($request->get('divisi'))->table('bo')->lock('with (nolock)')
                    ->selectRaw("isnull(bo.kd_part, '') as part_number")
                    ->leftJoin(DB::raw('part with (nolock)'), function($join) {
                        $join->on('part.kd_part', '=', 'bo.kd_part')
                            ->on('part.companyid', '=', 'bo.companyid');
                    })
                    ->leftJoin(DB::raw('sub with (nolock)'), function($join) {
                        $join->on('sub.kd_sub', '=', 'part.kd_sub');
                    });

            if(!empty($filter_salesman) && trim($filter_salesman) != '') {
                $sql->where('bo.kd_sales', strtoupper(trim($filter_salesman)));
            }

            if(!empty($filter_dealer) && trim($filter_dealer) != '') {
                $sql->where('bo.kd_dealer', strtoupper(trim($filter_dealer)));
            }

            if(!empty($filter_produk) && trim($filter_produk) != '') {
                $sql->where('sub.kd_produk', strtoupper(trim($filter_produk)));
            }

            if(!empty($filter_part_number) && trim($filter_part_number) != '') {
                $sql->where('bo.kd_part', 'like', strtoupper(trim($filter_part_number)).'%');
            }

            $sql = $sql->orderBy('bo.kd_part','asc')
                        ->paginate(10);

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

            $data_back_order = [];

            if(strtoupper(trim($list_part_number)) != '') {
                $sql = "select	isnull(bo.companyid, '') as companyid, isnull(bo.kd_sales, '') as kode_sales,
                                isnull(bo.nm_sales, '') as nama_sales, isnull(bo.kd_dealer, '') as kode_dealer,
                                isnull(bo.nm_dealer, '') as nama_dealer, isnull(bo.kd_part, '') as part_number,
                                isnull(part.ket, '') as nama_part, isnull(produk.nama, '') as produk,
                                isnull(bo.kd_tpc, '') as kode_tpc, isnull(bo.jumlah, 0) as jumlah_bo
                        from
                        (
                            select	bo.companyid, bo.kd_sales, salesman.nm_sales,
                                    bo.kd_dealer, dealer.nm_dealer, bo.kd_part,
                                    bo.kd_tpc, bo.jumlah
                            from
                            (
                                select	bo.companyid, bo.kd_sales, bo.kd_dealer,
                                        bo.kd_part, bo.jumlah, bo.kd_tpc
                                from	bo with (nolock)
                                where	isnull(bo.jumlah, 0) > 0 and
                                        bo.kd_part in (".$list_part_number.") and
                                        bo.companyid='".$request->userlogin['companyid']."' ";

                if(!empty($filter_salesman) && trim($filter_salesman) != '') {
                    $sql .= " and bo.kd_sales='".strtoupper(trim($filter_salesman))."' ";
                }

                if(!empty($filter_dealer) && trim($filter_dealer) != '') {
                    $sql .= " and bo.kd_dealer='".strtoupper(trim($filter_dealer))."' ";
                }

                if(!empty($filter_part_number) && trim($filter_part_number) != '') {
                    $sql .= " and bo.kd_part like '".strtoupper(trim($filter_part_number))."%'";
                }

                $sql .= " )	bo
                                    left join salesman with (nolock) on bo.kd_sales=salesman.kd_sales and
                                                bo.companyid=salesman.companyid
                                    left join dealer with (nolock) on bo.kd_dealer=dealer.kd_dealer and
                                                bo.companyid=dealer.companyid
                        )	bo
                                left join part with (nolock) on bo.kd_part=part.kd_part and
                                            bo.companyid=part.companyid
                                left join sub with (nolock) on part.kd_sub=sub.kd_sub
                                left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

                if(!empty($filter_produk) && trim($filter_produk) != '') {
                    $sql .= " where produk.kd_produk='".strtoupper(trim($filter_produk))."'";
                }

                $sql .= " order by bo.kd_part asc";

                $data_result = DB::select($sql);

                foreach($data_result as $result) {
                    $data_back_order[] = [
                        'kode_sales'    => trim($result->kode_sales),
                        'nama_sales'    => trim($result->nama_sales),
                        'kode_dealer'   => trim($result->kode_dealer),
                        'nama_dealer'   => trim($result->nama_dealer),
                        'part_pictures' => config('constants.app.app_images_url').'/'.strtoupper(trim($result->part_number)).'.jpg',
                        'part_number'   => trim($result->part_number),
                        'nama_part'     => trim($result->nama_part),
                        'produk'        => trim($result->produk),
                        'kode_tpc'      =>trim($result->kode_tpc),
                        'jumlah_bo'     => (int)$result->jumlah_bo
                    ];
                }
            }

            return ApiResponse::responseSuccess('success', $data_back_order);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function downloadBackOrder(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $filter_salesman = $request->get('salesman');
            $filter_dealer = $request->get('dealer');
            $filter_part_number = $request->get('part_number');

            if ($request->userlogin['role_id'] == "MD_H3_SM") {
                $filter_salesman = $request->userlogin['user_id'];
            }

            if ($request->userlogin['role_id'] == "D_H3") {
                $filter_dealer = $request->userlogin['user_id'];
            }

            $sql = "select	isnull(bo.kd_sales, '') as salesman, isnull(bo.kd_dealer, '') as dealer,
                            isnull(bo.kd_part, '') as part_number, isnull(part.ket, '') as description,
                            isnull(bo.ket, '') as keterangan, isnull(bo.jumlah, 0) as jumlah
                    from
                    (
                        select	*
                        from	bo with (nolock)
                        where	bo.companyid='".$request->userlogin['companyid']."' ";

            if(!empty($filter_salesman) && trim($filter_salesman) != '') {
                $sql .= " and bo.kd_sales='".strtoupper(trim($filter_salesman))."' ";
            }

            if(!empty($filter_dealer) && trim($filter_dealer) != '') {
                $sql .= " and bo.kd_dealer='".strtoupper(trim($filter_dealer))."' ";
            }

            if(!empty($filter_part_number) && trim($filter_part_number) != '') {
                $sql .= " and bo.kd_part like '".strtoupper(trim($filter_part_number))."%'";
            }

            $sql .= " )	bo
                            left join part with (nolock) on bo.kd_part=part.kd_part and
                                        bo.companyid=part.companyid
                            left join sub with (nolock) on part.kd_sub=sub.kd_sub
                            left join produk with (nolock) on sub.kd_produk=produk.kd_produk";

            if(!empty($filter_produk) && trim($filter_produk) != '') {
                $sql .= " where produk.kd_produk='".strtoupper(trim($filter_produk))."%'";
            }

            $sql .= " order by bo.kd_sales asc, bo.kd_dealer asc, bo.kd_part asc";


            $result = DB::connection($request->get('divisi'))->select($sql);
            $data_bo = [];

            $urutan = 0;

            foreach($result as $result) {
                $urutan = (int)$urutan + 1;

                $data_bo[] = [
                    'no'            => (int)$urutan,
                    'salesman'      => trim($result->salesman),
                    'dealer'        => trim($result->dealer),
                    'part_number'   => trim($result->part_number),
                    'description'   => trim($result->description),
                    'keterangan'    => trim($result->keterangan),
                    'jumlah'        => (int)$result->jumlah
                ];
            }

            if(empty($data_bo)) {
                return ApiResponse::responseWarning('Tidak ada data yang ditemukan');
            }

            return ApiResponse::responseSuccess('success', $data_bo);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

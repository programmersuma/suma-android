<?php

namespace App\Http\Controllers\Api\Dealer;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class DealerController extends Controller {

    public function listDealer(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

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

            /* ==================================================================== */
            /* Search Data Favorite */
            /* ==================================================================== */
            $sql = DB::connection($request->get('divisi'))
                    ->table('dealer_fav')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.id, 0) as id,
                                isnull(dealer_fav.kd_dealer, '') as kode_dealer,
                                isnull(dealer.nm_dealer, '') as nama_dealer,
                                isnull(dealer.alamat1, '') as alamat,
                                isnull(dealer.kota, '') as kota,
                                isnull(dealer.kabupaten, '') as kabupaten,
                                isnull(dealer.latitude, 0) as latitude,
                                isnull(dealer.longitude, 0) as longitude")
                    ->leftJoin(DB::raw('msdealer with (nolock)'), function($join) {
                        $join->on('msdealer.kd_dealer', '=', 'dealer_fav.kd_dealer')
                            ->on('msdealer.companyid', '=', 'dealer_fav.companyid');
                    })
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'dealer_fav.kd_dealer')
                            ->on('dealer.companyid', '=', 'dealer_fav.companyid');
                    })
                    ->where('dealer_fav.user_id', strtoupper(trim($request->userlogin['user_id'])))
                    ->where('dealer_fav.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->whereRaw("isnull(dealer.delsign, 0)=0");

            if(!empty($request->get('search')) && trim($request->get('search') != '')) {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('dealer_fav.kd_dealer', 'like', '%'.trim($request->get('search')).'%')
                            ->orWhere('dealer.nm_dealer', 'like', '%'.trim($request->get('search')).'%');
                });
            }

            $sql = $sql->orderBy('dealer_fav.kd_dealer', 'asc')
                        ->get();

            foreach($sql as $data) {
                $data_favorite[] = [
                    'id'        => (int)$data->id,
                    'code'      => strtoupper(trim($data->kode_dealer)),
                    'name'      => strtoupper(trim($data->nama_dealer)),
                    'address'   => strtoupper(trim($data->alamat)),
                    'regency'   => strtoupper(trim($data->kabupaten)),
                    'latitude'  => trim($data->latitude),
                    'longitude' => trim($data->longitude)
                ];
            }
            /* ==================================================================== */
            /* Search Data Dealer */
            /* ==================================================================== */
            $sql = DB::connection($request->get('divisi'))
                    ->table('dealer')->lock('with (nolock)')
                    ->selectRaw("isnull(dealer.kd_dealer, '') as kode_dealer")
                    ->whereRaw("isnull(dealer.delsign, 0)=0")
                    ->where('dealer.companyid', strtoupper(trim($request->userlogin['companyid'])));

            if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_KORSM') {
                $sql->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                    $join->on('salesman.kd_sales', '=', 'dealer.kd_sales')
                        ->on('salesman.companyid', '=', 'dealer.companyid');
                });
                $sql->where('salesman.spv', $kode_supervisor);
            } elseif(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_SM') {
                $sql->where('dealer.kd_sales', strtoupper(trim($request->userlogin['user_id'])));
            }

            if(!empty($request->get('search')) && trim($request->get('search') != '')) {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('dealer.kd_dealer', 'like', '%'.trim($request->get('search')).'%')
                            ->orWhere('dealer.nm_dealer', 'like', '%'.trim($request->get('search')).'%');
                });
            }

            $sql = $sql->orderBy('dealer.kd_dealer', 'asc')
                        ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $kode_dealer_result = '';

            $data_favorite = [];
            $data_dealer = [];

            foreach($data_result as $data) {
                if(strtoupper(trim($kode_dealer_result)) == '') {
                    $kode_dealer_result = "'".strtoupper(trim($data->kode_dealer))."'";
                } else {
                    $kode_dealer_result .= ",'".strtoupper(trim($data->kode_dealer))."'";
                }
            }

            if(trim($kode_dealer_result) != '') {
                $sql = "select  isnull(msdealer.id, 0) as id,
                                isnull(msdealer.kd_dealer, '') as kode_dealer,
                                isnull(dealer.nm_dealer, '') as nama_dealer,
                                isnull(dealer.alamat1, '') as alamat,
                                isnull(dealer.kota, '') as kota,
                                isnull(dealer.kabupaten, '') as kabupaten,
                                isnull(dealer.latitude, 0) as latitude,
                                isnull(dealer.longitude, 0) as longitude
                        from
                        (
                            select  msdealer.companyid, msdealer.id, msdealer.kd_dealer
                            from    msdealer with (nolock)
                            where   msdealer.kd_dealer in (".strtoupper(trim($kode_dealer_result)).") and
                                    msdealer.companyid=?
                        )   msdealer
                                left join dealer with (nolock) on msdealer.kd_dealer=dealer.kd_dealer and
                                            msdealer.companyid=dealer.companyid
                        order by msdealer.kd_dealer asc";

                $result = DB::connection($request->get('divisi'))->select($sql, [ strtoupper(trim($request->userlogin['companyid'])) ]);

                foreach($result as $data) {
                    $data_dealer[] = [
                        'id'        => (int)$data->id,
                        'code'      => strtoupper(trim($data->kode_dealer)),
                        'name'      => strtoupper(trim($data->nama_dealer)),
                        'address'   => strtoupper(trim($data->alamat)),
                        'regency'   => strtoupper(trim($data->kabupaten)),
                        'latitude'  => trim($data->latitude),
                        'longitude' => trim($data->longitude)
                    ];
                }
            }

            $result_list_dealer = [
                'data'  => [
                    'favorit'   => $data_favorite,
                    'list'      => $data_dealer
                ]
            ];

            return ApiResponse::responseSuccess('success', $result_list_dealer);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listDealerSalesman(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
                'salesman'  => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data salesman dan divisi tidak boleh kosong');
            }

            $salesman = '';

            if(!empty($request->get('salesman'))) {
                $list_salesman = explode(',', $request->get('salesman'));

                foreach($list_salesman as $data) {
                    if(trim($salesman) == '') {
                        $salesman = "'".$data."'";
                    } else {
                        $salesman .= ",'".$data."'";
                    }
                }
            }
            /* ==================================================================== */
            /* Search Data Favorite */
            /* ==================================================================== */
            $sql = DB::connection($request->get('divisi'))
                    ->table('dealer_fav')->lock('with (nolock)')
                    ->selectRaw("isnull(msdealer.id, 0) as id,
                                isnull(dealer_fav.kd_dealer, '') as kode_dealer,
                                isnull(dealer.nm_dealer, '') as nama_dealer,
                                isnull(dealer.alamat1, '') as alamat,
                                isnull(dealer.kota, '') as kota,
                                isnull(dealer.kabupaten, '') as kabupaten,
                                isnull(dealer.latitude, 0) as latitude,
                                isnull(dealer.longitude, 0) as longitude")
                    ->leftJoin(DB::raw('msdealer with (nolock)'), function($join) {
                        $join->on('msdealer.kd_dealer', '=', 'dealer_fav.kd_dealer')
                            ->on('msdealer.companyid', '=', 'dealer_fav.companyid');
                    })
                    ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                        $join->on('dealer.kd_dealer', '=', 'dealer_fav.kd_dealer')
                            ->on('dealer.companyid', '=', 'dealer_fav.companyid');
                    })
                    ->whereRaw("dealer.kd_sales in (".$salesman.")")
                    ->where('dealer_fav.user_id', strtoupper(trim($request->userlogin['user_id'])))
                    ->where('dealer_fav.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->whereRaw("isnull(dealer.delsign, 0)=0");

            if(!empty($request->get('search')) && trim($request->get('search') != '')) {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('dealer_fav.kd_dealer', 'like', '%'.trim($request->get('search')).'%')
                            ->orWhere('dealer.nm_dealer', 'like', '%'.trim($request->get('search')).'%');
                });
            }

            $sql = $sql->orderBy('dealer_fav.kd_dealer', 'asc')
                        ->get();

            foreach($sql as $data) {
                $data_favorite[] = [
                    'id'        => (int)$data->id,
                    'code'      => strtoupper(trim($data->kode_dealer)),
                    'name'      => strtoupper(trim($data->nama_dealer)),
                    'address'   => strtoupper(trim($data->alamat)),
                    'regency'   => strtoupper(trim($data->kabupaten)),
                    'latitude'  => trim($data->latitude),
                    'longitude' => trim($data->longitude)
                ];
            }
            /* ==================================================================== */
            /* Search Data Dealer */
            /* ==================================================================== */
            $sql = DB::connection($request->get('divisi'))
                    ->table('dealer')->lock('with (nolock)')
                    ->selectRaw("isnull(dealer.kd_dealer, '') as kode_dealer")
                    ->whereRaw("isnull(dealer.delsign, 0)=0")
                    ->whereRaw("dealer.kd_sales in (".$salesman.")")
                    ->where('dealer.companyid', strtoupper(trim($request->userlogin['companyid'])));

            if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_SM') {
                $sql->where('dealer.kd_sales', strtoupper(trim($request->userlogin['user_id'])));
            }

            if(!empty($request->get('search')) && trim($request->get('search') != '')) {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('dealer.kd_dealer', 'like', '%'.trim($request->get('search')).'%')
                            ->orWhere('dealer.nm_dealer', 'like', '%'.trim($request->get('search')).'%');
                });
            }

            $sql = $sql->orderBy('dealer.kd_dealer', 'asc')
                        ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $kode_dealer_result = '';

            $data_favorite = [];
            $data_dealer = [];

            foreach($data_result as $data) {
                if(strtoupper(trim($kode_dealer_result)) == '') {
                    $kode_dealer_result = "'".strtoupper(trim($data->kode_dealer))."'";
                } else {
                    $kode_dealer_result .= ",'".strtoupper(trim($data->kode_dealer))."'";
                }
            }

            if(trim($kode_dealer_result) != '') {
                $sql = "select  isnull(msdealer.id, 0) as id,
                                isnull(msdealer.kd_dealer, '') as kode_dealer,
                                isnull(dealer.nm_dealer, '') as nama_dealer,
                                isnull(dealer.alamat1, '') as alamat,
                                isnull(dealer.kota, '') as kota,
                                isnull(dealer.kabupaten, '') as kabupaten,
                                isnull(dealer.latitude, 0) as latitude,
                                isnull(dealer.longitude, 0) as longitude
                        from
                        (
                            select  msdealer.companyid, msdealer.id, msdealer.kd_dealer
                            from    msdealer with (nolock)
                            where   msdealer.kd_dealer in (".strtoupper(trim($kode_dealer_result)).") and
                                    msdealer.companyid=?
                        )   msdealer
                                left join dealer with (nolock) on msdealer.kd_dealer=dealer.kd_dealer and
                                            msdealer.companyid=dealer.companyid
                        order by msdealer.kd_dealer asc";

                $result = DB::connection($request->get('divisi'))->select($sql, [ strtoupper(trim($request->userlogin['companyid'])) ]);

                foreach($result as $data) {
                    $data_dealer[] = [
                        'id'        => (int)$data->id,
                        'code'      => strtoupper(trim($data->kode_dealer)),
                        'name'      => strtoupper(trim($data->nama_dealer)),
                        'address'   => strtoupper(trim($data->alamat)),
                        'regency'   => strtoupper(trim($data->kabupaten)),
                        'latitude'  => trim($data->latitude),
                        'longitude' => trim($data->longitude)
                    ];
                }
            }

            $result_list_dealer = [
                'data'  => [
                    'favorit'   => $data_favorite,
                    'list'      => $data_dealer
                ]
            ];

            return ApiResponse::responseSuccess('success', $result_list_dealer);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listCompetitor(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('competitor')->lock('with (nolock)')
                    ->selectRaw("isnull(competitor.id, 0) as id,
                                isnull(competitor.kd_dealer, '') as code_dealer,
                                isnull(competitor.nama_competitor, '') as name_competitor,
                                isnull(competitor.produk, '') as product,
                                isnull(competitor.judul, '') as title_activity_competitor,
                                isnull(competitor.tgl_awal, '') as begin_effdate,
                                isnull(competitor.tgl_akhir, '') as end_effdate,
                                isnull(competitor.photo, '') as photo,
                                isnull(competitor.description, '') as description,
                                isnull(competitor.usertime, '') as usertime")
                    ->where('competitor.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->orderBy('competitor.usertime', 'desc')
                    ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $data_competitor = [];

            foreach($data_result as $result) {
                $data_competitor[] = [
                    'id'                        => (int)$result->id,
                    'code_dealer'               => strtoupper(trim($result->code_dealer)),
                    'id_role'                   => strtoupper(trim($request->userlogin['role_id'])),
                    'id_user'                   => (int)$request->userlogin['id'],
                    'name_competitor'           => strtoupper(trim($result->name_competitor)),
                    'product'                   => strtoupper(trim($result->product)),
                    'title_activity_competitor' => strtoupper(trim($result->title_activity_competitor)),
                    'begin_effdate'             => strtoupper(trim($result->begin_effdate)),
                    'end_effdate'               => strtoupper(trim($result->end_effdate)),
                    'photo'                     => \json_decode($result->photo),
                    'description'               => strtoupper(trim($result->description)),
                    'created_at'                => strtoupper(trim($result->usertime))
                ];
            }
            return ApiResponse::responseSuccess('success', [ 'data' => $data_competitor ]);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function addCompetitor(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $validate = Validator::make($request->all(), [
                'code_dealer'       => 'required',
                'id_role'           => 'required',
                'name_competitor'   => 'required',
                'product'           => 'required',
                'title_activity_competitor' => 'required',
                'begin_effdate'     => 'required',
                'end_effdate'       => 'required',
                'photo'             => 'required',
                'description'       => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Isi data competitor secara lengkap');
            }

            $photo = $request->get('photo');
            $data_photo = [];
            $imagedata = json_decode($photo);

            foreach($imagedata as $item) {
                $dataPhoto = $item->photo;

                $convertImage = str_replace('data:image/png;base64,', '', $dataPhoto);
                $file_image = str_replace(' ', '+', $convertImage);
                $file_name = trim($request->userlogin['user_id']).'-'.time().'-'.Str::random(10).'.'.'png';
                File::put(public_path('assets/images/competitor/') . $file_name, base64_decode($file_image));
                $location_file =  config('constants.app.app_url_hosting') .'/assets/images/competitor/' . $file_name;

                $data_photo[] = [
                    'photo' => $location_file
                ];
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $data_photo) {
                DB::connection($request->get('divisi'))
                    ->insert('insert into competitor (kd_dealer, role_id, user_id, nama_competitor, produk, judul, tgl_awal, tgl_akhir,
                                description, usertime, companyid, photo) values (?,?,?,?,?,?,?,?,?,?,?,?)', [
                    strtoupper(trim($request->get('code_dealer'))), strtoupper(trim($request->get('id_role'))),
                    strtoupper(trim($request->userlogin['user_id'])), strtoupper(trim($request->get('name_competitor'))),
                    strtoupper(trim($request->get('product'))), strtoupper(trim($request->get('title_activity_competitor'))),
                    $request->get('begin_effdate'), $request->get('end_effdate'), $request->get('description'),
                    date('Y-m-d H:i:s'), strtoupper(trim($request->userlogin['companyid'])), json_encode($data_photo)
                ]);
            });

            return ApiResponse::responseSuccess('Data competitor berhasil disimpan', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function addNewDealer(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $validate = Validator::make($request->all(), [
                'name'          => 'required',
                'latlong'       => 'required',
                'address'       => 'required',
                'phone'         => 'required',
                'description'   => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Isi data dealer baru secara lengkap');
            }

            $user_id = $request->userlogin['user_id'];
            $companyid = $request->userlogin['companyid'];

            $geoLocation = explode(",", $request->get('latlong'));
            $latitude = trim($geoLocation[0]);
            $longitude = trim($geoLocation[1]);
            $code_dealer = time().'='.$request->userlogin['user_id'];

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $user_id, $code_dealer, $latitude, $longitude, $companyid) {
                DB::connection($request->get('divisi'))
                    ->insert('exec SP_DealerCandidate_Simpan ?,?,?,?,?,?,?,?,?', [
                        strtoupper(trim($user_id)), strtoupper(trim($code_dealer)),
                        strtoupper(trim($request->get('name'))), trim($latitude),
                        trim($longitude), strtoupper(trim($request->get('address'))),
                        strtoupper(trim($request->get('description'))),
                        trim($request->get('phone')), strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data Berhasil Disimpan', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function updateDealerLocation(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'kode_dealer'   => 'required',
                'latlong'       => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Isi data divisi dan kode dealer terlebih dahulu');
            }

            $user_id = strtoupper(trim($request->userlogin['user_id']));
            $companyid = strtoupper(trim($request->userlogin['companyid']));

            $result = DB::connection($request->get('divisi'))
                        ->table('dealer')->lock('with (nolock)')
                        ->select('dealer.kd_dealer')
                        ->where('dealer.kd_dealer', $request->get('kode_dealer'))
                        ->where('dealer.companyid', $companyid)
                        ->first();

            if (empty($result->kd_dealer)) {
                return ApiResponse::responseWarning('Kode dealer yang anda entry tidak terdaftar');
            }

            $geoLocation = explode(",", $request->get('latlong'));
            $latitude = trim($geoLocation[0]);
            $longitude = trim($geoLocation[1]);

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $latitude, $longitude, $user_id, $companyid) {
                DB::connection($request->get('divisi'))
                    ->insert('exec SP_Dealer_UpdateLokasi ?,?,?,?,?', [
                        strtoupper(trim($request->get('kode_dealer'))), $latitude, $longitude,
                        strtoupper(trim($user_id)), strtoupper(trim($companyid))
                ]);
            });

            return ApiResponse::responseSuccess('Data Berhasil Disimpan', $request->all());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listKreditLimit(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Silahkan isi data divisi dan pilih data dealer terlebih dahulu');
            }

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

            $list_dealer_selected = json_decode(str_replace('\\', '', $request->get('ms_dealer_id')));
            $list_dealer = '';

            foreach($list_dealer_selected as $result) {
                if(strtoupper(trim($list_dealer)) == '') {
                    $list_dealer = "'".strtoupper(trim($result->dealer_code))."'";
                } else {
                    $list_dealer .= ",'".strtoupper(trim($result->dealer_code))."'";
                }
            }

            if(trim($list_dealer) == '') {
                return ApiResponse::responseWarning('Pilih data dealer terlebih dahulu');
            }

            /* ==================================================================== */
            /* Detail data kredit limit */
            /* ==================================================================== */
            $sql = "select	isnull(msdealer.kd_dealer, '') as dealer_code,
                            isnull(rtrim(dealer.nm_dealer), '') as dealer_name,
                            isnull(rtrim(dealer.alamat1), '') as dealer_address,
                            convert(varchar(10), getdate(), 120) as date,
                            isnull(dealer.kd_sales, '') as salesman_code,
                            isnull(salesman.nm_sales, '') as salesman_name,
                            isnull(dealer.limit_piut, 0) as plafon_kredit_limit,
                            case
                                when isnull(limit_piut, 0)=1 or isnull(limit_sales, 0)=1 then 0
                                when isnull(limit_piut, 0) <> 0 or isnull(limit_sales, 0) <> 0 then
                                    isnull(limit_piut, 0) - (isnull(s_awal_b, 0) + isnull(jual_14, 0) +
                                    isnull(jual_20, 0) - isnull(extra, 0) + isnull(da, 0) -
                                    isnull(ca, 0) - isnull(insentif, 0) - isnull(t_bayar_b, 0))
                                else 0
                            end as maximum_open,
                            (isnull(s_awal_b, 0) + isnull(jual_14, 0) + isnull(jual_20, 0) - isnull(extra, 0) +
                                isnull(da, 0) - isnull(ca, 0) - isnull(insentif, 0) -
                                isnull(t_bayar_b, 0)) as sisa_piutang
                    from
                    (
                        select	msdealer.companyid, msdealer.id, msdealer.kd_dealer
                        from    msdealer with (nolock)
                        where	msdealer.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                msdealer.kd_dealer in (".trim($list_dealer).")
                    )	msdealer
                            inner join dealer with (nolock) on msdealer.kd_dealer=dealer.kd_dealer and
                                        msdealer.companyid=dealer.companyid
                            left join salesman with (nolock) on dealer.kd_sales=salesman.kd_sales and
                                        msdealer.companyid=salesman.companyid
                    where   msdealer.companyid is not null";

            if(strtoupper(trim($request->userlogin['role_id'])) == "MD_H3_SM") {
                $sql .= " and dealer.kd_sales='".strtoupper(trim($request->userlogin['user_id']))."'";
            } elseif(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_KORSM') {
                $sql .= " and salesman.spv='".strtoupper(trim($kode_supervisor))."'";
            }

            $sql .= " order by msdealer.kd_dealer asc";

            $result = DB::connection($request->get('divisi'))->select($sql);
            $data_kredit_limit = [];

            foreach($result as $data) {
                $data_kredit_limit[] = [
                    'dealer_code'           => strtoupper(trim($data->dealer_code)),
                    'dealer_name'           => strtoupper(trim($data->dealer_name)),
                    'dealer_address'        => strtoupper(trim($data->dealer_address)),
                    'salesman_code'         => strtoupper(trim($data->salesman_code)),
                    'salesman_name'         => strtoupper(trim($data->salesman_name)),
                    'date'                  => $data->date,
                    'plafon_kredit_limit'   => (strtoupper(trim($request->userlogin['role_id'])) == 'D_H3') ? 0 : (double)$data->plafon_kredit_limit,
                    'maximum_open'          => (strtoupper(trim($request->userlogin['role_id'])) == 'D_H3') ? 0 : (double)$data->maximum_open,
                    'sisa_piutang'          => (strtoupper(trim($request->userlogin['role_id'])) == 'D_H3') ? 0 : (double)$data->sisa_piutang,
                ];
            }

            return ApiResponse::responseSuccess('success', $data_kredit_limit);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listJatuhTempo(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'ms_dealer_id'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Silahkan isi data divisi dan pilih data dealer terlebih dahulu');
            }

            $list_dealer_selected = json_decode(str_replace('\\', '', $request->get('ms_dealer_id')));
            $list_dealer = '';
            $jumlah_data_filter_dealer = 0;

            foreach($list_dealer_selected as $result) {
                $jumlah_data_filter_dealer = (double)$jumlah_data_filter_dealer + 1;

                if(strtoupper(trim($list_dealer)) == '') {
                    $list_dealer = "'".strtoupper(trim($result->dealer_code))."'";
                } else {
                    $list_dealer .= ",'".strtoupper(trim($result->dealer_code))."'";
                }
            }

            /* ==================================================================== */
            /* Detail data jatuh tempo */
            /* ==================================================================== */
            $sql = "select	isnull(rtrim(faktur.no_faktur), '') as nomor_faktur,
                            isnull(rtrim(faktur.kd_dealer), '') as dealer_code,
                            isnull(rtrim(faktur.nm_dealer), '') as dealer_name,
                            isnull(rtrim(faktur.kd_sales), '') as salesman_code,
                            isnull(rtrim(faktur.nm_sales), '') as salesman_name,
                            isnull(faktur.tgl_faktur, '') as tanggal_faktur,
                            isnull(faktur.jatuh_tempo, '') as jatuh_tempo,
                            case
                                when datediff(day, getdate(), faktur.jatuh_tempo) <= 0 then 'red'
                                when datediff(day, getdate(), faktur.jatuh_tempo) > 1 and
                                    datediff(day, getdate(), faktur.jatuh_tempo) < 8 then 'yellow'
                            else 'green'
                            end as flag,
                            isnull(faktur.total_faktur, 0) as total_faktur,
                            isnull(faktur.terbayar_realisasi, 0) as terbayar_realisasi,
                            isnull(faktur.terbayar_belum_realisasi, 0) as terbayar_belum_realisasi,
                            isnull(faktur.total_pembayaran, 0) as total_pembayaran,
                            isnull(faktur.sisa_pembayaran, 0) as sisa_pembayaran
                    from
                    (
                        select	faktur.no_faktur, faktur.tgl_faktur, dealer.kd_dealer,
                                dealer.nm_dealer, faktur.kd_sales, salesman.nm_sales,
                                dateadd(day, isnull(dealer.jtp, 0),
                                    dateadd(day, isnull(faktur.umur_faktur, 0),
                                        cast(iif(isnull(faktur.tgl_sj, '')='',
                                            dateadd(day, 4, faktur.tgl_faktur),
                                            faktur.tgl_sj) as date))) as jatuh_tempo,
                                isnull(faktur.total, 0) as total_faktur,
                                isnull(faktur.terbayar_realisasi, 0) as terbayar_realisasi,
                                isnull(faktur.terbayar_belum_realisasi, 0) as terbayar_belum_realisasi,
                                isnull(faktur.terbayar_realisasi, 0) + isnull(faktur.terbayar_belum_realisasi, 0) as total_pembayaran,
                                isnull(faktur.total, 0) - (isnull(faktur.terbayar_realisasi, 0) + isnull(faktur.terbayar_belum_realisasi, 0)) as sisa_pembayaran
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur,
                                    faktur.tgl_akhir_faktur, faktur.kd_sales, faktur.kd_dealer,
                                    faktur.total, faktur.terbayar, faktur.tgl_sj,
                                    faktur.umur_faktur, faktur.jtp_khusus,
                                    sum(iif(isnull(terima.realisasi, 0)=0, 0,
                                        isnull(terimadtl.jumlah, 0))) as terbayar_realisasi,
                                    sum(iif(isnull(terima.realisasi, 0)=1, 0,
                                        isnull(terimadtl.jumlah, 0))) as terbayar_belum_realisasi
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur,
                                        faktur.tgl_akhir_faktur, faktur.kd_sales, faktur.kd_dealer,
                                        faktur.total, faktur.terbayar, faktur.tgl_sj,
                                        faktur.umur_faktur, faktur.jtp_khusus
                                from	faktur with (nolock)
                                where	faktur.companyid='".strtoupper(trim($request->userlogin['companyid']))."' and
                                        faktur.kd_dealer in (".$list_dealer.")
                            )	faktur
                                    left join terimadtl with (nolock) on faktur.no_faktur=terimadtl.no_faktur
                                    left join terima with (nolock) on terimadtl.no_bpk=terima.no_bpk and
                                                    faktur.companyid=terima.companyid
                            group by faktur.companyid, faktur.no_faktur, faktur.tgl_faktur,
                                    faktur.tgl_akhir_faktur, faktur.kd_sales, faktur.kd_dealer,
                                    faktur.total, faktur.terbayar, faktur.tgl_sj,
                                    faktur.umur_faktur, faktur.jtp_khusus
                            having isnull(faktur.total, 0) >
                                    sum(iif(isnull(terima.realisasi, 0)=1, isnull(terimadtl.jumlah, 0), 0))
                        )	faktur
                                left join dealer with (nolock) on faktur.kd_dealer=dealer.kd_dealer and
                                            faktur.companyid=dealer.companyid
                                left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                            faktur.companyid=salesman.companyid
                    )	faktur";

            $query = DB::connection($request->get('divisi'))
                        ->table(DB::raw('('.$sql.') as jtp'))
                        ->orderBy('jtp.jatuh_tempo', 'asc')
                        ->orderBy('jtp.nomor_faktur', 'asc')
                        ->paginate(15);

            $result = collect($query)->toArray();
            $data_result = $result['data'];
            $data_jatuh_tempo = [];

            foreach($data_result as $data) {
                $data_jatuh_tempo[] = [
                    'nomor_faktur'      => strtoupper(trim($data->nomor_faktur)),
                    'dealer_code'       => strtoupper(trim($data->dealer_code)),
                    'dealer_name'       => strtoupper(trim($data->dealer_name)),
                    'salesman_code'     => strtoupper(trim($data->salesman_code)),
                    'salesman_name'     => strtoupper(trim($data->salesman_name)),
                    'tanggal_faktur'    => trim($data->tanggal_faktur),
                    'jatuh_tempo'       => trim($data->jatuh_tempo),
                    'flag'              => trim($data->flag),
                    'total_faktur'      => (double)$data->total_faktur,
                    'total_pembayaran'  => (double)$data->total_pembayaran,
                    'sisa_pembayaran'   => (double)$data->sisa_pembayaran,
                    'status'            => ((double)$data->terbayar_belum_realisasi > 0) ? 'Belum Realisasi' : ''
                ];
            }

            return ApiResponse::responseSuccess('success', $data_jatuh_tempo);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function detailJatuhTempo(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'nomor_faktur'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Silahkan isi data divisi dan pilih nomor faktur terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('faktur')->lock('with (nolock)')
                    ->selectRaw("isnull(faktur.no_faktur, '') as nomor_faktur")
                    ->where('faktur.no_faktur', $request->get('nomor_faktur'))
                    ->where('faktur.companyid', strtoupper(trim($request->userlogin['companyid'])))
                    ->first();

            if(empty('nomor_faktur') || trim($sql->nomor_faktur) == '') {
                return ApiResponse::responseWarning('Nomor faktur tidak terdaftar');
            }

            $sql = "select	isnull(rtrim(faktur.no_faktur), '') as nomor_faktur,
                            isnull(rtrim(faktur.kd_dealer), '') as dealer_code,
                            isnull(rtrim(faktur.nm_dealer), '') as dealer_name,
                            isnull(rtrim(faktur.kd_sales), '') as salesman_code,
                            isnull(rtrim(faktur.nm_sales), '') as salesman_name,
                            isnull(faktur.tgl_faktur, '') as tanggal_faktur,
                            isnull(faktur.jatuh_tempo, '') as jatuh_tempo,
                            case
                                when datediff(day, getdate(), faktur.jatuh_tempo) <= 0 then 'red'
                                when datediff(day, getdate(), faktur.jatuh_tempo) > 1 and
                                    datediff(day, getdate(), faktur.jatuh_tempo) < 8 then 'yellow'
                            else 'green'
                            end as flag,
                            isnull(faktur.total_faktur, 0) as total_faktur,
                            isnull(faktur.terbayar_realisasi, 0) as terbayar_realisasi,
                            isnull(faktur.terbayar_belum_realisasi, 0) as terbayar_belum_realisasi,
                            isnull(faktur.total_pembayaran, 0) as total_pembayaran,
                            isnull(faktur.sisa_pembayaran, 0) as sisa_pembayaran,
                            isnull(terima.no_bpk, '') as nomor_bpk,
                            isnull(convert(varchar(10), terima.tanggal, 120), '') as tanggal_input,
                            iif(isnull(terima.tunai_giro, 'T')='T', 'TUNAI', 'GIRO') as jenis,
                            isnull(terima.nm_bank, '') as bank,
                            isnull(terima.no_giro, '') as nomor_giro,
                            isnull(terima.acc_bank, '') as account_bank,
                            isnull(terima.jt_tempo, '') as jatuh_tempo_giro,
                            isnull(convert(varchar(10), terima.tgl_real, 120), '') as tanggal_realisasi,
                            isnull(terima.realisasi, 0) as status_realisasi,
                            isnull(terimadtl.jumlah, 0) as jumlah_pembayaran
                    from
                    (
                        select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur, dealer.kd_dealer,
                                dealer.nm_dealer, faktur.kd_sales, salesman.nm_sales,
                                dateadd(day, isnull(dealer.jtp, 0),
                                    dateadd(day, isnull(faktur.umur_faktur, 0),
                                        cast(iif(isnull(faktur.tgl_sj, '')='',
                                            dateadd(day, 4, faktur.tgl_faktur),
                                            faktur.tgl_sj) as date))) as jatuh_tempo,
                                isnull(faktur.total, 0) as total_faktur,
                                isnull(faktur.terbayar_realisasi, 0) as terbayar_realisasi,
                                isnull(faktur.terbayar_belum_realisasi, 0) as terbayar_belum_realisasi,
                                isnull(faktur.terbayar_realisasi, 0) + isnull(faktur.terbayar_belum_realisasi, 0) as total_pembayaran,
                                isnull(faktur.total, 0) - (isnull(faktur.terbayar_realisasi, 0) + isnull(faktur.terbayar_belum_realisasi, 0)) as sisa_pembayaran
                        from
                        (
                            select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur,
                                    faktur.tgl_akhir_faktur, faktur.kd_sales, faktur.kd_dealer,
                                    faktur.total, faktur.terbayar, faktur.tgl_sj,
                                    faktur.umur_faktur, faktur.jtp_khusus,
                                    sum(iif(isnull(terima.realisasi, 0)=0, 0,
                                        isnull(terimadtl.jumlah, 0))) as terbayar_realisasi,
                                    sum(iif(isnull(terima.realisasi, 0)=1, 0,
                                        isnull(terimadtl.jumlah, 0))) as terbayar_belum_realisasi
                            from
                            (
                                select	faktur.companyid, faktur.no_faktur, faktur.tgl_faktur,
                                        faktur.tgl_akhir_faktur, faktur.kd_sales, faktur.kd_dealer,
                                        faktur.total, faktur.terbayar, faktur.tgl_sj,
                                        faktur.umur_faktur, faktur.jtp_khusus
                                from	faktur with (nolock)
                                where	faktur.no_faktur=? and faktur.companyid=?
                            )	faktur
                                    left join terimadtl with (nolock) on faktur.no_faktur=terimadtl.no_faktur
                                    left join terima with (nolock) on terimadtl.no_bpk=terima.no_bpk and
                                                    faktur.companyid=terima.companyid
                            group by faktur.companyid, faktur.no_faktur, faktur.tgl_faktur,
                                    faktur.tgl_akhir_faktur, faktur.kd_sales, faktur.kd_dealer,
                                    faktur.total, faktur.terbayar, faktur.tgl_sj,
                                    faktur.umur_faktur, faktur.jtp_khusus
                            having isnull(faktur.total, 0) >
                                    sum(iif(isnull(terima.realisasi, 0)=1, isnull(terimadtl.jumlah, 0), 0))
                        )	faktur
                                left join dealer with (nolock) on faktur.kd_dealer=dealer.kd_dealer and
                                            faktur.companyid=dealer.companyid
                                left join salesman with (nolock) on faktur.kd_sales=salesman.kd_sales and
                                            faktur.companyid=salesman.companyid
                    )	faktur
                            left join terimadtl with (nolock) on faktur.no_faktur=terimadtl.no_faktur
                            left join terima with (nolock) on terimadtl.no_bpk=terima.no_bpk and
                                        faktur.companyid=terima.companyid
                    order by terima.no_bpk asc";

            $result = DB::connection($request->get('divisi'))->select($sql, [ strtoupper(trim($request->get('nomor_faktur'))), strtoupper(trim($request->userlogin['companyid'])) ]);

            $jumlah_data = 0;
            $data_pembayaran = new Collection();
            $data_detail_pembayaran = new Collection();

            foreach($result as $data) {
                $jumlah_data = (double)$jumlah_data + 1;

                if(strtoupper(trim($data->nomor_bpk)) != '') {
                    $data_detail_pembayaran->push((object) [
                        'nomor_faktur'          => strtoupper(trim($data->nomor_faktur)),
                        'nomor_bpk'             => strtoupper(trim($data->nomor_bpk)),
                        'tanggal_input'         => trim($data->tanggal_input),
                        'jenis'                 => strtoupper(trim($data->jenis)),
                        'bank'                  => strtoupper(trim($data->bank)),
                        'nomor_giro'            => (strtoupper(trim($data->nomor_giro)) == '') ? '~' : strtoupper(trim($data->nomor_giro)),
                        'account_bank'          => (strtoupper(trim($data->account_bank)) == '') ? '~' : strtoupper(trim($data->account_bank)),
                        'jatuh_tempo_giro'      => trim($data->jatuh_tempo_giro),
                        'tanggal_realisasi'     => trim($data->tanggal_realisasi),
                        'flag'                  => ((int)$data->status_realisasi == 1) ? 'green' : 'red',
                        'status_realisasi'      => ((int)$data->status_realisasi == 1) ? 'Sudah Realisasi' : 'Belum Realisasi',
                        'jumlah_pembayaran'     => (double)$data->total_faktur,
                    ]);
                }

                $data_pembayaran->push((object) [
                    'nomor_faktur'      => strtoupper(trim($data->nomor_faktur)),
                    'dealer_code'       => strtoupper(trim($data->dealer_code)),
                    'dealer_name'       => strtoupper(trim($data->dealer_name)),
                    'salesman_code'     => strtoupper(trim($data->salesman_code)),
                    'salesman_name'     => strtoupper(trim($data->salesman_name)),
                    'tanggal_faktur'    => trim($data->tanggal_faktur),
                    'jatuh_tempo'       => trim($data->jatuh_tempo),
                    'flag'              => trim($data->flag),
                    'total_faktur'      => (double)$data->total_faktur,
                    'total_pembayaran'  => (double)$data->total_pembayaran,
                    'sisa_pembayaran'   => (double)$data->sisa_pembayaran,
                    'status'            => ((double)$data->terbayar_belum_realisasi > 0) ? 'Belum Realisasi' : ''
                ]);
            }

            if((double)$jumlah_data <= 0) {
                return ApiResponse::responseWarning('Tagihan sudah terbayar atau sudah lunas. (Menu ini hanya menampilkan data tagihan yang belum terbayar)');
            }

            $data_pembayaran_faktur = new Collection();
            $nomor_faktur = '';

            foreach($data_pembayaran as $result) {
                if(strtoupper(trim($nomor_faktur)) != strtoupper(trim($result->nomor_faktur))) {
                    $data_pembayaran_faktur->push((object) [
                        'nomor_faktur'      => strtoupper(trim($result->nomor_faktur)),
                        'dealer_code'       => strtoupper(trim($result->dealer_code)),
                        'dealer_name'       => strtoupper(trim($result->dealer_name)),
                        'salesman_code'     => strtoupper(trim($result->salesman_code)),
                        'salesman_name'     => strtoupper(trim($result->salesman_name)),
                        'tanggal_faktur'    => trim($result->tanggal_faktur),
                        'jatuh_tempo'       => trim($result->jatuh_tempo),
                        'flag'              => trim($result->flag),
                        'total_faktur'      => (double)$result->total_faktur,
                        'total_pembayaran'  => (double)$result->total_pembayaran,
                        'sisa_pembayaran'   => (double)$result->sisa_pembayaran,
                        'status'            => trim($result->status),
                        'detail'            => $data_detail_pembayaran
                                                ->where('nomor_faktur' , strtoupper(trim($result->nomor_faktur)))
                                                ->values()
                                                ->all()
                    ]);
                    $nomor_faktur = strtoupper(trim($result->nomor_faktur));
                }
            }

            $data = [
                'data'  => $data_pembayaran_faktur->first()
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

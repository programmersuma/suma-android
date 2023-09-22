<?php

namespace App\Http\Controllers\Api\Sales;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class SalesmanController extends Controller
{
    public function listSalesman(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
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

            $sql = DB::connection($request->get('divisi'))
                    ->table('salesman')->lock('with (nolock)')
                    ->selectRaw("isnull(salesman.id_sales, 0) as id,
                                isnull(salesman.kd_sales, '') as sales_code,
                                isnull(salesman.nm_sales, '') as name,
                                isnull(salesman.usertime, '') as created_at,
                                isnull(salesman.usertime, '') as update_at")
                    ->where('salesman.companyid', strtoupper(trim($request->userlogin['companyid'])));

            if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_KORSM') {
                $sql->where('salesman.spv', strtoupper(trim($kode_supervisor)));
            } elseif(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_SM') {
                $sql->where('salesman.kd_sales', strtoupper(trim($request->userlogin['user_id'])));
            }

            if(!empty($request->get('search')) && trim($request->get('search')) != '') {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('salesman.kd_sales', 'like', '%'.trim($request->get('search')).'%')
                            ->orWhere('salesman.nm_sales', 'like', '%'.trim($request->get('search')).'%');
                });
            }

            $sql = $sql->orderBy('salesman.kd_sales', 'asc')
                        ->paginate(15);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];

            $salesman = [];

            foreach($data_result as $data) {
                $salesman[] = [
                    'id'            => (int)$data->id,
                    'sales_code'    => strtoupper(trim($data->sales_code)),
                    'sales_name'    => strtoupper(trim($data->name)),
                    'created_at'    => strtoupper(trim($data->created_at)),
                    'update_at'     => strtoupper(trim($data->update_at))
                ];
            }

            $data_sales = new Collection();
            $data_sales->push((object) [
                'data' => [
                    'favorit'   => [],
                    'list'      => $salesman,
                ]
            ]);

            return ApiResponse::responseSuccess('success', $data_sales->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listSelectedSalesman(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
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

            $sql = DB::connection($request->get('divisi'))
                    ->table('salesman')->lock('with (nolock)')
                    ->selectRaw("isnull(salesman.id_sales, 0) as id,
                                isnull(salesman.kd_sales, '') as sales_code,
                                isnull(salesman.nm_sales, '') as name,
                                isnull(salesman.usertime, '') as created_at,
                                isnull(salesman.usertime, '') as update_at")
                    ->where('salesman.companyid', strtoupper(trim($request->userlogin['companyid'])));

            if(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_KORSM') {
                $sql->where('salesman.spv', strtoupper(trim($kode_supervisor)));
            } elseif(strtoupper(trim($request->userlogin['role_id'])) == 'MD_H3_SM') {
                $sql->where('salesman.kd_sales', strtoupper(trim($request->userlogin['user_id'])));
            }

            if(!empty($request->get('search')) && trim($request->get('search')) != '') {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('salesman.kd_sales', 'like', '%'.trim($request->get('search')).'%')
                            ->orWhere('salesman.nm_sales', 'like', '%'.trim($request->get('search')).'%');
                });
            }

            $result = $sql->orderBy('salesman.kd_sales', 'asc')->get();

            $salesman = [];

            foreach($result as $data) {
                $salesman[] = [
                    'id'            => (int)$data->id,
                    'sales_code'    => strtoupper(trim($data->sales_code)),
                    'name'          => strtoupper(trim($data->name)),
                    'created_at'    => strtoupper(trim($data->created_at)),
                    'update_at'     => strtoupper(trim($data->update_at))
                ];
            }

            $data_sales = new Collection();
            $data_sales->push((object) [
                'data' => $salesman
            ]);

            return ApiResponse::responseSuccess('success', $data_sales->first());
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listKoordinator(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Pilih data divisi terlebih dahulu');
            }

            $sql = DB::connection($request->get('divisi'))->table('superspv')->lock('with (nolock)')
                    ->selectRaw("isnull(superspv.id_spv, 0) as id,
                                isnull(superspv.kd_spv, '') as koordinator_code,
                                isnull(superspv.nm_spv, '') as name,
                                isnull(superspv.usertime, '') as created_at,
                                isnull(superspv.usertime, '') as update_at")
                    ->where('superspv.companyid', strtoupper(trim($request->userlogin['companyid'])));

            if(!empty($request->get('search')) && trim($request->get('search')) != '') {
                $sql->where(function($query) use ($request) {
                    return $query
                            ->where('superspv.kd_spv', 'like', '%'.trim($request->get('search')).'%')
                            ->orWhere('superspv.nm_spv', 'like', '%'.trim($request->get('search')).'%');
                });
            }

            $result = $sql->orderBy('superspv.kd_spv', 'asc')->get();

            $data_sales = [
                'data'  => $result
            ];

            return ApiResponse::responseSuccess('success', $data_sales);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

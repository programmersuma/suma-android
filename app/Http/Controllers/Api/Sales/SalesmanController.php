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

            /* ==================================================================== */
            /* Cek Role Id Supervisor */
            /* ==================================================================== */
            if(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql = DB::table('superspv')->lock('with (nolock)')
                        ->selectRaw("isnull(superspv.kd_spv, '') as kode_supervisor")
                        ->where('superspv.nm_spv', strtoupper(trim($user_id)))
                        ->where('superspv.companyid', strtoupper(trim($companyid)))
                        ->first();

                if(empty($sql->kode_supervisor) && trim($sql->kode_supervisor) == '') {
                    return ApiResponse::responseWarning('Kode supervisor anda tidak ditemukan, hubungi IT Programmer');
                }

                $kode_supervisor = strtoupper(trim($sql->kode_supervisor));
            }

            $sql = DB::table('salesman')->lock('with (nolock)')
                    ->selectRaw("isnull(salesman.id_sales, 0) as id,
                                isnull(salesman.kd_sales, '') as sales_code,
                                isnull(salesman.nm_sales, '') as name,
                                isnull(salesman.usertime, '') as created_at,
                                isnull(salesman.usertime, '') as update_at")
                    ->where('salesman.companyid', strtoupper(trim($companyid)));

            if(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql->where('salesman.spv', strtoupper(trim($kode_supervisor)));
            } elseif(strtoupper(trim($role_id)) == 'MD_H3_SM') {
                $sql->where('salesman.kd_sales', strtoupper(trim($user_id)));
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

            /* ==================================================================== */
            /* Cek Role Id Supervisor */
            /* ==================================================================== */
            if(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql = DB::table('superspv')->lock('with (nolock)')
                        ->selectRaw("isnull(superspv.kd_spv, '') as kode_supervisor")
                        ->where('superspv.nm_spv', strtoupper(trim($user_id)))
                        ->where('superspv.companyid', strtoupper(trim($companyid)))
                        ->first();

                if(empty($sql->kode_supervisor) && trim($sql->kode_supervisor) == '') {
                    return ApiResponse::responseWarning('Kode supervisor anda tidak ditemukan, hubungi IT Programmer');
                }

                $kode_supervisor = strtoupper(trim($sql->kode_supervisor));
            }

            $sql = DB::table('salesman')->lock('with (nolock)')
                    ->selectRaw("isnull(salesman.id_sales, 0) as id,
                                isnull(salesman.kd_sales, '') as sales_code,
                                isnull(salesman.nm_sales, '') as name,
                                isnull(salesman.usertime, '') as created_at,
                                isnull(salesman.usertime, '') as update_at")
                    ->where('salesman.companyid', strtoupper(trim($companyid)));

            if(strtoupper(trim($role_id)) == 'MD_H3_KORSM') {
                $sql->where('salesman.spv', strtoupper(trim($kode_supervisor)));
            } elseif(strtoupper(trim($role_id)) == 'MD_H3_SM') {
                $sql->where('salesman.kd_sales', strtoupper(trim($user_id)));
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

            $sql = DB::table('superspv')->lock('with (nolock)')
                    ->selectRaw("isnull(superspv.id_spv, 0) as id,
                                isnull(superspv.kd_spv, '') as koordinator_code,
                                isnull(superspv.nm_spv, '') as name,
                                isnull(superspv.usertime, '') as created_at,
                                isnull(superspv.usertime, '') as update_at")
                    ->where('superspv.companyid', strtoupper(trim($companyid)));

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

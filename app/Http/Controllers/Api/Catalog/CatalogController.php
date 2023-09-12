<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class CatalogController extends Controller {

    protected function listCatalog(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('catalog_part')->lock('with (nolock)')
                    ->selectRaw("isnull(id, 0) as id, isnull(name, '') as name,
                                isnull(icon, '') as icon, isnull(detail, '') as detail")
                    ->orderBy('id', 'asc')
                    ->get();

            $parts_catalog = new Collection;

            foreach($sql as $data) {
                $parts_catalog->push((object) [
                    'id'        => $data->id,
                    'name'      => trim($data->name),
                    'icon'      => trim($data->icon),
                    'detail'    => trim($data->detail)
                ]);
            }

            $sql = DB::connection($request->get('divisi'))
                    ->table('catalog')->lock('with (nolock)')
                    ->selectRaw("isnull(catalog.id, '') as id_catalog, isnull(catalog.name, '') as name_catalog,
                                isnull(catalog.icon, '') as icon_catalog, isnull(catalog.list, 0) as list_catalog,
                                isnull(catalog_dtl.id_detail, '') as id_detail, isnull(catalog_dtl.name, '') as name_detail,
                                isnull(catalog_dtl.url, '') as url_detail, isnull(catalog_dtl.size, 0) as size_detail,
                                isnull(catalog_dtl_list.name, '') as name_file, isnull(catalog_dtl_list.url, '') as url_file,
                                isnull(catalog_dtl_list.size, 0) as size_file")
                    ->leftJoin(DB::raw('catalog_dtl with (nolock)'), function($join) {
                        $join->on('catalog_dtl.id', '=', 'catalog.id');
                    })
                    ->leftJoin(DB::raw('catalog_dtl_list with (nolock)'), function($join) {
                        $join->on('catalog_dtl_list.id_detail', '=', 'catalog_dtl.id_detail');
                    })
                    ->orderByRaw("catalog.id asc,
                                catalog_dtl.name asc,
                                catalog_dtl_list.name asc")
                    ->get();

            $catalog_temp = new Collection();
            $catalog_detail_temp = new Collection();
            $catalog_file_temp = new Collection();

            foreach($sql as $data) {
                $catalog_temp->push((object) [
                    'id'    => $data->id_catalog,
                    'name'  => trim($data->name_catalog),
                    'icon'  => trim($data->icon_catalog),
                    'list'  => $data->list_catalog,
                ]);

                $catalog_detail_temp->push((object) [
                    'id_catalog'=> $data->id_catalog,
                    'id'    => $data->id_detail,
                    'name'  => trim($data->name_detail),
                    'url'   => trim($data->url_detail),
                    'size'  => $data->size_detail,
                ]);

                $catalog_file_temp->push((object) [
                    'id_catalog'=> $data->id_catalog,
                    'id'    => $data->id_detail,
                    'name'  => trim($data->name_file),
                    'size'  => trim($data->size_file),
                    'detail'=> trim($data->url_file)
                ]);
            }

            $data_catalog = new Collection();
            $data_catalog_detail = new Collection();
            $catalog_id = '';
            $catalog_detail_id = '';

            foreach($catalog_temp as $catalog) {
                if((int)$catalog->id != (int)$catalog_id) {
                    $catalog_list = $catalog_detail_temp
                                ->where('id_catalog', (int)$catalog->id)
                                ->values()
                                ->all();

                    foreach($catalog_list as $list_catalog) {
                        if(strtoupper(trim($catalog_detail_id)) != strtoupper(trim($list_catalog->id))) {
                            if((int)$catalog->list == 1) {
                                $data_catalog_detail->push((object) [
                                    'id_catalog'    => $list_catalog->id_catalog,
                                    'id'            => $list_catalog->id,
                                    'name'          => trim($list_catalog->name),
                                    'detail'        => $catalog_file_temp
                                                        ->where('id_catalog', (int)$catalog->id)
                                                        ->where('id', $list_catalog->id)
                                                        ->values()
                                                        ->all()
                                ]);

                            } else {
                                $data_catalog_detail->push((object) [
                                    'id_catalog'    => (int)$list_catalog->id_catalog,
                                    'id'            => (int)$list_catalog->id,
                                    'name'          => trim($list_catalog->name),
                                    'url'           => trim($list_catalog->url),
                                    'size'          => (int)$list_catalog->size
                                ]);
                            }

                            $catalog_detail_id = strtoupper(trim($list_catalog->id));
                        }
                    }

                    $data_catalog->push((object) [
                        'id'    => (int)$catalog->id,
                        'name'  => trim($catalog->name),
                        'icon'  => trim($catalog->icon),
                        'list'  => ((int)$catalog->list == 1) ? true : false,
                        'detail'=> $data_catalog_detail
                                    ->where('id_catalog', (int)$catalog->id)
                                    ->values()
                                    ->all()
                    ]);

                    $catalog_id = (int)$catalog->id;
                }
            }

            $data_result = [
                'part_catalogue' => $parts_catalog->first(),
                'data'           => $data_catalog
            ];

            return ApiResponse::responseSuccess('success', $data_result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

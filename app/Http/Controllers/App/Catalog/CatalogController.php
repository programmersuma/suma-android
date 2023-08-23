<?php

namespace App\Http\Controllers\App\Catalog;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CatalogController extends Controller {

    protected function listCatalog(Request $request) {
        try {
            $url = 'catalog/catalog';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'divisi'  => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

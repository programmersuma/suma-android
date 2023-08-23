<?php

namespace App\Http\Controllers\App\Sales;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalesmanController extends Controller
{
    public function listSalesman(Request $request) {
        try {
            $url = 'sales/list-salesman';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'          => $request->get('page'),
                'search'        => $request->get('search'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listSelectedSalesman(Request $request) {
        try {
            $url = 'sales/list-selected-salesman';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'search'        => $request->get('salesman'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listKoordinator(Request $request) {
        try {
            $url = 'sales/list-koordinator';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'search'        => $request->get('salesman'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

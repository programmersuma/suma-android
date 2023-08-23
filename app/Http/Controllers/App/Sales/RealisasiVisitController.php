<?php

namespace App\Http\Controllers\App\Sales;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RealisasiVisitController extends Controller {

    public function realisasiVisitDetail(Request $request) {
        try {
            $url = 'visit/realisasi-visit-detail';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'code'          => $request->get('code'),
                'start_date'    => $request->get('start_date'),
                'end_date'      => $request->get('end_date'),
                'page'          => $request->get('page'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function realisasiVisitSalesman(Request $request) {
        try {
            $url = 'visit/realisasi-visit-salesman';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'code'          => $request->get('code'),
                'dealer'        => $request->get('dealer'),
                'start_date'    => $request->get('start_date'),
                'end_date'      => $request->get('end_date'),
                'page'          => $request->get('page'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function realisasiVisitKoordinator(Request $request) {
        try {
            $url = 'visit/realisasi-visit-coordinator';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'code'          => $request->get('code'),
                'salesman'      => $request->get('salesman'),
                'dealer'        => $request->get('dealer'),
                'start_date'    => $request->get('start_date'),
                'end_date'      => $request->get('end_date'),
                'page'          => $request->get('page'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function realisasiVisitManager(Request $request) {
        try {
            $url = 'visit/realisasi-visit-manager';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'coordinator'   => $request->get('coordinator'),
                'salesman'      => $request->get('salesman'),
                'dealer'        => $request->get('dealer'),
                'start_date'    => $request->get('start_date'),
                'end_date'      => $request->get('end_date'),
                'page'          => $request->get('page'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

<?php

namespace App\Http\Controllers\App\Sales;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EfectivitasController extends Controller {

    public function efectivitasSalesman(Request $request) {
        try {
            $url = 'sales/efectivitas-salesman';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'dealer'        => $request->get('dealer'),
                'start_date'    => $request->get('start_date'),
                'end_date'      => $request->get('end_date'),
                'page'          => $request->get('page')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function efectivitasKoordinator(Request $request) {
        try {
            $url = 'sales/efectivitas-coordinator';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'salesman'      => $request->get('salesman'),
                'dealer'        => $request->get('dealer'),
                'start_date'    => $request->get('start_date'),
                'end_date'      => $request->get('end_date'),
                'page'          => $request->get('page')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function efectivitasManager(Request $request) {
        try {
            $url = 'sales/efectivitas-manager';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'coordinator'   => $request->get('coordinator'),
                'salesman'      => $request->get('salesman'),
                'dealer'        => $request->get('dealer'),
                'start_date'    => $request->get('start_date'),
                'end_date'      => $request->get('end_date'),
                'page'          => $request->get('page')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

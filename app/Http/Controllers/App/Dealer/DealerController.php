<?php

namespace App\Http\Controllers\App\Dealer;

use App\Helpers\ApiResponse;
use App\Helpers\ApiRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DealerController extends Controller {

    public function listDealer(Request $request) {
        try {
            $url = 'dealer/list-dealer';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'search'    => $request->get('search'),
                'divisi'    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listDealerSalesman(Request $request) {
        try {
            $url = 'dealer/list-dealer-salesman';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'salesman'  => $request->get('salesman'),
                'search'    => $request->get('search'),
                'divisi'    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listCompetitor(Request $request) {
        try {
            $url = 'dealer/list-competitor';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'divisi'    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function addCompetitor(Request $request) {
        try {
            $url = 'dealer/add-competitor';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'code_dealer'               => $request->get('code_dealer'),
                'id_role'                   => $request->get('id_role'),
                'name_competitor'           => $request->get('name_competitor'),
                'product'                   => $request->get('product'),
                'title_activity_competitor' => $request->get('title_activity_competitor'),
                'begin_effdate'             => $request->get('begin_effdate'),
                'end_effdate'               => $request->get('end_effdate'),
                'photo'                     => $request->get('photo'),
                'description'               => $request->get('description'),
                'divisi'                    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function addNewDealer(Request $request) {
        try {
            $url = 'dealer/add-new-dealer';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'name'          => $request->get('name'),
                'latlong'       => $request->get('latlong'),
                'address'       => $request->get('address'),
                'photo'         => $request->get('photo'),
                'phone'         => $request->get('phone'),
                'description'   => $request->get('description'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateDealerLocation(Request $request) {
        try {
            $url = 'dealer/update-dealer';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'kode_dealer'   => $request->get('kode_dealer'),
                'latlong'       => $request->get('latlong'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listKreditLimit(Request $request) {
        try {
            $url = 'dealer/list-kredit-limit';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listJatuhTempo(Request $request) {
        try {
            $url = 'dealer/list-jatuh-tempo';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'          => $request->get('page'),
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function detailJatuhTempo(Request $request) {
        try {
            $url = 'dealer/detail-jatuh-tempo';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_faktur'  => $request->get('nomor_faktur'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

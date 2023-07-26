<?php

namespace App\Http\Controllers\App\Sales;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function checkCheckInDashboard(Request $request) {
        try {
            $url = 'visit/check-checkin';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function dateVisit(Request $request) {
        try {
            $url = 'visit/date-visit';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function addVisit(Request $request) {
        try {
            $url = 'visit/add-visit';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'date'          => $request->get('date'),
                'latitude'      => $request->get('latitude'),
                'longitude'     => $request->get('longitude'),
                'keterangan'    => $request->get('keterangan')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function checkIn(Request $request) {
        try {
            $url = 'visit/checkin';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'code_visit'    => $request->get('code_visit'),
                'latitude'      => $request->get('latitude'),
                'longitude'     => $request->get('longitude'),
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function checkOut(Request $request) {
        try {
            $url = 'visit/checkin-checkout';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'code_visit'    => $request->get('code_visit'),
                'keterangan'    => $request->get('keterangan')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listPlanningVisit(Request $request) {
        try {
            $url = 'visit/list-date-visit';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'tanggal'   => $request->get('tanggal'),
                'salesman'  => $request->get('salesman'),
                'dealer'    => $request->get('dealer')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function addPlanningVisit(Request $request) {
        try {
            $url = 'visit/add-date-visit';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'tanggal'       => $request->get('tanggal'),
                'salesman'      => $request->get('salesman'),
                'dealer'        => $request->get('dealer'),
                'keterangan'    => $request->get('keterangan')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function deletePlanningVisit(Request $request) {
        try {
            $url = 'visit/delete-date-visit';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'visit_code'    => $request->get('visit_code')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

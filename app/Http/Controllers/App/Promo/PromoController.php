<?php

namespace App\Http\Controllers\App\Promo;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function listBrosur(Request $request) {
        try {
            $url = 'promo/brosure-promo';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'  => $request->get('ms_dealer_id')
            ];
            $response = ApiRequest::requestGet($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listPromoPart(Request $request) {
        try {
            $url = 'promo/part-promo';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'  => $request->get('ms_dealer_id')
            ];
            $response = ApiRequest::requestGet($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

<?php

namespace App\Http\Controllers\App\Tracking;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrackingController extends Controller {

    public function trackingOrder(Request $request) {
        try {
            $url = 'tracking/tracking-order';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'              => $request->get('page'),
                'ms_dealer_id'      => $request->get('ms_dealer_id'),
                'search'            => $request->get('dealer'),
                'sorting'           => $request->get('sorting'),
                'nomor_faktur'      => $request->get('nomor_faktur'),
                'part_number'       => $request->get('part_number'),
                'month'             => $request->get('month'),
                'status_bo'         => $request->get('status_bo'),
                'status_invoice'    => $request->get('status_invoice'),
                'status_shipping'   => $request->get('status_shipping')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function detailTracking(Request $request) {
        try {
            $url = 'tracking/detail-tracking';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'              => $request->get('page'),
                'tracking_item_id'  => $request->get('tracking_item_id'),
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

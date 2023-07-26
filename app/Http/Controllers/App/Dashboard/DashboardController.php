<?php

namespace App\Http\Controllers\App\Dashboard;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request) {
        try {
            $url = 'dashboard/index';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'category'      => $request->get('category'),
                'divisi'        => $request->get('divisi'),
                'year'          => $request->get('year'),
                'month'         => $request->get('month'),
                'item_group'    => $request->get('item_group'),
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'company'       => $request->get('company'),
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

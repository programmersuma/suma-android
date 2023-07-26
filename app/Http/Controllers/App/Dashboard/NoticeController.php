<?php

namespace App\Http\Controllers\App\Dashboard;

use App\Helpers\ApiRequest;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

class NoticeController extends Controller
{
    public function Notice(Request $request) {
        try {
            $url = 'dashboard/notice';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'  => $request->get('page')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}


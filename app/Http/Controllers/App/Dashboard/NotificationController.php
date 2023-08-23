<?php

namespace App\Http\Controllers\App\Dashboard;

use App\Helpers\ApiRequest;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function countNotification(Request $request) {
        try {
            $url = 'notification/count';
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

    public function listNotification(Request $request) {
        try {
            $url = 'notification/list';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'divisi'  => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function pushNotification(Request $request) {
        try {
            $url = 'notification/push';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'email'         => $request->get('email'),
                'type'          => $request->get('type'),
                'title'         => $request->get('title'),
                'message'       => $request->get('message'),
                'code'          => $request->get('code'),
                'user_process'  => $request->get('user_process'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}


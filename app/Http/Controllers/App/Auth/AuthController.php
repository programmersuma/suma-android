<?php

namespace app\Http\Controllers\App\Auth;

use App\Helpers\ApiRequest;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller {

    protected function oauthToken() {
        try {
            $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
            $url = 'oauth/token';
            $header = [ 'Authorization' => $credential ];
            $body = [];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function login(Request $request) {
        try {
            $url = 'auth/login';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'email'     => $request->get('email'),
                'password'  => $request->get('password')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function forgotPassword(Request $request) {
        try {
            $url = 'auth/forgot-password';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'email'     => $request->get('email')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function renewLogin(Request $request) {
        try {
            $url = 'oauth/renew';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'password'  => $request->get('password')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function profile(Request $request) {
        try {
            $url = 'profile/profile';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }

    }

    protected function changePassword(Request $request) {
        try {
            $url = 'profile/change-password';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'password'      => $request->get('password'),
                'new_password'  => $request->get('new_password')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function logout(Request $request) {
        try {
            $url = 'auth/logout';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

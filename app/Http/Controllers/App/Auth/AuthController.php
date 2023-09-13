<?php

namespace app\Http\Controllers\App\Auth;

use App\Helpers\ApiRequest;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller {

    protected function oauthToken(Request $request) {
        try {
            $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
            $url = 'oauth/token';
            $header = [ 'Authorization' => $credential ];
            $body = [
                'divisi'  => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function checkDivisi(Request $request) {
        try {
            $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
            $url = 'auth/check-divisi';
            $header = [ 'Authorization' => $credential ];
            $body = [
                'email'     => $request->get('email')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function listDivisi(Request $request) {
        try {
            $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
            $url = 'auth/list-divisi';
            $header = [ 'Authorization' => $credential ];
            $body = [
                'email'     => $request->get('email')
            ];
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
                'password'  => $request->get('password'),
                'regid'     => $request->get('regid'),
                'divisi'    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function forgotPassword(Request $request) {
        try {
            $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
            $url = 'auth/forgot-password';
            $header = [ 'Authorization' => $credential ];
            $body = [
                'email'  => $request->get('email')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function resetPassword($access_reset) {
        try {
            $decode_access = base64_decode(base64_decode(base64_decode(base64_decode(base64_decode($access_reset)))));
            $split_string = explode(':', $decode_access);

            $email = $split_string[0];
            $time = $split_string[1];
            $time_now = time();
            $status_access = 0;

            $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
            $url = 'auth/forgot-password-cek-status';
            $header = [ 'Authorization' => $credential ];
            $body = [
                'email'     => $email
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            $responseApi = json_decode($response, false);

            if((int)$time >= (int)$time_now) {
                $status_access = 1;
            } else {
                $status_access = 0;
            }

            return view('app.resetpassword', [
                'status'        => (object)[
                    'reset'     => (int)$responseApi->data->status_reset_password,
                    'access'    => (int)$status_access,
                ],
                'email'         => trim($email)
            ]);
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function submitResetPassword(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'email'             => 'required|string',
                'password'          => 'required|string',
                'password_verify'   => 'required|string',
            ]);

            if ($validate->fails()) {
                return redirect()->back()->withInput()->with('failed', 'Data email dan password baru harus terisi');
            }

            if(trim($request->get('password')) != trim($request->get('password_verify'))) {
                return redirect()->back()->withInput()->with('failed', 'Kolom password dan kolom verify password tidak sama');
            }

            if(Str::length($request->get('password')) < 6) {
                return redirect()->back()->withInput()->with('failed', 'Panjang password setidaknya harus memiliki 6 karakter0');
            }

            $credential = 'Basic '.base64_encode(trim(config('constants.api_key.api_username')).':'.trim(config('constants.app.api_key.api_password')));
            $url = 'auth/forgot-password-submit';
            $header = [ 'Authorization' => $credential ];
            $body = [
                'email'     => $request->get('email'),
                'password'  => $request->get('password')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            $responseApi = json_decode($response, false);

            if ($responseApi->status == 1) {
                return redirect()->back()->withInput()->with('success', $responseApi->message[0]);
            } else {
                return redirect()->back()->withInput()->with('failed', $responseApi->message[0]);
            }
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    protected function renewLogin(Request $request) {
        try {
            $url = 'oauth/renew';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'password'  => $request->get('password'),
                'divisi'    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
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
            $body = [
                'divisi'  => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
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
                'new_password'  => $request->get('new_password'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
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
            $body = [
                'divisi'  => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

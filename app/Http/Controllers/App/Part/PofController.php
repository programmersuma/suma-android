<?php

namespace App\Http\Controllers\App\Part;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PofController extends Controller
{
    public function listPofOrder(Request $request) {
        try {
            $url = 'pof/list-pof-order';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'          => $request->get('page'),
                'month'         => $request->get('month'),
                'salesman'      => $request->get('salesman'),
                'dealer'        => $request->get('dealer'),
                'part_number'   => $request->get('part_number')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function detailPofOrder(Request $request) {
        try {
            $url = 'pof/detail-pof-order';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof' => $request->get('nomor_pof')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function approveOrder(Request $request) {
        try {
            $url = 'pof/order-approve';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof' => $request->get('nomor_pof')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function cancelApprove(Request $request) {
        try {
            $url = 'pof/cancel-approve';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof' => $request->get('nomor_pof')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateTpc(Request $request) {
        try {
            $url = 'pof/update-tpc';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof' => $request->get('nomor_pof'),
                'tpc'       => $request->get('tpc')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateStatusBo(Request $request) {
        try {
            $url = 'pof/update-back-order';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof' => $request->get('nomor_pof'),
                'status_bo' => $request->get('status_bo')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateUmurPof(Request $request) {
        try {
            $url = 'pof/update-umur-pof';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof' => $request->get('nomor_pof'),
                'umur_pof'  => $request->get('umur_pof')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateKeterangan(Request $request) {
        try {
            $url = 'pof/update-keterangan';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof'     => $request->get('nomor_pof'),
                'keterangan'    => $request->get('keterangan')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateDiscHeader(Request $request) {
        try {
            $url = 'pof/update-disc-header';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof'     => $request->get('nomor_pof'),
                'discount'      => $request->get('discount')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function hapusPofOrder(Request $request) {
        try {
            $url = 'pof/hapus-pof-order';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof' => $request->get('nomor_pof')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateHargaDetail(Request $request) {
        try {
            $url = 'pof/update-harga';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof'     => $request->get('nomor_pof'),
                'part_number'   => $request->get('part_number'),
                'harga'         => $request->get('harga')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateQuantity(Request $request) {
        try {
            $url = 'pof/update-quantity';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof'     => $request->get('nomor_pof'),
                'part_number'   => $request->get('part_number'),
                'quantity'      => $request->get('quantity')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateDiscDetail(Request $request) {
        try {
            $url = 'pof/update-disc-detail';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof'     => $request->get('nomor_pof'),
                'part_number'   => $request->get('part_number'),
                'discount'      => $request->get('discount')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function hapusPartNumber(Request $request) {
        try {
            $url = 'pof/hapus-part-number';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'nomor_pof'     => $request->get('nomor_pof'),
                'part_number'   => $request->get('part_number')
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }
}

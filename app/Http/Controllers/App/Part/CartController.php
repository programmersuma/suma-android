<?php

namespace App\Http\Controllers\App\Part;

use App\Http\Controllers\App\Imports\ExcelCartController;
use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
//use PhpOffice\PhpSpreadsheet\IOFactory;

class CartController extends Controller {

    public function addCart(Request $request) {
        try {
            $url = 'cart/add-cart';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'item_part'     => $request->get('item_part'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function addToCart(Request $request) {
        try {
            $url = 'cart/add-to-cart';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'id_part'       => $request->get('id_part'),
                'quantity'      => $request->get('quantity'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listCart(Request $request) {
        try {
            $url = 'cart/list-cart';
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

    public function updateTpc(Request $request) {
        try {
            $url = 'cart/update-tpc';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'tpc'           => $request->get('tpc'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateQuantity(Request $request) {
        try {
            $url = 'cart/update-quantity';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'id_part_cart'  => $request->get('id_part_cart'),
                'quantity'      => $request->get('quantity'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateHarga(Request $request) {
        try {
            $url = 'cart/update-harga';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'id_part_cart'  => $request->get('id_part_cart'),
                'harga'         => $request->get('harga'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateDiscDetail(Request $request) {
        try {
            $url = 'cart/update-disc-detail';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'id_part_cart'  => $request->get('id_part_cart'),
                'discount'      => $request->get('discount'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function updateDiscHeader(Request $request) {
        try {
            $url = 'cart/update-disc-header';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'id_part_cart'  => $request->get('id_part_cart'),
                'discount'      => $request->get('discount'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function removeCart(Request $request) {
        try {
            $url = 'cart/remove-cart';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'id_part_cart'  => $request->get('id_part_cart'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function submitOrder(Request $request) {
        try {
            $url = 'cart/submit-order';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'bo'            => $request->get('bo'),
                'umur_faktur'   => $request->get('umur_faktur'),
                'keterangan'    => $request->get('keterangan'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function importExcel(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'file'          => 'required|mimes:xls,xlsx'
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('File yang dipilih harus berformat xls atau xlsx');
            }

            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $fileName = date('YmdHis').'='.Str::random(10);
                $request->file('file')->move('excel/upload', $fileName.'.xls');

                $fileExcel = config('constants.app.app_base_url').'/excel/upload/'.$fileName.'.xls';
                $dataExcel = Excel::toCollection(new ExcelCartController, $fileExcel);
                $data_imports = [];
                foreach($dataExcel[0] as $data) {
                    if(!empty($data['part_number'])) {
                        if(!empty($data['order'])) {
                            $data_imports[] = [
                                'part_number'   => strtoupper(trim($data['part_number'])),
                                'order'         => (int)$data['order'],
                            ];
                        }
                    }
                }
                if(empty($data_imports)) {
                    return ApiResponse::responseWarning('Header kolom excel harus mengandung kata part_number dan order');
                }
                $imports = json_encode($data_imports, true);

                File::delete($fileExcel);
                // ======================================================================================================
                // Send data to API
                // ======================================================================================================
                $url = 'cart/import-excel';
                $header = ['Authorization' => 'Bearer '.$request->get('token')];
                $body = [
                    'imports'       => $imports,
                    'ms_dealer_id'  => $request->get('ms_dealer_id'),
                    'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
                ];
                $response = ApiRequest::requestPost($url, $header, $body);

                return $response;
            } else {
                return ApiResponse::responseWarning('File yang dipilih harus berformat xls atau xlsx');
            }
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function importExcelResult(Request $request) {
        try {
            $url = 'cart/import-excel-result';
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
}

<?php

namespace App\Http\Controllers\App\Part;

use App\Helpers\ApiRequest;
use App\Helpers\ApiResponse;
use App\Exports\ReadyStock;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class PartController extends Controller {

    public function listMotorType(Request $request) {
        try {
            $url = 'part/list-motor-type';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'search'    => $request->get('search'),
                'divisi'  => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listItemClassProduk(Request $request) {
        try {
            $url = 'part/list-item-class-produk';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'search'    => $request->get('search'),
                'divisi'  => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listItemGroup(Request $request) {
        try {
            $url = 'part/list-item-group';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'          => $request->get('page'),
                'classproduk'   => $request->get('classproduk'),
                'search'        => $request->get('search'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listItemSubProduk(Request $request) {
        try {
            $url = 'part/list-item-sub-produk';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'produk'    => $request->get('produk'),
                'search'    => $request->get('search'),
                'divisi'    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function partSearch(Request $request) {
        try {
            $url = 'part/part-search';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'      => $request->get('ms_dealer_id'),
                'similarity'        => $request->get('similarity'),
                'part_number'       => $request->get('part_number'),
                'part_description'  => $request->get('part_description'),
                'item_group'        => $request->get('item_group'),
                'motor_type'        => $request->get('motor_type'),
                'sorting'           => $request->get('sorting'),
                'divisi'            => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listPartFavorite(Request $request) {
        try {
            $url = 'part/part-favorite';
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

    public function addPartFavorite(Request $request) {
        try {
            $url = 'part/add-favorite';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'  => $request->get('ms_dealer_id'),
                'id_part'       => $request->get('id_part'),
                'is_love'       => $request->get('is_love'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function listBackOrder(Request $request) {
        try {
            $url = 'part/list-back-order';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'          => $request->get('page'),
                'salesman'      => $request->get('salesman'),
                'dealer'        => $request->get('dealer'),
                'part_number'   => $request->get('is_love'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function checkStock(Request $request) {
        try {
            $url = 'part/check-stock';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'ms_dealer_id'      => $request->get('ms_dealer_id'),
                'similarity'        => $request->get('similarity'),
                'part_number'       => $request->get('part_number'),
                'part_description'  => $request->get('part_description'),
                'item_group'        => $request->get('item_group'),
                'motor_type'        => $request->get('motor_type'),
                'sorting'           => $request->get('sorting'),
                'divisi'            => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function skemaPembelian(Request $request) {
        try {
            $url = 'part/skema-pembelian';
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

    public function priceList(Request $request) {
        try {
            $url = 'part/price-list';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'      => $request->get('page'),
                'divisi'    => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $response = ApiRequest::requestPost($url, $header, $body);

            return $response;
        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }
    }

    public function readyStock(Request $request) {
        try {

        } catch (\Exception $exception) {
            return ApiResponse::responseWarning('Koneksi web hosting tidak terhubung ke server internal '.$exception);
        }

        $url = 'part/ready-stock';
            $header = ['Authorization' => 'Bearer '.$request->get('token')];
            $body = [
                'page'          => $request->get('page'),
                'class_produk'  => $request->get('class_produk'),
                'produk'        => $request->get('produk'),
                'sub_produk'    => $request->get('sub_produk'),
                'frg'           => $request->get('frg'),
                'status_stock'  => $request->get('status_stock'),
                'divisi'        => (strtoupper(trim($request->get('divisi'))) == 'HONDA') ? 'sqlsrv_honda' : 'sqlsrv_general'
            ];
            $responseApi = ApiRequest::requestPost($url, $header, $body);
            $statusApi = json_decode($responseApi)->status;
            $messageApi =  json_decode($responseApi)->message[0];

            if($statusApi == 1) {
                $data =  json_decode($responseApi)->data;
                $file = Excel::store(new ReadyStock($data, $request), '/excel/readystock/readystock.xlsx');

                $filePath = public_path().'/excel/readystock/readystock.xlsx';
                $file = new File($filePath);

                $uploadedFile = new UploadedFile(
                    $file->getRealPath(),
                    $file->getFilename(),
                    $file->getMimeType(),
                    null,
                    true
                );

                $destinationPath = public_path('excel/readystock/download');
                $uploadedFile->move($destinationPath, $uploadedFile->getClientOriginalName());
            } else {
                return $responseApi;
            }
    }
}

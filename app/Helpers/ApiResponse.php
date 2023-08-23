<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;

class ApiResponse
{
    // ==========================================================================================
    // API RESPONSE SUMA
    // ==========================================================================================
    protected static $response = [
        'message'   => null,
        'data'      => null
    ];

    public static function responseSuccess($message = null, $data = null)
    {
        return response()->json([
            'status'    => 1,
            'message'   => [
                $message
            ],
            'data'      => $data
        ], 200);
    }

    public static function responseWarning($message = null)
    {
        return response()->json([
            'status'    => 0,
            'message'   => [
                $message
            ]
        ], 200);
    }

    public static function responseError($user_id = null, $jenis = null, $menu = null, $proses = null,
        $error = null, $companyid = null) {

        // DB::connection($request->get('divisi'))->transaction(function () use ($request, $user_id, $jenis, $menu, $proses, $error, $companyid) {
        //     DB::connection($request->get('divisi'))->insert('exec SP_ErrorPMO_Simpan ?,?,?,?,?,?', [
        //         strtoupper(trim($user_id)), strtoupper(trim($jenis)), trim($menu), trim($proses), trim($error), strtoupper(trim($companyid))
        //     ]);
        // });

        return response()->json([
            'status'    => 0,
            'message'   => [
                trim($error)
            ]
        ], 200);
    }
}

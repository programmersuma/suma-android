<?php

namespace App\Helpers;

class ApiHelpers
{
    public static function ApiResponse($status, $message, $data)
    {
        if ($status == 1) {
            return response()->json([
                'status'    => 1,
                'message'   => [$message],
                'data'      => $data
            ], 200);
        } else {
            return response()->json([
                'status'    => 0,
                'message'   => [$message]
            ], 200);
        }
    }
}

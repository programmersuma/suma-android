<?php

namespace App\Helpers;
use Illuminate\Support\Facades\Http;

class ApiRequest
{
    public static function requestPost($request = null, $header = null, $body = null) {
        $url = trim(config('constants.app.app_api_url')).'/';
        $results = Http::withHeaders($header)
                    ->post($url.$request, $body)
                    ->body();

        return $results;
    }

    public static function requestGet($request = null, $header = null, $body = null) {
        $url = trim(config('constants.app.app_api_url')).'/';
        $results = Http::withHeaders($header)
                    ->get($url.$request, $body)
                    ->body();
        return $results;
    }
}

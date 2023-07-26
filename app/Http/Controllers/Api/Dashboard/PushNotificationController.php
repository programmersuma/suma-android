<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class PushNotificationController extends Controller
{
    public function pushNotifictaion(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'email'     => 'required',
                'title'     => 'required',
                'message'   => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Status BO dan umur faktur tidak boleh kosong');
            }

            $sql = DB::table('users')->lock('with (nolock)')
                    ->selectRaw("isnull(users.user_id, '') as user_id,
                                isnull(users.email, '') as email")
                    ->where('users.email', $request->get('email'))
                    ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('User Id tidak ditemukan');
            }

            $user_id = strtoupper(trim($sql->user_id));

            $sql = DB::table('user_api_sessions')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.user_id, '') as user_id,
                                isnull(user_api_sessions.fcm_id, '') as fcm_id")
                    ->where('user_api_sessions.user_id', strtoupper(trim($user_id)))
                    ->orderBy('user_api_sessions.id', 'desc')
                    ->first();

            if(empty($sql->user_id)) {
                return ApiResponse::responseWarning('Session Id tidak ditemukan, lakukan login ulang');
            }

            if(empty($sql->fcm_id) && trim($sql->fcm_id) == '') {
                return ApiResponse::responseWarning('Fcm Id tidak ditemukan');
            }
            $fcm_id_device = trim($sql->fcm_id);

            define('API_ACCESS_KEY', 'AAAAqiS-_OY:APA91bGb1OFkLDEuHsKeLFLLsVPObmk809cgD0iXAVxYqNot_XuPmNNaMDIWEtOYZZ03s4967V0Gw3q_ypx_K4H2ICJk_aKEO-USvoCoQY8SMRXPPzxojRsObbWSHzZN9mJ-mhdD9-5O');
            $regid = array($fcm_id_device);

            $message = [
                'title'     		=> trim($request->get('title')),
                'type'     			=> trim($request->get('title')),
                'message'     		=> trim($request->get('message')),
                'content_available' => true,
                'priority' 			=> 'high',
                'data' 			    => 'Test Data',
            ];
            $fields = [
                'registration_ids'  => $regid,
                'data'      		=> $message
            ];
            $headers = [
                'Authorization: key=' . API_ACCESS_KEY,
                'Content-Type: application/json'
            ];

            $ch = curl_init();
            curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
            curl_setopt( $ch,CURLOPT_POST, true );
            curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
            $chresult = curl_exec($ch );
            curl_close( $ch );

            $result = [
                'regid' => $regid,
                'fcm' 	=> json_decode($chresult)
            ];

            return ApiResponse::responseSuccess('success', $result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}

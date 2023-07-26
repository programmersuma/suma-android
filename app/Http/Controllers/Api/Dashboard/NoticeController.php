<?php

namespace App\Http\Controllers\Api\Dashboard;

use Illuminate\Http\Request;
use App\Helpers\ApiHelpers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class NoticeController extends Controller
{
    public function Notice(Request $request) {
        $token = $request->header('Authorization');
        $formatToken = explode(" ", $token);
        $session_id = trim($formatToken[1]);
        $sql = "select	user_api_sessions.user_id, user_api_sessions.id_user, users.jabatan, users.role_id,
                        users.email
                from
                (
                    select	user_api_sessions.user_id, user_api_sessions.id_user,
                            user_api_sessions.companyid
                    from    user_api_sessions
                    where	session_id=:session_id
                )	user_api_sessions
                        inner join users on user_api_sessions.user_id=users.user_id and users.companyid=user_api_sessions.companyid";

        $result_user = collect(DB::select($sql, [':session_id' => $session_id ]))->first();
        $email = trim($result_user->email);

        $sql = DB::table('notice')->where('email', $email)->orderBy('tanggal', 'desc')->get();
        $data_notice = [];

        $i = 0;
        foreach($sql as $result) {
            $data_information = [];

            $isi_notice = json_decode($result->isi_notice);
            foreach($isi_notice as $isi) {
                $data_information[] = [
                    'name'       => $isi->name,
                    'small_info' => $isi->small_info,
                    'description' => $isi->description
                ];
            }

            $i = $i + 1;
            $data_notice[] = [
                'id' 	        => (int)$result->id_notice,
                'type_notice'   => $result->type_notice,
                'page'          => $i,
                'information'   => collect($data_information)->first()
            ];

        }

        return ApiHelpers::ApiResponse(1, "success", collect($data_notice)->where('page', $request->get('page'))->values()->all());
    }

    public function paginate($items, $perPage = 2, $page = null, $options = []) {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}


<?php

namespace App\Http\Controllers\Api\Notification;

use App\Helpers\ApiResponse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    private $database;

    public function __construct() {
        $this->database = \App\Services\FirebaseService::connect();
    }

    public function countNotification(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('notification')->lock('with (nolock)')
                    ->selectRaw("count(notification.user_id) as count")
                    ->where('notification.user_id', strtoupper(trim($request->userlogin->user_id)))
                    ->whereRaw("isnull(notification.sts_read, 0)=0")
                    ->groupBy('notification.user_id')
                    ->first();

            $jumlah_notification = 0;

            if(!empty($sql->count)) {
                if((double)$sql->count > 0) {
                    $jumlah_notification = (double)$sql->count;
                }
            }

            $data = [
                'user_id'   => strtoupper(trim($request->userlogin->user_id)),
                'email'     => strtoupper(trim($request->userlogin->email)),
                'count'     => (double)$jumlah_notification
            ];

            return ApiResponse::responseSuccess('success', $data);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function listNotification(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'divisi'    => 'required|string',
            ]);

            if ($validate->fails()) {
                return ApiResponse::responseWarning('Data divisi tidak boleh kosong');
            }

            $sql = DB::connection($request->get('divisi'))->table('notification')->lock('with (nolock)')
                    ->selectRaw("isnull(notification.id, 0) as id,
                                format(notification.tanggal, 'dd MMMM yyyy') as tanggal,
                                format(notification.tanggal, 'HH:mm:ss') as jam,
                                isnull(notification.email, '') as email,
                                isnull(notification.notice, '') as notice,
                                isnull(notification.message, '') as message,
                                isnull(notification.type, '') as type,
                                isnull(notification.code, '') as code")
                    ->where('notification.user_id', strtoupper(trim($request->userlogin->user_id)))
                    ->where('notification.companyid', strtoupper(trim($request->userlogin->companyid)))
                    ->orderBy('notification.id', 'desc')
                    ->paginate(10);

            $result = collect($sql)->toArray();
            $data_result = $result['data'];
            $data_notification = new Collection();
            $data_pof = new Collection();
            $data_campaign = new Collection();
            $data_information = new Collection();

            $list_notification_id = '';
            $list_pof_code = '';
            $list_campaign_code = '';

            foreach($data_result as $data) {
                if(strtoupper(trim($list_notification_id)) == '') {
                    $list_notification_id = "'".strtoupper(trim($data->id))."'";
                } else {
                    $list_notification_id .= ",'".strtoupper(trim($data->id))."'";
                }

                if(strtoupper(trim($data->type)) == 'POF') {
                    if(strtoupper(trim($list_pof_code)) == '') {
                        $list_pof_code = "'".strtoupper(trim($data->code))."'";
                    } else {
                        $list_pof_code .= ",'".strtoupper(trim($data->code))."'";
                    }
                } elseif(strtoupper(trim($data->type)) == 'CAMPAIGN') {
                    if(strtoupper(trim($list_campaign_code)) == '') {
                        $list_campaign_code = "'".strtoupper(trim($data->code))."'";
                    } else {
                        $list_campaign_code .= ",'".strtoupper(trim($data->code))."'";
                    }
                } elseif(strtoupper(trim($data->type)) == 'INFORMATION') {
                    $data_information->push((object) [
                        'id'        => (int)$data->id,
                        'tanggal'   => trim($data->tanggal).' • '.trim($data->jam),
                        'type'      => strtoupper(trim($data->type)),
                        'notice'    => trim($data->notice),
                        'message'   => trim($data->message),
                    ]);
                }

                $data_notification->push((object) [
                    'id'        => (int)$data->id,
                    'tanggal'   => trim($data->tanggal).' • '.trim($data->jam),
                    'email'     => trim($data->email),
                    'notice'    => trim($data->notice),
                    'message'   => trim($data->message),
                    'type'      => strtoupper(trim($data->type)),
                    'code'      => strtoupper(trim($data->code))
                ]);
            }

            if(strtoupper(trim($list_pof_code)) != '') {
                $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                        ->selectRaw("isnull(pof.no_pof, '') as nomor_pof, isnull(pof.tgl_pof, '') as tanggal,
                                    isnull(pof.kd_sales, '') as kode_sales, isnull(salesman.nm_sales, '') as nama_sales,
                                    isnull(pof.kd_dealer, '') as kode_dealer, isnull(dealer.nm_dealer, '') as nama_dealer,
                                    isnull(pof.approve, 0) as status_approve, isnull(pof.total, 0) as total")
                        ->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                            $join->on('salesman.kd_sales', '=', 'pof.kd_sales')
                                ->on('salesman.companyid', '=', 'pof.companyid');
                        })
                        ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                            $join->on('dealer.kd_dealer', '=', 'pof.kd_dealer')
                                ->on('dealer.companyid', '=', 'pof.companyid');
                        })
                        ->whereRaw("pof.no_pof in (".strtoupper(trim($list_pof_code)).")")
                        ->where('pof.companyid', strtoupper(trim($request->userlogin->companyid)))
                        ->get();

                foreach($sql as $data) {
                    $data_pof->push((object) [
                        'nomor_pof'     => strtoupper(trim($data->nomor_pof)),
                        'tanggal'       => trim($data->tanggal),
                        'sales_code'    => strtoupper(trim($data->kode_sales)),
                        'sales_name'    => strtoupper(trim($data->nama_sales)),
                        'dealer_code'   => strtoupper(trim($data->kode_dealer)),
                        'dealer_name'   => strtoupper(trim($data->nama_dealer)),
                        'approve'       => (int)$data->status_approve,
                        'total'         => (double)$data->total,
                        'order_code'    => strtoupper(trim($data->nomor_pof)),
                    ]);
                }
            }

            if(strtoupper(trim($list_campaign_code)) != '') {
                $sql = DB::connection($request->get('divisi'))->table('camp')->lock('with (nolock)')
                        ->selectRaw("isnull(substring(camp.no_camp, 3, 3), 0) as id,
                                    isnull(camp.no_camp, '') as nomor_campaign,
                                    isnull(camp.nm_camp, '') as nama_campaign,
                                    isnull(camp.tgl_prd1, '') as tanggal_awal,
                                    isnull(camp.tgl_prd2, '') as tanggal_akhir,
                                    isnull(camp.picture, '') as picture")
                        ->whereRaw("camp.no_camp in (".strtoupper(trim($list_campaign_code)).")")
                        ->where('camp.companyid', strtoupper(trim($request->userlogin->companyid)))
                        ->get();

                foreach($sql as $data) {
                    $data_campaign->push((object) [
                        'id'            => (int)$data->id,
                        'title'         => strtoupper(trim($data->nama_campaign)),
                        'photo'         => (trim($data->picture) == '') ? 'https://suma-honda.id/assets/images/logo/bg_logo_suma.png' : trim($data->picture), // photo
                        'promo_start'   => trim($data->tanggal_awal),
                        'promo_end'     => trim($data->tanggal_akhir),
                        'content'       => strtoupper(trim($data->nama_campaign)),
                        'note'          => strtoupper(trim($data->nama_campaign)),
                        'code'          => strtoupper(trim($data->nomor_campaign))
                    ]);
                }
            }

            $data_result_notification = [];

            foreach($data_notification as $data) {
                if(strtoupper(trim($data->type)) == 'POF') {
                    if(!empty($data_pof->where('nomor_pof', strtoupper(trim($data->code)))->first())) {
                        $data_result_notification[] = [
                            'id'        => (int)$data->id,
                            'tanggal'   => trim($data->tanggal),
                            'email'     => trim($data->email),
                            'notice'    => trim($data->notice),
                            'message'   => trim($data->message),
                            'type'      => strtoupper(trim($data->type)),
                            'code'      => strtoupper(trim($data->code)),
                            'pof'       => $data_pof
                                            ->where('nomor_pof', strtoupper(trim($data->code)))
                                            ->first()
                        ];
                    } else {
                        $data_result_notification[] = [
                            'id'        => (int)$data->id,
                            'tanggal'   => trim($data->tanggal),
                            'email'     => trim($data->email),
                            'notice'    => trim($data->notice),
                            'message'   => trim($data->message),
                            'type'      => strtoupper(trim($data->type)),
                            'code'      => strtoupper(trim($data->code)),
                            'pof'       => [
                                'nomor_pof'     => strtoupper(trim($data->code)),
                                'tanggal'       => '-',
                                'sales_code'    => '-',
                                'sales_name'    => '-',
                                'dealer_code'   => '-',
                                'dealer_name'   => '-',
                                'approve'       => 0,
                                'total'         => 0,
                                'order_code'    => strtoupper(trim($data->code))
                            ]
                        ];
                    }

                } elseif(strtoupper(trim($data->type)) == 'CAMPAIGN') {
                    if(!empty($data_campaign->where('code', strtoupper(trim($data->code)))->first())) {
                        $data_result_notification[] = [
                            'id'        => (int)$data->id,
                            'tanggal'   => trim($data->tanggal),
                            'email'     => trim($data->email),
                            'notice'    => trim($data->notice),
                            'message'   => trim($data->message),
                            'type'      => strtoupper(trim($data->type)),
                            'code'      => strtoupper(trim($data->code)),
                            'campaign'  => $data_campaign
                                            ->where('code', strtoupper(trim($data->code)))
                                            ->first()
                        ];
                    } else {
                        $data_result_notification[] = [
                            'id'        => (int)$data->id,
                            'tanggal'   => trim($data->tanggal),
                            'email'     => trim($data->email),
                            'notice'    => trim($data->notice),
                            'message'   => trim($data->message),
                            'type'      => strtoupper(trim($data->type)),
                            'code'      => strtoupper(trim($data->code)),
                            'campaign'  => [
                                'id'            => 0,
                                'title'         => '-',
                                'photo'         => 'https://suma-honda.id/assets/images/logo/bg_logo_suma.png',
                                'promo_start'   => '-',
                                'promo_end'     => '-',
                                'content'       => '-',
                                'note'          => '-',
                                'code'          => '-'
                            ]
                        ];
                    }

                } elseif(strtoupper(trim($data->type)) == 'INFORMATION') {
                    if(!empty($data_information->where('id', strtoupper(trim($data->id)))->first())) {
                        $data_result_notification[] = [
                            'id'        => (int)$data->id,
                            'tanggal'   => trim($data->tanggal),
                            'email'     => trim($data->email),
                            'notice'    => trim($data->notice),
                            'message'   => trim($data->message),
                            'type'      => strtoupper(trim($data->type)),
                            'code'      => strtoupper(trim($data->code)),
                            'information' => $data_information
                                            ->where('id', strtoupper(trim($data->id)))
                                            ->first()
                        ];
                    } else {
                        $data_result_notification[] = [
                            'id'        => (int)$data->id,
                            'tanggal'   => trim($data->tanggal),
                            'email'     => trim($data->email),
                            'notice'    => trim($data->notice),
                            'message'   => trim($data->message),
                            'type'      => strtoupper(trim($data->type)),
                            'code'      => strtoupper(trim($data->code)),
                            'information' => [
                                'id'        => 0,
                                'tanggal'   => '-',
                                'type'      => '-',
                                'notice'    => '-',
                                'message'   => '-'
                            ]
                        ];
                    }
                }
            }

            if($list_notification_id != '') {
                DB::connection($request->get('divisi'))->transaction(function () use ($request, $list_notification_id) {
                    DB::connection($request->get('divisi'))->update("update notification set sts_read=1 where notification.id in (".trim($list_notification_id).")");
                });
            }

            return ApiResponse::responseSuccess('success', $data_result_notification);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }

    public function pushNotification(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                'email'         => 'required',
                'type'          => 'required',
                'title'         => 'required',
                'message'       => 'required',
                'user_process'  => 'required',
                'divisi'        => 'required'
            ]);

            if($validate->fails()) {
                return ApiResponse::responseWarning('Isi data divisi, email, type, dan message terlebih dahulu');
            }

            if(trim($request->get('code')) == '') {
                if(strtoupper(trim($request->get('type'))) == 'POF') {
                    return ApiResponse::responseWarning('Untuk type POF dan Campaign harus menyertakan kode nya');
                } elseif(strtoupper(trim($request->get('type'))) == 'CAMPAIGN') {
                    return ApiResponse::responseWarning('Untuk type POF dan Campaign harus menyertakan kode nya');
                }
            }

            $sql = DB::connection($request->get('divisi'))->table('users')->lock('with (nolock)')
                    ->selectRaw("isnull(user_api_sessions.companyid, '') as companyid,
                                isnull(user_api_sessions.id, 0) as id,
                                isnull(users.id, '') as id_user,
                                isnull(users.user_id, '') as user_id,
                                isnull(users.email, '') as email,
                                isnull(user_api_sessions.session_id, '') as session_id,
                                isnull(user_api_sessions.fcm_id, '') as fcm_id,
                                iif(isnull(company.kd_file, '')='A', 'HONDA', 'FDR') as divisi")
                    ->leftJoin(DB::raw('company with (nolock)'), function($join) {
                        $join->on('company.companyid', '=', 'users.companyid');
                    })
                    ->leftJoin(DB::raw('user_api_sessions with (nolock)'), function($join) {
                        $join->on('user_api_sessions.user_id', '=', 'users.user_id')
                            ->on('user_api_sessions.companyid', '=', 'users.companyid');
                    })
                    ->where('users.email', trim($request->get('email')))
                    ->orderBy('user_api_sessions.id', 'desc')
                    ->first();

            if(empty($sql->fcm_id) && trim($sql->fcm_id) == '') {
                return ApiResponse::responseWarning('Fcm Id tidak ditemukan');
            }

            $companyid_received = strtoupper(trim($sql->companyid));
            $id_user_received = (int)$sql->id_user;
            $user_id_received = strtoupper(trim($sql->user_id));
            $user_email_received = trim($sql->email);
            $fcm_id_device = trim($sql->fcm_id);
            $registration_id = array($fcm_id_device);

            if(strtoupper(trim($request->get('type'))) == 'POF') {
                $kode_notification = strtoupper(trim($request->get('code')));
            } elseif(strtoupper(trim($request->get('type'))) == 'CAMPAIGN') {
                $kode_notification = strtoupper(trim($request->get('code')));
            } else {
                $kode_notification = trim(trim($id_user_received).'NOTIF'.date('YmdHis'));
            }

            DB::connection($request->get('divisi'))->transaction(function () use ($request, $kode_notification, $user_id_received,
                                                                        $user_email_received, $companyid_received) {
                DB::connection($request->get('divisi'))
                    ->insert('exec SP_Notification_Simpan ?,?,?,?,?,?,?,?,?', [
                        strtoupper(trim($kode_notification)), strtoupper(trim($user_id_received)), trim($user_email_received),
                        trim($request->get('title')), trim($request->get('message')), trim($request->get('type')),
                        strtoupper(trim($request->get('code'))), strtoupper(trim($companyid_received)),
                        strtoupper(trim($request->get('user_process')))
                    ]);
            });

            $data_content = [];
            // ========================================================================================================
            // GET DATA DETAIL CONTENT
            // ========================================================================================================
            if(strtoupper(trim($request->get('type'))) == 'POF') {
                $sql = DB::connection($request->get('divisi'))->table('pof')->lock('with (nolock)')
                        ->selectRaw("isnull(pof.no_pof, '') as nomor_pof, isnull(pof.tgl_pof, '') as tanggal,
                                    isnull(pof.kd_sales, '') as kode_sales, isnull(salesman.nm_sales, '') as nama_sales,
                                    isnull(pof.kd_dealer, '') as kode_dealer, isnull(dealer.nm_dealer, '') as nama_dealer,
                                    isnull(pof.approve, 0) as status_approve, isnull(pof.total, 0) as total")
                        ->leftJoin(DB::raw('salesman with (nolock)'), function($join) {
                            $join->on('salesman.kd_sales', '=', 'pof.kd_sales')
                                ->on('salesman.companyid', '=', 'pof.companyid');
                        })
                        ->leftJoin(DB::raw('dealer with (nolock)'), function($join) {
                            $join->on('dealer.kd_dealer', '=', 'pof.kd_dealer')
                                ->on('dealer.companyid', '=', 'pof.companyid');
                        })
                        ->where('pof.no_pof', strtoupper(trim($request->get('code'))))
                        ->first();

                if(empty($sql->nomor_pof)) {
                    $data_content = [
                        'nomor_pof'     => strtoupper(trim($request->get('code'))),
                        'tanggal'       => '-',
                        'sales_code'    => '-',
                        'sales_name'    => '-',
                        'dealer_code'   => '-',
                        'dealer_name'   => '-',
                        'approve'       => 0,
                        'total'         => 0,
                        'order_code'    => strtoupper(trim($request->get('code'))),
                    ];
                } else {
                    $data_content = [
                        'nomor_pof'     => strtoupper(trim($sql->nomor_pof)),
                        'tanggal'       => trim($sql->tanggal),
                        'sales_code'    => strtoupper(trim($sql->kode_sales)),
                        'sales_name'    => strtoupper(trim($sql->nama_sales)),
                        'dealer_code'   => strtoupper(trim($sql->kode_dealer)),
                        'dealer_name'   => strtoupper(trim($sql->nama_dealer)),
                        'approve'       => (int)$sql->status_approve,
                        'total'         => (double)$sql->total,
                        'order_code'    => strtoupper(trim($sql->nomor_pof)),
                    ];
                }
            } elseif(strtoupper(trim($request->get('type'))) == 'CAMPAIGN') {
                $sql = DB::connection($request->get('divisi'))->table('camp')->lock('with (nolock)')
                        ->selectRaw("isnull(substring(camp.no_camp, 3, 3), 0) as id,
                                    isnull(camp.no_camp, '') as nomor_campaign,
                                    isnull(camp.nm_camp, '') as nama_campaign,
                                    format(cast(convert(varchar(10), isnull(camp.tgl_prd1, ''), 120) as date), 'dd MMMM yyyy') as tanggal_awal,
                                    format(cast(convert(varchar(10), isnull(camp.tgl_prd2, ''), 120) as date), 'dd MMMM yyyy') as tanggal_akhir,
                                    isnull(camp.picture, '') as picture")
                        ->where('camp.no_camp', strtoupper(trim($request->get('code'))))
                        ->first();

                if(empty($sql->nomor_campaign)) {
                    $data_content = [
                        'id'            => 0,
                        'title'         => '-',
                        'photo'         => '-',
                        'promo_start'   => '-',
                        'promo_end'     => '-',
                        'content'       => '-',
                        'note'          => '-',
                        'code'          => ''
                    ];
                } else {
                    $data_content = [
                        'id'            => (int)$sql->id,
                        'title'         => strtoupper(trim($sql->nama_campaign)),
                        'photo'         => (trim($sql->picture) == '') ? 'https://suma-honda.id/assets/images/logo/bg_logo_suma.png' : trim($sql->picture),
                        'promo_start'   => trim($sql->tanggal_awal),
                        'promo_end'     => trim($sql->tanggal_akhir),
                        'content'       => strtoupper(trim($sql->nama_campaign)),
                        'note'          => strtoupper(trim($sql->nama_campaign)),
                        'code'          => strtoupper(trim($sql->nomor_campaign))
                    ];
                }
            } else {
                $sql = DB::connection($request->get('divisi'))->table('notification')->lock('with (nolock)')
                        ->selectRaw("isnull(notification.id, 0) as id,
                                    format(notification.tanggal, 'dd MMMM yyyy') as tanggal,
                                    format(notification.tanggal, 'HH:mm:ss') as jam,
                                    isnull(notification.email, '') as email,
                                    isnull(notification.notice, '') as notice,
                                    isnull(notification.message, '') as message,
                                    isnull(notification.type, '') as type,
                                    isnull(notification.code, '') as code")
                        ->where('notification.user_id', strtoupper(trim($user_id_received)))
                        ->where('notification.companyid', strtoupper(trim($companyid_received)))
                        ->orderBy('notification.id', 'desc')
                        ->first();

                if(empty($sql->id)) {
                    $data_content = [
                        'tanggal'   => trim($sql->tanggal).' • '.trim($sql->jam),
                        'type'      => strtoupper(trim($sql->type)),
                        'notice'    => trim($sql->notice),
                        'message'   => trim($sql->message)
                    ];
                } else {
                    $data_content = [
                        'tanggal'   => '-',
                        'type'      => '-',
                        'notice'    => '-',
                        'message'   => '-'
                    ];
                }
            }
            // ========================================================================================================
            // END GET DATA DETAIL CONTENT
            // ========================================================================================================

            $message = [
                'title'     		=> trim($request->get('title')),
                'type'     			=> trim($request->get('type')),
                'message'     		=> trim($request->get('message')),
                'content_available' => true,
                'priority' 			=> 'high',
                'data' 			    => $data_content,
            ];
            $fields = [
                'registration_ids'  => $registration_id,
                'data'      		=> $message
            ];
            $headers = [
                'Authorization: key=AAAAqiS-_OY:APA91bGb1OFkLDEuHsKeLFLLsVPObmk809cgD0iXAVxYqNot_XuPmNNaMDIWEtOYZZ03s4967V0Gw3q_ypx_K4H2ICJk_aKEO-USvoCoQY8SMRXPPzxojRsObbWSHzZN9mJ-mhdD9-5O',
                'Content-Type: application/json'
            ];

            $curlInit = curl_init();
            curl_setopt($curlInit, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
            curl_setopt($curlInit, CURLOPT_POST, true);
            curl_setopt($curlInit, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curlInit, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlInit, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlInit, CURLOPT_POSTFIELDS, json_encode($fields));

            $curlExec = curl_exec($curlInit);
            curl_close($curlInit);

            $result = [
                'regid' => $registration_id,
                'fcm' 	=> json_decode($curlExec)
            ];

            return ApiResponse::responseSuccess('success', $result);
        } catch (\Exception $exception) {
            return ApiResponse::responseError($request->ip(), 'API', Route::getCurrentRoute()->action['controller'],
                $request->route()->getActionMethod(), $exception->getMessage(), 'XXX');
        }
    }
}


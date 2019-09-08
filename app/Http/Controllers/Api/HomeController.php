<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditProces;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    /**
     * 待处理事项统计
     * @Author: qinjintian
     * @Date:   2019-03-06
     */
    public function badge()
    {
        // 我审核的数量
        $my_review_count = (new AuditProces)->getVerifyCount();
        // 未读的提醒信息数量
        $my_unread_notified_count = (new Notification)->unreadNotified(['my_unread_notified' => 1]);

        return [
            'code' => 1,
            'message' => 'success',
            'data' => [
                'my_review_count' => $my_review_count,
                'my_unread_notified_count' => $my_unread_notified_count,
            ]
        ];
    }

    /**
     * 未读的消息、只获取前最新的10条
     * @Author: qinjintian
     * @Date:   2018-12-19
     */
    public function unreadNotified()
    {
        $notifieds = Notification::with(['operator' => function ($query) {
            return $query->select(['username', 'realname', 'id']);
        }])->where([
            ['status', '=', 0],
            ['user_id', '=', auth()->id()]
        ])->orderBy('id', 'DESC')->skip(0)
            ->take(10)
            ->get()->toArray();

        foreach ($notifieds as $key => $val) {
            $notifieds[$key]['api_params'] = $notifieds[$key]['api_params'] ? unserialize($val['api_params']) : [];
            if (empty($val['operator'])) {
                $notifieds[$key]['operator'] = ['id' => 0, 'username' => 'system', 'realname' => '系统'];
            }
        }
        return ['code' => 1, 'message' => '未读消息获取成功', 'data' => $notifieds];
    }

    /**
     * 阅读消息提醒
     * @Author: qinjintian
     * @Date:   2018-12-19
     */
    public function readNotified()
    {
        $id = request()->input('id', 0);
        $notification = Notification::where([['id', '=', $id], ['user_id', '=', auth()->id()]])->first();
        if (!$notification) {
            return ['code' => 0, 'message' => 'Soory，不允许阅读他人的消息内容'];
        }
        if ($notification->status == 0) {
            $notification->status = 1;
            $notification->read_time = date('Y-m-d H:i:s', time());
            $notification->save();
        }
        return ['code' => 1, 'message' => 'success'];
    }

    /**
     * 生成二维码
     * @Author: qinjintian
     * @Date:   2019-03-12
     */
    public function qrcode()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 生成微信授权二维码
                $wechat_redmm_qrcode = base64EncodeImage(public_path('static/wechat.png'));
                $user_info = auth()->user();
                $data = [];
                $data['wechat_redmm_qrcode'] = [
                    'text' => '扫描加我微信',
                    'image' => $wechat_redmm_qrcode
                ];
                /*if (empty($user_info['wechat_open_id'])) {
                    $wechat_authorize_url = request()->root() . '/api/wechat?authorize_code=' . encrypt(auth()->id()) . '';
                    $wechat_authorize_qrcode = 'data:image/png;base64,' . base64_encode(\QrCode::format('png')->size(200)->generate($wechat_authorize_url));
                    $data['wechat_authorize_qrcode'] = [
                        'text' => '扫描授权公众号，获取OA消息提醒',
                        'image' => $wechat_authorize_qrcode
                    ];
                }*/
                $data['wechat_authorize_qrcode'] = [
                    'text' => '扫描授权公众号，获取OA消息提醒',
                    'image' => $wechat_redmm_qrcode
                ];
                return ['code' => 1, 'message' => '生成微信授权二维码成功', 'data' => $data];
                break;
            case 2:
                // 微信二维码不再提醒
                $user = User::where('id', auth()->id())->first();
                if (empty($user->not_follow)) {
                    $user->not_follow = 1; // 不再提示
                    $user->save();
                }
                return ['code' => 1, 'message' => '操作成功，以后不再提示'];
                break;
        }
    }
}

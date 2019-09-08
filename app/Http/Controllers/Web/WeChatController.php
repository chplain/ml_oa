<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use App\Handles\WeChat;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class WeChatController extends Controller
{
    // 微信公众号开发校验token
    public function serve1()
    {
        $app = app('wechat.official_account');
        $app->server->push(function($message){
            return "欢迎关注 overtrue！";
        });
        return $app->server->serve();
        
        // $signature = request()->input('signature');
        // $timestamp = request()->input('timestamp');
        // $nonce = request()->input('nonce');
        // $echostr = request()->input('echostr');
        // $token = "qinjintian";
        //
        // // 1、将token、timestamp、nonce三个参数进行字典序排序
        // $tmpArr = [$nonce, $token, $timestamp];
        // sort($tmpArr, SORT_STRING);
        // // 2、将三个参数字符串拼接成一个字符串进行sha1加密
        // $str = implode($tmpArr);
        // $sign = sha1($str);
        // // 3、开发者获得加密后的字符串可与signature对比，标识该请求来源于微信
        // if ($sign == $signature) {
        //     echo $echostr;
        // }
    }

    /**
     * 微信用户授权回调
     * @Author: qinjintian
     * @Date:   2019-03-12
     */
    public function serve()
    {
        $code = request()->input('code');
        $authorize_code = request()->input('authorize_code');
        try {
            $user_id = empty($authorize_code) ? 0 : decrypt($authorize_code);
        } catch (\Exception $e) {
            return view('error', ['message' => '微信授权二维码链接错误，请检查']);
        }

        if (!$user_id) {
            return view('error', ['message' => '非法链接，不允许访问，请检查']);
        }

        if (!isWeChat()) {
            return view('error', ['message' => '仅支持在微信中扫描授权，请检查']);
        }

        $user = User::where('id', $user_id)->first();
        if (!$user) {
            return view('error', ['message' => '非法链接，系统中没有这个用户，请检查']);
        }

        if (!empty($user['wechat_open_id'])) {
            return view('error', ['message' => '你已经微信授权过，无需重复授权']);
        }

        if (!$code) {
            // 通过授权获取code
            $redirect_url = request()->fullUrl();
            $jump_url = WeChat::getAuthorizeUrl($redirect_url);
            header("Location: $jump_url");
            exit;
        } else {
            // 获取微信用户信息
            $wechat_access_token = WeChat::getAccessToken(); // 获取微信token
            $user_authorizes = WeChat::getUserAuthorize($code, $wechat_access_token);
            $openid = $user_authorizes['openid'] ?? '';
            if (!$openid) {
                return view('error', ['message' => '授权登录失败，请稍后重试']);
            }
            $user->wechat_open_id = $openid;
            $result = $user->save();
            if ($result) {
                return view('error', ['message' => '微信授权成功，关注公众号后即可接收OA消息提醒']);
            } else {
                return view('error', ['message' => '微信授权失败，请稍后重试']);
            }
        }
    }
}

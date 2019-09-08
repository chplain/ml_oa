<?php

namespace App\Handles;

class WeChat
{
    // 公众号账号信息
    private static $appid = 'wx9384984f57dfec5f'; // 开发者账号
    private static $appsecret = '1ab77418837ddec81d990551c68912d7'; // 开发者密码

    // 手机微信授权地址
    public static function getAuthorizeUrl($redirect_url)
    {
        $redirect_url = urlencode($redirect_url);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . self::$appid . "&redirect_uri={$redirect_url}&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect";
    }

    // 获取TOKEN
    public static function getAccessToken()
    {
        $wechat_access_token = cache()->store('file')->get('wechat_access_token');
        if (!empty($wechat_access_token) && time() - $wechat_access_token['store_time'] < 6000) {
            return $wechat_access_token['access_token'];
        }

        $urla = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . self::$appid . "&secret=" . self::$appsecret;

        $http = new \GuzzleHttp\Client;
        $response = $http->get($urla);
        $result = json_decode((string)$response->getBody(), true);
        $access_token = $result['access_token'];
        $wechat_access_token = [
            'access_token' => $access_token,
            'store_time' => time()
        ];
        cache()->store('file')->add('wechat_access_token', $wechat_access_token, 120); // 缓存2小时
        return $access_token;
    }

    /**
     * 获取用户网页授权access_token,包括openid
     * @param  string $code 微信授权code
     * @return array
     */
    public static function getUserAuthorize($code)
    {
        $access_token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . self::$appid . "&secret=" . self::$appsecret . "&code={$code}&grant_type=authorization_code";
        $http = new \GuzzleHttp\Client;
        $response = $http->get($access_token_url);
        $access_token_array = json_decode((string)$response->getBody(), true);
        return $access_token_array;
    }

    /**
     * 发送自定义的模板消息
     * @param  string $topcolor 模板内容字体颜色，不填默认为黑色
     * @return array
     */
    public static function pushMessage($openid, $template_data, $template_id = '', $jump_url = '', $topcolor = '#0000')
    {
        $template = [
            'touser' => $openid,
            'template_id' => $template_id,
            'url' => $jump_url,
            'topcolor' => $topcolor,
            'data' => $template_data
        ];
		$json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . self::getAccessToken();
        $http = new \GuzzleHttp\Client;
        $response = $http->post($url, [
            'form_params' => urldecode($json_template),
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $result_data = json_decode((string)$response->getBody(), true);
        return $result_data;
    }
}

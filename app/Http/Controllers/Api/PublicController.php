<?php

namespace App\Http\Controllers\Api;

use App\Models\TemplateLibrary;
use App\Models\User;
use App\Http\Controllers\Controller;
use Mail;
use App\Jobs\SendEmail;
use Illuminate\Contracts\Mail\Mailer;
class PublicController extends Controller
{
    /**
     * 用户登录
     * @Author: qinjintian
     * @Date:   2018-09-07
     */
    public function login()
    {
        // 查找用户是否存在
        $username = request('username');
        $password = request('password');
        $login_status = auth()->attempt(
            [
                'username' => $username,
                'password' => $password
            ]
        );

        $user = auth()->user();
        if (!$user) {
            return response()->json(['code' => 0, 'message' => '用户不存在或密码错误，请检查']);
        }

        if ($user->status == 0) {
            return response()->json(['code' => 0, 'message' => '此账号已被禁用，请联系超级管理员启用']);
        }

        $data = [];
        // Passport 授权认证方式，1 => Laravel个人访问客户端， 2 => Laravel密码授予客户
        if (env('PASSPORT_TYPE') == 1) {
            $token_data = $this->personalAccessTokens($user);
        } else {
            $token_data = $this->passwordAccessTokens([
                'grant_type' => 'password',
                'client_id' => env('PASSWORD_CLIENT_ID'),
                'client_secret' => env('PASSWORD_CLIENT_SECRET'),
                'username' => $username,
                'password' => $password,
                'scope' => '*',
            ]);
        }

        if ($token_data['code'] === 0) {
            return response()->json(['code' => 0, 'message' => $token_data['message']]);
        }

        $data['token_type'] = $token_data['data']['token_type'];
        $data['access_token'] = $token_data['data']['access_token'];
        if (env('PASSPORT_TYPE') <> 1) {
            $data['refresh_token'] = $token_data['data']['refresh_token'];
            $data['expires_in'] = $token_data['data']['expires_in'];
        }

        $user_base_data = [
            'id' => $user->id,
            'username' => $user->username,
            'realname' => $user->realname,
            'sex' => $user->sex,
            'sex_cn' => $user->sex == 1 ? '男' : '女',
            'age' => $user->age,
            'ethnic' => $user->ethnic,
            'birthday' => $user->birthday,
            'current_home_address' => $user->current_home_address,
            'census_address' => $user->census_address,
            'if_follow_wechat' => $user->wechat_open_id || $user->not_follow ? 1 : 0,
            'avatar' => asset($user->avatar),
            'last_login_ip' => $user->last_login_ip,
            'last_login_time' => $user->last_login_time,
        ];
        $data['user'] = $user_base_data;
        $data['dept'] = $user->dept()->first();
        $data['rank'] = $user->rank()->first();
        $data['position'] = $user->position()->first();
        $data['user_has_roles'] = $user->roles()->get();
        $holiday = new \App\Models\Holiday;
        $if_exits = $holiday->where('year', date('Y'))->first();
        if (empty($if_exits)) {
            // 把一年的节假日都存到数据库
            $rss1 = $holiday->addHolidays();//添加节假日
        }

        // 更新用户表
        $cache = $this->getUserCache($user->id);
        $update = [];
        $update['last_login_time'] = date('Y-m-d H:i:s');
        $update['last_login_ip'] = $cache['login_ip'];
        $update['last_login_addr'] = $cache['login_addr'];
        User::where('id', $user->id)->update($update);

        // 检查并创建当前月模板库文件夹
        TemplateLibrary::createTemplateFolder();
        systemLog('登录', '用户登录', 2);
        return response()->json(['code' => 1, 'message' => '登录成功', 'data' => $data]);
    }

    /**
     * passport授权令牌
     * @Author: qinjintian
     * @Date:   2018-11-14
     */
    private function passwordAccessTokens(array $params = [])
    {
        try {
            $url = request()->root() . '/oauth/token';
            $http = new \GuzzleHttp\Client;
            $response = $http->post($url, [
                'form_params' => $params,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $token = json_decode((string)$response->getBody(), true);
            return [
                'code' => 1,
                'message' => 'success',
                'data' => [
                    'token_type' => $token['token_type'],
                    'expires_in' => $token['expires_in'],
                    'access_token' => $token['access_token'],
                    'refresh_token' => $token['refresh_token'],
                ]
            ];
        } catch (\Exception $e) {
            return ['code' => 0, 'message' => '请求失败，服务器错误'];
        }
    }

    /**
     * Laravel个人访问客户端
     * @Author: qinjintian
     * @Date:   2018-11-14
     */
    private function personalAccessTokens($user)
    {
        try {
            $access_data = $user->createToken($user->username, ['*']); // 创建access_token并设置作用域
            return [
                'code' => 1,
                'message' => 'success',
                'data' => [
                    'token_type' => 'Bearer',
                    'access_token' => $access_data->accessToken,
                ]
            ];
        } catch (\Exception $e) {
            return [
                'code' => 0,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 退出登录
     * @Author: qinjintian
     * @Date:   2018-09-14
     */
    public function logout()
    {
        if (auth()->check()) {
            request()->user()->token()->revoke(); // 此方法允许一个账号多处同时在线
            // auth->user()->oauthAccessToken()->delete(); // 此方法不允许同一个用户多处登录
        }
        systemLog('登录', '用户退出登录', 2);
        return response()->json(['code' => 1, 'message' => '退出成功']);
    }

    /*
    * 写入/获取缓存
    */
    public function getUserCache($user_id)
    {
        // 拿取缓存登录ip、登录地址
        $location = cache()->remember('user_id_' . $user_id, 30, function() {
            //获取ip、地址
            $login_ip = request()->getClientIp();
            $ipip = new \ipip\db\City(storage_path('app/private/ipdb/ipipfree.ipdb'));
            $location = $ipip->find($login_ip, 'CN');
            $login_addr = empty($location[2]) ? '未知' : $location[2];
            // 存入缓存
            $cache_arr = [];
            $cache_arr['login_ip'] = $login_ip;
            $cache_arr['login_addr'] = $login_addr;
            return $cache_arr;
        });
        return $location;
    }

    //从考勤机上推送过来的数据
    public function push()
    {
        $inputs = request()->all();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $record = new \App\Models\AttendanceRecord;
        if (!isset($inputs['users_records']) || !is_array($inputs['users_records'])) {
            return response()->json(['code' => -1, 'message' => '传输数据失败']);
        }
        // $start_time = date('Y-m-d', time() - 2592000) . ' 00:00:00';//获取30天内的数据
        $start_time = date('Y-m-01', strtotime("-1 month")) . ' 00:00:00';//上一个月1号
        $end_time = date('Y-m-d H:i:s');//当前时间
        $users_records = $record->whereBetween('punch_time', [$start_time, $end_time])->select(['number', 'punch_time'])->get();
        //dda($users_records);
        $user_date_records = [];//这段时间内用户的说有考勤记录
        foreach ($users_records as $key => $value) {
            $user_date_records[$value->number][] = $value->punch_time;
        }
        $insert = [];
        $i = 0;
        foreach ($inputs['users_records'] as $key => $value) {
            $tmp = $user_date_records[$value['number']] ?? [];
            if (!in_array($value['time'], $tmp) && isset($user_data['number_id'][$value['number']])) {
                //当前考勤数据 数据库里没有  并且考勤机工号已经关联用户id
                $insert[$i]['number'] = $value['number'];
                $insert[$i]['user_id'] = $user_id = $user_data['number_id'][$value['number']];//考勤机工号对应的用户id
                $insert[$i]['username'] = $user_data['id_username'][$user_id];
                $insert[$i]['realname'] = $user_data['id_realname'][$user_id];
                $year = date('Y', strtotime($value['time']));
                $month = date('m', strtotime($value['time']));
                $insert[$i]['year'] = $year;
                $insert[$i]['month'] = $month;
                $insert[$i]['punch_time'] = $value['time'];
                $insert[$i]['created_at'] = date('Y-m-d H:i:s');
                $insert[$i]['updated_at'] = date('Y-m-d H:i:s');
                $i++;
            }
        }
        if (!empty($insert)) {
            //插入数据库
            $record->insert($insert);
        }
        return response()->json(['code' => 1, 'message' => '操作成功']);

    }

    /**
     * 下载文件
     * @Author: qinjintian
     * @Date:   2019-04-30
     */
    public function download()
    {
        $filepath = request()->get('filepath');
        $filename = request()->get('filename');
        if (!$filepath) {
            return ['code' => -1, 'message' => '缺少参数filepath'];
        }
        $filepath = str_replace(request()->root(), '', $filepath);
        $realpath = realpath(public_path($filepath));
        if (!$realpath) {
            return ['code' => 0, 'message' => '文件不存在，请检查'];
        }
        $filename = $filename ? $filename : substr($filepath, strrpos($filepath, '/')+1);
        return response()->download($realpath, $filename, $headers = ['Content-Type: application/octet-stream']);
    }

    // 发送邮件
    public function sendEmail(Mailer $mailer)
    {
        $inputs = request()->all();
        //$user = \App\Models\User::findOrFail($inputs['id']);
        //$this->dispatch(new SendEmail($user));
        if(!isset($inputs['email']) || empty($inputs['email'])){
            return response()->json(['code' => 0, 'message' => '请输入有效邮箱']);
        }
        $pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        if (!preg_match($pattern, $inputs['email'])){
            return response()->json(['code' => 0, 'message' => '请输入合法邮箱']);
        }
        $this->dispatch(new SendEmail($inputs));
        return response()->json(['code' => 1, 'message' => '发送成功']);
    }

    // 调试专用接口
    public function test(Mailer $mailer)
    {
        dd(111);
    }
}

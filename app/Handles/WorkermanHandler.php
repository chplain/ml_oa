<?php
 
namespace handles;
use Workerman\Lib\Timer;
 
class WorkermanHandler
{

    public function onWorkerStart($worker)
    {
        //加载index文件的内容
        require __DIR__ . '/../../vendor/autoload.php';
        require_once __DIR__ . '/../../bootstrap/app.php';
        //心跳30秒
        Timer::add(30, function()use($worker){
            $time_now = time();
            foreach($worker->connections as $connection) {
                // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
                if (!isset($connection->lastMessageTime)) {
                    $connection->lastMessageTime = $time_now;
                    continue;
                }
                // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
                if ($time_now - $connection->lastMessageTime > 30) {
                    $connection->close();
                }
            }
        });
    }
 
    // 处理客户端连接
    public function onConnect($connection)
    {
        //每15秒发送一次
        Timer::add(15, function() use($connection) {
            if(isset($connection->user_id) && $connection->user_id > 0){
                $noti = new \App\Models\Notification;
                $count = $noti->where('status', 0)->where('user_id', $connection->user_id)->count();
                $connection->send(json_encode(['code' => 100, 'msg'=>$connection->user_id, 'notice'=> $count]));
            }   
        });
        echo "new connection from ip " . $connection->getRemoteIp() . "\n";
    }
 
    // 处理客户端消息
    public function onMessage($connection, $data)
    {
        // 向客户端发送hello $data
        //server信息
        if (isset($data->server)) {
            foreach ($data->server as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
 
        //header头信息
        if (isset($data->header)) {
            foreach ($data->header as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
 
        //get请求
        if (isset($data->get)) {
            foreach ($data->get as $k => $v) {
                $_GET[$k] = $v;
            }
        }
 
        //post请求
        if (isset($data->post)) {
            foreach ($data->post as $k => $v) {
                $_POST[$k] = $v;
            }
        }
 
        //文件请求
        if (isset($data->files)) {
            foreach ($data->files as $k => $v) {
                $_FILES[$k] = $v;
            }
        }
 
        //cookies请求
        if (isset($data->cookie)) {
            foreach ($data->cookie as $k => $v) {
                $_COOKIE[$k] = $v;
            }
        }
 
        ob_start();//启用缓存区
 
        //加载laravel请求核心模块
        $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);
        $laravelResponse = $kernel->handle(
            $request = \Illuminate\Http\Request::capture()
        );
        $laravelResponse->send();
        $kernel->terminate($request, $laravelResponse);
 
        $res = ob_get_contents();//获取缓存区的内容
        ob_end_clean();//清除缓存区

        $connection->lastMessageTime = time();
        if($data){
            $inputs = json_decode($data, true);
            if($inputs['type'] == 'login'){
                //登录成功 存起来
                $connection->user_id = $inputs['user_id'];
                $connection->realname = $inputs['realname'];
                //$this->wk->clients[$inputs['user_id']] = $connection;
                $connection->send(json_encode($inputs));
            }elseif($inputs['type'] == 'heart'){
                $connection->send(json_encode(['code' => 200, 'message' => '响应心跳']));
            }
        }
        //输出缓存区域的内容
        //$connection->send(json_encode($data));

    }
 
    // 处理客户端断开
    public function onClose($connection)
    {
        echo "connection closed from ip {$connection->getRemoteIp()}\n";
    }
}

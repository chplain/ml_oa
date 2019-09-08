<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Excel;

class SystemLogController extends Controller
{
    /**
     * 操作日志
     * @Author: molin
     * @Date:   2018-08-22
     */
    public function index(){
        $inputs = request()->all();
        $model = new \App\Models\SystemLog;
        $inputs['type'] = 1; //普通日志
        $data = $model->getDataList($inputs);
        $items = array();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        foreach ($data['datalist'] as $key => $value) {
            $items['realname'] = $user_data['id_realname'][$value->user_id];
            $items['operate_path'] = $value->operate_path;
            $items['content'] = $value->content;
            $items['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $data['datalist'][$key] = $items;
        }
        $permission = new \App\Models\Permission;
        $menu_list = $permission->where('parent_id', 0)->select(['id', 'title'])->get();
        $data['menu_list'] = $menu_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 登录日志
     * @Author: molin
     * @Date:   2018-11-12
     */
    public function login(){
        $inputs = request()->all();
        $model = new \App\Models\SystemLog;
        $inputs['type'] = 2; //登录日志
        $data = $model->getDataList($inputs);
        $items = array();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        foreach ($data['datalist'] as $key => $value) {
            $items['realname'] = $user_data['id_realname'][$value->user_id];
            $items['content'] = $value->content;
            $items['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $data['datalist'][$key] = $items;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 操作日志详情
     * @Author: molin
     * @Date:   2018-08-22
     */
    public function show(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '参数错误']);
        }
        $log = new \App\Models\SystemLog;
        $showInfo = $log->where('id', $inputs['id'])->first();
        return  response()->json(['code' => 1, 'message' => '获取成功', 'data' => $showInfo]);
    }

}

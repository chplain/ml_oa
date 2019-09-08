<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AttendanceSettingController extends Controller
{
    //出勤设置
    public function store(){
    	
    	$inputs = request()->all();
    	$data = array();
        $year = date('Y');
        $holiday = new \App\Models\Holiday;
        $if_exits = $holiday->where('year', $year)->first();
        if(empty($if_exits)){
            // 把一年的节假日都存到数据库
            $rss1 = $holiday->addHolidays();//添加节假日
            if(!$rss1){
                return response()->json(['code' => -1, 'message' => '节假日获取失败']);
            }
        }

    	//获取最新设置
    	$setting = new \App\Models\AttendanceSetting;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		$setting_info = $setting->orderBy('id', 'desc')->first();
    		$data['setting_info'] = $setting_info;
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['user_list'] = $user_list;
            $free = new \App\Models\AttendanceFree;
            $free_list = $free->get();
            $tmp = array();
            foreach ($free_list as $key => $value) {
                $tmp[] = $value->user_id;
            }
            $data['free_ids'] = $tmp;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	$rules = [
    		'am_start_time' => 'required',
            'am_end_time' => 'required',
            'am_start_before_time' => 'required|integer',
            'am_start_after_time' => 'required|integer',
            'pm_start_time' => 'required',
            'pm_end_time' => 'required',
            'pm_end_before_time' => 'required|integer',
            'pm_end_after_time' => 'required|integer',
    		'free_ids' => 'required|array'
    	];
    	$attributes = [
            'am_start_time' => '上午上班时间',
            'am_end_time' => '上午下班时间',
            'am_start_before_time' => '上班前多少分钟打卡有效',
            'am_start_after_time' => '上班后多少分钟打卡有效',
            'pm_start_time' => '下午上班时间',
            'pm_end_time' => '下午下班时间',
            'pm_end_before_time' => '下班前多少分钟打卡有效',
            'pm_end_after_time' => '下班后多少分钟打卡有效',
            'free_ids' => '免签人员'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }

        $res = $setting->storeData($inputs);
        if($res){
            //免签人员
            $free = new \App\Models\AttendanceFree;
            $free::truncate();//清空数据
            if(!empty($inputs['free_ids'])){
                $insert = array();
                foreach ($inputs['free_ids'] as $k => $u) {
                    $insert[$k]['user_id'] = $u; 
                }
                $free->insert($insert);//重新写入
            }
        	systemLog('考勤管理', '更新了出勤设置');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
    	return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    //节假日设置
    public function holiday(){
    	$inputs = request()->all();
    	$data = array();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		if(!isset($inputs['month']) || empty($inputs['month'])){
    			$inputs['month'] = date('m');
    		}
    		if(!isset($inputs['year']) || empty($inputs['year'])){
    			$inputs['year'] = date('Y');
    		}
    		$attend = new \App\Models\Attendance;
    		$data = $attend->getDataList($inputs);
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}

    	$rules = [
    		'month' => 'required|array',
    	];

    	$attributes = [
            'month' => '当月数据',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $update = $tmp = array();
        foreach ($inputs['month'] as $key => $val) {
        	$tmp['id'] = $val['id'];
        	$tmp['type'] = $val['type'];
        	$tmp['updated_at'] = date('Y-m-d H:i:s');
        	$update[] = $tmp;
        }

        $attend = new \App\Models\Attendance;
        $result = $attend->updateBatch($update);
        if($result){
            systemLog('考勤管理', '编辑了节假日设置');
        	return response()->json(['code' => 1, 'message' => '保存成功']);
        }
        return response()->json(['code' =>0, 'message' => '保存失败']);
    }

}

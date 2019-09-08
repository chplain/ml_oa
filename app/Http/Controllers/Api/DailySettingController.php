<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DailySettingController extends Controller
{
    /**
    *  保存日报配置
    **/
    public function store(){

    	$inputs = request()->all();
    	$daily = new \App\Models\DailySetting;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'setting'){
    		$setting_info = $daily->orderBy('id', 'desc')->first();
    		$setting_info['type_content'] = unserialize($setting_info['type_content']);
    		$setting_info['weeks'] = explode(',', $setting_info['weeks']);
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $setting_info]);
    	}
        $rules = [
            'weeks' => 'required|array',
            'start_time' => 'required',
            'end_time' => 'required',
            'if_eval' => 'required',
            'type_content' => 'required|array'
        ];
        $attributes = [
            'weeks' => '写日报时间',
            'start_time' => '每天开始时间',
            'end_time' => '每天结束时间',
            'if_eval' => '是否考核',
            'type_content' => '日报类型'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if(!isset($inputs['type_content']) || empty($inputs['type_content'])){
        	return response()->json(['code' => -1, 'message' => '请填写日报类型']);
        }
        foreach ($inputs['type_content'] as $key => $value) {
        	$response = $this->checkInputs($value);
        	if($response['code'] != 1){
	        	return response()->json($response);
	        }
        }	
        $result = $daily->storeData($inputs);
        $response = $result ? ['code' => 1, 'message' => '操作成功'] : ['code' => 0, 'message' => '操作失败，请重试'];
        return response()->json($response);
    }

     /**
     * 检查提交字段是否为空
     * @Author: molin
     * @Date:   2018-09-12
     */
    public function checkInputs($value){
    	if(!isset($value['number']) || !is_numeric($value['number'])){
    		return $response = ['code' => -1, 'message' => '序号字段必填'];
    	}
    	if(!isset($value['name']) || empty($value['name'])){
    		return $response = ['code' => -1, 'message' => '任务名称字段必填'];
    	}
    	if(!isset($value['if_as']) || !is_numeric($value['if_as'])){
    		return $response = ['code' => -1, 'message' => '是否关联字段必填'];
    	}
    	if(!isset($value['as_id']) || !is_numeric($value['as_id'])){
    		return $response = ['code' => -1, 'message' => '关联id字段必填'];
    	}
    	if(!isset($value['if_use']) || !is_numeric($value['if_use'])){
    		return $response = ['code' => -1, 'message' => '是否启用字段必填'];
    	}

    	return $response = ['code' => 1, 'message' => '验证通过'];
    }
}

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyReportController extends Controller
{
    //

    /**
    * 报备申请
    * @author molin
    * @date 2018-11-19
    */
    public function store(){
    	$inputs = request()->all();
        $applyReport = new \App\Models\ApplyReport;
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $applyReport::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
    	$rules = [
            'content' => 'required|array',
            'remarks' => 'required|max:100',
        ];
        $attributes = [
            'content' => '打卡时间',
            'remarks' => '备注',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $log_txt = array();
        foreach ($inputs['content'] as $key => $value) {
        	if(empty($value['date'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数date']);
        	}
        	if(empty($value['first_time'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数first_time']);
        	}
        	if(empty($value['last_time'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数last_time']);
        	}
            if(empty($value['type']) || !is_numeric($value['type'])){
                return response()->json(['code' => -1, 'message' => '缺少参数type']);
            }
            $log_txt[] = $value['date'];
        }
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $applyReport::type)->orderBy('id', 'desc')->first();//获取最新的配置
    	if(empty($setting_info)){
    		return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
    	}
        $steps = new \App\Models\AuditProcessStep;
        $step1 = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();
        if(empty($step1)){
            return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
        }
    	$setting_info['setting_content'] = unserialize($setting_info['setting_content']);

        $inputs['user_id'] = auth()->user()->id;
        $inputs['dept_id'] = auth()->user()->dept_id;
		
        $result = $applyReport->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('报备申请', '提交了报备申请['.implode(',', $log_txt).']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);        
    }

    
    
}

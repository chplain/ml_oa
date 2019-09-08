<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ApplyProcessSetting extends Model
{
    //
    protected $table = "apply_process_settings";

    public function storeData($inputs, $step_data, $controls_data){
    	$setting = new ApplyProcessSetting;
    	$setting->type_id = $inputs['id'];
    	$setting->setting_content = serialize($inputs['setting_content']);
    	$setting->apply_setting = serialize($inputs['apply_setting']);//申请时做判断跳到哪一步
    	$res = false;
        DB::transaction(function () use ($setting, $step_data, $controls_data) {
        	$setting->save();
        	$step_items = array();
            foreach ($step_data as $key => $value) {
            	$step_items[$key]['setting_id'] = $setting->id;
            	$step_items[$key]['step'] = $value['step'];
            	$step_items[$key]['name'] = $value['name'];
            	$step_items[$key]['cur_user_id'] = $value['cur_user_id'];
            	$step_items[$key]['dept_id'] = $value['dept_id'] ?? 0;
            	$step_items[$key]['rank_id'] = $value['rank_id'] ?? 0;
            	$step_items[$key]['role_id'] = $value['role_id'] ?? 0;
            	$step_items[$key]['user_id'] = $value['user_id'] ?? 0;
            	$step_items[$key]['step_type'] = $value['step_type'];
            	$step_items[$key]['condition1'] = $value['condition1'] ?? '';
            	$step_items[$key]['if_condition'] = $value['if_condition'] ?? 0;
            	$step_items[$key]['next_step_id'] = $value['next_step_id'] ?? 0;
            	$step_items[$key]['condition2'] = $value['condition2'] ?? '';
            	$step_items[$key]['if_reject'] = $value['if_reject'];
            	$step_items[$key]['reject_step_id'] = $value['reject_step_id'] ?? 0;
            }
            $steps = new \App\Models\AuditProcessStep;
            $steps->insert($step_items);
            $items = array();
            $i = 0;
            foreach ($controls_data as $key => $value) {
            	foreach ($value as $k => $v) {
            		$items[$i]['setting_id'] = $setting->id;
            		$items[$i]['name'] = $v['name'];
            		$items[$i]['step'] = $v['step'];
            		$items[$i]['if_show'] = $v['if_show'];
            		$items[$i]['if_edit'] = $v['if_edit'];
            		$i++;
            	}
            }
            $con = new \App\Models\AuditFormControlSetting;
            $con->insert($items);
        }, 5);
        $res = true;
        return $res;
    }
}

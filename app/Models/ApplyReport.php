<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ApplyReport extends Model
{
    //
    protected $table = 'apply_reports';

    const type = 8;//表单类型

    // 获取部门信息
    public function hasDept()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    public function hasMain()
    {
        return $this->hasOne('App\Models\ApplyMain', 'apply_id', 'id');
    }

    //报备申请--保存数据
    public function storeData($inputs, $setting_info){
    	//申请表
    	$apply_report = new ApplyReport;
        $apply_report->user_id = $inputs['user_id'];
    	$apply_report->dept_id = $inputs['dept_id'];
    	$apply_report->content = serialize($inputs['content']);//报备内容（日期等信息）
    	$apply_report->remarks = $inputs['remarks'];//备注
        $apply_report->status = 0;
    	$re = false;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $dept = new \App\Models\Dept;

        //获取流程配置信息
        $steps = new \App\Models\AuditProcessStep;
        if(!empty($setting_info->apply_setting)){
            //根据职级走审核流程
            $rank_id = auth()->user()->rank_id;//获取申请人职级id
            $apply_setting = unserialize($setting_info->apply_setting);
            foreach ($apply_setting as $key => $value) {
                if($value['rank_id'] == $rank_id){
                    $next_step_id = $value['next_step_id'];
                }
            }
            if(!empty($next_step_id)){
                $step1_setting = $steps->where('setting_id', $setting_info->id)->where('step', 'step'.$next_step_id)->first();//第一步审核
            }else{
                $step1_setting = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();//第一步审核
            }
            
        }else{
            $step1_setting = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();//第一步审核
        }
        
        if(!empty($step1_setting)){
            //申请无限制  走正常流程
            $apply_report->status_txt = $step1_setting->name;//步骤说明
            $next_user_ids = array();
            //cur_user_id当前审核人 1部门负责人 2部门和职级 3岗位 4指定人员
            $cur_user_id = $step1_setting->cur_user_id;
            if($cur_user_id == 1){
                $dept_id = auth()->user()->dept_id;
                $dept_info = $dept->where('id', $dept_id)->select(['id','supervisor_id'])->first();
                if(empty($dept_info)){
                    return false;
                }
                $next_user_ids = array($dept_info->supervisor_id);
            }else if($cur_user_id == 2){
                $dept_id = $step1_setting->dept_id;
                $rank_id = $step1_setting->rank_id;
                $users = $user->where('status', 1)->where('dept_id', $dept_id)->where('rank_id', $rank_id)->select(['id'])->get();
                if(empty($users)){
                    return false;
                }
                foreach ($users as $key => $value) {
                    $next_user_ids[] = $value->id;
                }
            }else if($cur_user_id == 3){
                $role_id = $step1_setting->role_id;
                $role_list = $user->where('position_id', $role_id)->select(['id'])->get();
                if(empty($role_list)){
                    return false;
                }
                foreach ($role_list as $key => $value) {
                    $next_user_ids[] = $value->id;
                }
            }else if($cur_user_id == 4){
                $uid = $step1_setting->user_id;
                if(empty($uid)){
                    return false;
                }
                $next_user_ids = array($uid);
            }
            
            $apply_report->current_verify_user_id = implode(',', $next_user_ids);
            $apply_report->step = $step1_setting->step;
        }

		DB::transaction(function () use($apply_report,$setting_info){
		    $apply_report->save();
    		//流程表
	    	$audit_process = new \App\Models\AuditProces;
	    	$audit_process->storeData($setting_info, $apply_report, 8, 'ApplyReport');
	    	//往通用表里面加一条数据
	    	$apply_main = new \App\Models\ApplyMain;
	    	$apply_main->storeData($apply_report, 8, 'ApplyReport', '报备申请');
		}, 5);
    	$re = true;
    	return $re;
    }
}

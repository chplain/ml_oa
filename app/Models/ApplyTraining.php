<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ApplyTraining extends Model
{
    //
    protected $table = 'apply_trainings';

    const type = 5;//表单类型

    //关联流程表
    public function hasProces()
    {
        return $this->hasMany('App\Models\AuditProces', 'apply_id', 'id');
    }


   //培训申请--保存数据
    public function storeData($inputs, $setting_info){
    	//申请表
    	$apply_training = new ApplyTraining;
        $apply_training->user_id = $inputs['user_id'];
    	$apply_training->dept_id = $inputs['dept_id'];
        $apply_training->name = $inputs['name'];
    	$apply_training->type_id = $inputs['type_id'];
        $apply_training->addr_id = $inputs['addr_id'] ?? 0;
    	$apply_training->content = serialize($inputs['content']);
    	if(isset($inputs['start_time']) && !empty($inputs['start_time'])){
    		$apply_training->start_time = $inputs['start_time'];
    	}
    	if(isset($inputs['end_time']) && !empty($inputs['end_time'])){
    		$apply_training->end_time = $inputs['end_time'];
    	}
        $apply_training->by_training_users = $inputs['by_training_users'];
    	$apply_training->training_users = $inputs['training_users'];
        $apply_training->status = 0;
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
            $apply_training->status_txt = $step1_setting->name;//步骤说明
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
                $role_list = DB::table('model_has_roles')->where('role_id', $role_id)->select(['model_id'])->get();
                if(empty($role_list)){
                    return false;
                }
                foreach ($role_list as $key => $value) {
                    $next_user_ids[] = $value->model_id;
                }
            }else if($cur_user_id == 4){
                $uid = $step1_setting->user_id;
                if(empty($uid)){
                    return false;
                }
                $next_user_ids = array($uid);
            }
            
            $apply_training->current_verify_user_id = implode(',', $next_user_ids);
            $apply_training->step = $step1_setting->step;
        }

		DB::transaction(function () use($apply_training,$setting_info){
		    $apply_training->save();
    		//流程表
	    	$audit_process = new \App\Models\AuditProces;
	    	$audit_process->storeData($setting_info, $apply_training, 5, 'ApplyTraining');
	    	//往通用表里面加一条数据
	    	$apply_main = new \App\Models\ApplyMain;
	    	$apply_main->storeData($apply_training, 5, 'ApplyTraining', '培训申请-'.$apply_training->name);
		}, 5);
    	$re = true;
    	return $re;
    }

    //获取数据列表-培训
    public function getQueryList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs){
                    return $query->where('user_id', $inputs['user_id']);
                })
                ->when(isset($inputs['keyword']) && !empty($inputs['keyword']), function ($query) use ($inputs) {
                    return $query->where('name', 'like', '%'.$inputs['keyword'].'%');
                })
                ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs) {
                    return $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                });
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }


}

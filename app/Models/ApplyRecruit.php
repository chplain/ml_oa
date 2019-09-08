<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ApplyRecruit extends Model
{
    //
    protected $table = 'apply_recruits';

    const type = 4;//表单类型

    // 获取部门信息
    public function hasDept()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    public function hasMain()
    {
        return $this->hasOne('App\Models\ApplyMain', 'apply_id', 'id');
    }

    //招聘申请--保存数据
    public function storeData($inputs, $setting_info){
    	//申请表
    	$apply_rec = new ApplyRecruit;
        $apply_rec->user_id = $inputs['user_id'];
    	$apply_rec->dept_id = $inputs['dept_id'];
        $apply_rec->number = $inputs['number'];
        $apply_rec->post = $inputs['post'];
    	$apply_rec->positions_id = $inputs['positions_id'];
        $apply_rec->reason_ids = implode(',', $inputs['reason_ids']);
    	$apply_rec->reason = $inputs['reason'] ?? '';
        $apply_rec->type = $inputs['type'];
    	$apply_rec->duty = $inputs['duty'];
    	$apply_rec->demand = $inputs['demand'];
    	$apply_rec->salary1 = $inputs['salary1'];
    	$apply_rec->salary2 = $inputs['salary2'];
        $apply_rec->status = 0;
    	
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
            $apply_rec->status_txt = $step1_setting->name;//步骤说明
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
            
            $apply_rec->current_verify_user_id = implode(',', $next_user_ids);
            $apply_rec->step = $step1_setting->step;
        }

		DB::transaction(function () use($apply_rec,$setting_info){
		    $apply_rec->save();
    		//流程表
	    	$audit_process = new \App\Models\AuditProces;
	    	$audit_process->storeData($setting_info, $apply_rec, 4, 'ApplyRecruit');
	    	//往通用表里面加一条数据
	    	$apply_main = new \App\Models\ApplyMain;
	    	$apply_main->storeData($apply_rec, 4, 'ApplyRecruit', '招聘'.$apply_rec->post);
		}, 5);
    	$re = true;
    	return $re;
    }

    //获取数据列表
    public function getDataList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        
        $where_query = $this->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                    return $query->where('user_id', $inputs['user_id']);
                })
                ->when(isset($inputs['dept_id']) && is_numeric($inputs['dept_id']), function($query) use ($inputs){
                    return $query->where('dept_id', $inputs['dept_id']);
                })
                ->when(isset($inputs['type']) && is_numeric($inputs['type']), function($query) use ($inputs){
                    return $query->where('type', $inputs['type']);
                })
                ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                    return $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                })
                ->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query) use ($inputs){
                    return $query->where('post', 'like', '%'.$inputs['keywords'].'%');
                })
                ->when(isset($inputs['search_status']) && is_numeric($inputs['search_status']), function($query) use ($inputs){
                    return $query->where('status', $inputs['search_status']);
                })
                ->with(['hasDept' => function($query){
                    return $query->select(['id','name','supervisor_id']);
                }])
                ->with(['hasMain' => function($query){
                    return $query->where('type_id', 4)->select(['id','apply_id','status','status_txt']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')
                ->when(!isset($inputs['export']), function ($query) use ($start, $length){
                    return $query->skip($start)->take($length);
                })
                ->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

}

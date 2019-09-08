<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ApplyAccess extends Model
{
    //物品领用
    protected $table = 'apply_access';

    const type = 2;//表单类型
    
    // 获取部门信息
    public function hasDept()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    public function hasMain()
    {
        return $this->hasOne('App\Models\ApplyMain', 'apply_id', 'id');
    }


    //离职申请--保存数据
    public function storeData($inputs, $setting_info){
    	//申请表
    	$apply_acc = new ApplyAccess;
        $apply_acc->user_id = $inputs['user_id'];
    	$apply_acc->dept_id = $inputs['dept_id'];
        $apply_acc->content = serialize($inputs['content']);
        $apply_acc->uses = $inputs['uses'];
        $apply_acc->if_personnel = $inputs['if_personnel'] ?? 0;
        $apply_acc->keywords = $inputs['keywords'] ?? '';
        $apply_acc->status = 0;
    	
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
            $apply_acc->status_txt = $step1_setting->name;//步骤说明
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
            
            $apply_acc->current_verify_user_id = implode(',', $next_user_ids);
            $apply_acc->step = $step1_setting->step;
        }


		DB::transaction(function () use($apply_acc,$setting_info){
		    $apply_acc->save();
    		//流程表
	    	$audit_process = new \App\Models\AuditProces;
	    	$audit_process->storeData($setting_info, $apply_acc, 2, 'ApplyAccess');
	    	//往通用表里面加一条数据
	    	$apply_main = new \App\Models\ApplyMain;
	    	$apply_main->storeData($apply_acc, 2, 'ApplyAccess', '物品领用申请');
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
                ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                    return $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                })
                ->when(isset($inputs['keyword']) && !empty($inputs['keyword']), function($query) use ($inputs){
                    return $query->where('keywords', 'like', '%'.$inputs['keyword'].'%');
                })
                ->when(isset($inputs['if_personnel']) && is_numeric($inputs['if_personnel']), function($query) use ($inputs){
                    return $query->where('if_personnel', $inputs['if_personnel']);
                })
                ->when(isset($inputs['search_status']) && is_numeric($inputs['search_status']), function($query) use ($inputs){
                	if($inputs['search_status'] == 1){
                		return $query->where('status', 0);
                	}else if($inputs['search_status'] == 2){
                		return $query->where('status', 1);
                	}else if($inputs['search_status'] == 3){
                		return $query->where('status', 2);
                	}
                    
                })
                ->with(['hasDept' => function($query){
                    return $query->select(['id','name','supervisor_id']);
                }])
                ->with(['hasMain' => function($query){
                    return $query->where('type_id', 2)->select(['id','apply_id','status']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }


}

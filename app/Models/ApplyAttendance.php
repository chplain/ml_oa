<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ApplyAttendance extends Model
{
    //
    protected $table = 'apply_attendances';

    const type = 1;//表单类型

    // 获取部门信息
    public function hasDept()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    public function hasMain()
    {
        return $this->hasOne('App\Models\ApplyMain', 'apply_id', 'id');
    }

    //出勤申请--保存数据
    public function storeData($inputs, $setting_info){
    	//申请表
    	$apply_att = new ApplyAttendance;
        $apply_att->type = $inputs['type'];
        $apply_att->user_id = $inputs['user_id'];
    	$apply_att->dept_id = $inputs['dept_id'];
    	$apply_att->start_time = $inputs['start_time'];
    	$apply_att->end_time = $inputs['end_time'];
        $apply_att->remarks = $inputs['remarks'];
        $apply_att->leave_type = $inputs['leave_type'] ?? 0;
        $apply_att->leave_time = $inputs['leave_time'] ?? 0;//请假时间
    	$apply_att->time_str = $inputs['time_str'] ?? 0;//加班、外勤时间
    	$apply_att->outside_addr = $inputs['outside_addr'] ?? '';
        $apply_att->status = 0;
        if(isset($inputs['time_data']) && !empty($inputs['time_data'])){
            $apply_att->time_data = serialize($inputs['time_data']);//加班外勤时间
        }
    	$re = false;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $dept = new \App\Models\Dept;
        //$dept_info = $dept->where('user_id', $inputs['user_id'])->select(['id','supervisor_id'])->first();

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
            $apply_att->status_txt = $step1_setting->name;//步骤说明
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
                $users = $user->where('status', 1)->where('dept_id', $dept_id)->where('rank_id', $rank_id)->select(['id'])->get()->toArray();
                if(empty($users)){
                    return false;
                }
                foreach ($users as $key => $value) {
                    $next_user_ids[] = $value['id'];
                }
            }else if($cur_user_id == 3){
                $role_id = $step1_setting->role_id;
                $role_list = $user->where('status', 1)->where('position_id', $role_id)->select(['id'])->get()->toArray();
                if(empty($role_list)){
                    return false;
                }
                foreach ($role_list as $key => $value) {
                    $next_user_ids[] = $value['id'];
                }
            }else if($cur_user_id == 4){
                $uid = $step1_setting->user_id;
                if(empty($uid)){
                    return false;
                }
                $next_user_ids = array($uid);
            }
            
            $apply_att->current_verify_user_id = implode(',', $next_user_ids);
            $apply_att->step = $step1_setting->step;
        }

		DB::transaction(function () use($apply_att,$setting_info){
		    $apply_att->save();
    		//流程表
	    	$audit_process = new \App\Models\AuditProces;
	    	$audit_process->storeData($setting_info, $apply_att, 1, 'ApplyAttendance');
	    	//往通用表里面加一条数据
	    	$apply_main = new \App\Models\ApplyMain;
	    	$apply_main->storeData($apply_att, 1, 'ApplyAttendance', $apply_att->remarks);
            //多个加班时间段
            if($apply_att->type == 2){
                $times = new \App\Models\ApplyAttendanceTime;
                $time_insert = array();
                foreach (unserialize($apply_att->time_data) as $key => $value) {
                    $time_insert[$key]['apply_id'] = $apply_att->id;
                    $time_insert[$key]['user_id'] = $apply_att->user_id;
                    $time_insert[$key]['type'] = $apply_att->type;
                    $time_insert[$key]['start_time'] = $value['start_time'];
                    $time_insert[$key]['end_time'] = $value['end_time'];
                    $time_insert[$key]['time_str'] = $value['time_str'];
                }
                if(!empty($time_insert)) $times->insert($time_insert);
            }
		}, 5);
    	$re = true;
    	return $re;
    }


    //获取数据列表
    public function getDataList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        if(isset($inputs['keywords']) && !empty($inputs['keywords'])){
            $user = new \App\Models\User;
            $user_list = $user->where('username', 'like', '%'.$inputs['keywords'].'%')->orWhere('realname', 'like', '%'.$inputs['keywords'].'%')->select(['id'])->get();
            $uids = array();
            foreach ($user_list as $key => $value) {
                $uids[] = $value['id'];
            }
            $inputs['user_ids'] = $uids;
        }
        $where_query = $this->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                    return $query->where('user_id', $inputs['user_id']);
                })
                ->when(isset($inputs['dept_id']) && is_numeric($inputs['dept_id']), function($query) use ($inputs){
                    return $query->where('dept_id', $inputs['dept_id']);
                })
                ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                    return $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                })
                ->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query) use ($inputs){
                    return $query->when(isset($inputs['user_ids']), function($query) use ($inputs){
                        return $query->whereIn('user_id', $inputs['user_ids']);
                    });
                })
                ->with(['hasDept' => function($query){
                    return $query->select(['id','name','supervisor_id']);
                }])
                ->with(['hasMain' => function($query){
                    return $query->where('type_id', 1)->select(['id','apply_id','status']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

}

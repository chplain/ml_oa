<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class AchievementAssign extends Model
{
    //
    protected $table = 'achievement_assigns';

    //保存数据
    public function storeData($inputs){
    	$assign = new AchievementAssign;
    	$tpl = new \App\Models\AchievementTemplate;
    	$info = $tpl->where('id', $inputs['id'])->first();
    	$assign->user_ids = implode(',', $inputs['user_ids']);
    	$assign->assign_user_id = auth()->user()->id;
    	$assign->tpl_id = $inputs['id'];
    	$assign->name = $info['name'];
    	$assign->score_user_ids = $info['score_user_ids'];
    	$assign->verify_user_ids = $info['verify_user_ids'];
    	$assign->year_month = $inputs['year'].'-'.$inputs['month'] ;
    	$assign->year = $inputs['year'];
    	$assign->month = $inputs['month'];
    	$user = new \App\Models\User;
    	$user_data = $user->whereIn('id',$inputs['user_ids'])->select(['id','dept_id'])->get();
    	$dept = new \App\Models\Dept;
    	$dept_data = $dept->getIdToData();

    	$result = false;
    	DB::transaction(function () use ($assign, $info, $user_data, $dept_data) {
            $assign->save();
            foreach ($user_data as $val) {
            	$assign_users = new \App\Models\AchievementAssignUser;
	        	$assign_users->tpl_id = $assign->tpl_id;//模板id
	        	$assign_users->assign_id = $assign->id;//分派表id
	        	$assign_users->user_id = $val['id'];
	        	$assign_users->dept_id = $val['dept_id'];
	        	$assign_users->year_month = $assign->year.'-'.$assign->month;
	        	$assign_users->year = $assign->year;
	        	$assign_users->month = $assign->month;
	        	$assign_users->name = $assign->name;
	        	$assign_users->th = $info->th;
	        	$assign_users->tbody = $info->tbody;
	        	$tmp_score_user_ids = $tmp_verify_user_ids = array();
	        	//评分人
	        	foreach (unserialize($info['score_user_ids']) as $value) {
	        		if($value['type'] == 1){
	        			//部门负责人
	        			$tmp_score_user_ids[] = $dept_data['id_supervisor_id'][$val['dept_id']];
	        		}else if($value['type'] == 2){
	        			//被考核人
	        			$tmp_score_user_ids[] = $val['id'];
	        		}else if($value['type'] == 3){
	        			//指定人员
	        			$tmp_score_user_ids[] = $value['user_id'];
	        		}
	        	}
	        	//审核人
	        	foreach (unserialize($info['verify_user_ids']) as $value) {
	        		if($value['type'] == 1){
	        			//部门负责人
	        			$tmp_verify_user_ids[] = $dept_data['id_supervisor_id'][$val['dept_id']];
	        		}else if($value['type'] == 2){
	        			//被考核人
	        			$tmp_verify_user_ids[] = $val['id'];
	        		}else if($value['type'] == 3){
	        			//指定人员
	        			$tmp_verify_user_ids[] = $value['user_id'];
	        		}
	        	}

	        	$assign_users->score_user_ids = implode(',', $tmp_score_user_ids);
	        	$assign_users->verify_user_ids = implode(',', array_unique($tmp_verify_user_ids));//去掉重复
	        	$assign_users->cur_user_id = $tmp_score_user_ids[0];
	        	$assign_users->status = 0;
	        	$assign_users->save();
	        	$score_obj = new \App\Models\AchievementUserScore;
	        	$score_users = array();
        		foreach (unserialize($info['score_user_ids']) as $key => $value) {
        			$score_users[$key]['assign_user_id'] = $assign_users->id;
        			$score_users[$key]['user_id'] = $val['id'];
	        		if($value['type'] == 1){
	        			//部门负责人
	        			$uid = $dept_data['id_supervisor_id'][$val['dept_id']];
	        		}else if($value['type'] == 2){
	        			//被考核人
	        			$uid = $val['id'];
	        		}else if($value['type'] == 3){
	        			//指定人员
	        			$uid = $value['user_id'];
	        		}
	        		$score_users[$key]['score_user_id'] = $uid;//评分人id
	        		$score_users[$key]['percent'] = $value['percent'];//评分占的百分比
	        		$score_users[$key]['if_view'] = $value['if_view'];//是否可以查看其他人的评分
	        		$score_users[$key]['created_at'] = date('Y-m-d H:i:s');
	        		$score_users[$key]['updated_at'] = date('Y-m-d H:i:s');
	        	}
    			$score_obj->insert($score_users);
    			//审核
    			$verify_obj = new \App\Models\AchievementUserVerify;
	        	$verify_users = array();
        		foreach (unserialize($info['verify_user_ids']) as $key => $value) {
        			$verify_users[$key]['assign_user_id'] = $assign_users->id;
        			$verify_users[$key]['user_id'] = $val['id'];
	        		if($value['type'] == 1){
	        			//部门负责人
	        			$uid = $dept_data['id_supervisor_id'][$val['dept_id']];
	        		}else if($value['type'] == 2){
	        			//被考核人
	        			$uid = $val['id'];
	        		}else if($value['type'] == 3){
	        			//指定人员
	        			$uid = $value['user_id'];
	        		}
	        		$verify_users[$key]['verify_user_id'] = $uid;//评分人id
	        		$verify_users[$key]['status'] = 0;//未审核
	        		$verify_users[$key]['created_at'] = date('Y-m-d H:i:s');
	        		$verify_users[$key]['updated_at'] = date('Y-m-d H:i:s');
	        	}
    			$verify_obj->insert($verify_users);
	        }
            
        }, 5);
    	$result = true;
        return $result;
    }
}

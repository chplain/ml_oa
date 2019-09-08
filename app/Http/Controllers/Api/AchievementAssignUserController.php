<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class AchievementAssignUserController extends Controller
{
    /** 
    *  我的绩效
    *  @author molin
    *	@date 2018-12-11
    */
    public function index(){
    	$inputs = request()->all();
    	$inputs['user_id'] = auth()->user()->id;
    	$assign_users = new \App\Models\AchievementAssignUser;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'confirm'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$th = explode(',', $info->th);
    		$tbody = unserialize($info->tbody);
    		$tmp['th'] = $th;
    		$tmp['tbody'] = $tbody;
    		
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
    			$score_users[$key]['percent'] = $value->percent.'%';
    		}
    		$tmp['score_users'] = $score_users;
    		$verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($verify_users as $key => $value) {
    			$tmp_verify_users[] = $user_data['id_realname'][$value];
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'submit'){
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $assign_users->where('id', $inputs['id'])->where('user_id', $inputs['user_id'])->first();
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$info->status = 1;
    		$info->if_edit = 2;//确认之后不能修改内容了
    		$res = $info->save();
    		if($res){
                systemLog('绩效', '确认了绩效内容');
                $notice_score_user_ids = explode(',', $info->score_user_ids);
                addNotice($notice_score_user_ids, '绩效', '您有一条绩效待评分', '', 0, 'achievement-list-score','achievement_user/score');//提醒评分
    			return response()->json(['code' => 1, 'message' => '操作成功']);
    		}
    		return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		$user = new \App\Models\User;
    		$user_data = $user->getIdToData();
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$th = explode(',', $info->th);
    		$tbody = unserialize($info->tbody);
    		$other_th_score = array();
    		$user_score_total = array(); 
    		foreach ($info->hasScore as $key => $value) {
    			if(!empty($value->score)){
    				$other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
    				$score_tmp = explode(',', $value->score);
    				foreach ($tbody as $k => $v) {
    					$user_score_total[$k] = $user_score_total[$k] ?? 0;
    					$user_score_total[$k] += sprintf('%.2f', ($score_tmp[$k] * ($value->percent / 100)));//得分
    					array_push($tbody[$k], $score_tmp[$k]);//追加得分
    				}
    			}
    		}
            $total_score = 0;
            foreach ($tbody as $k => $v) {
                if(isset($user_score_total[$k])){
                    $user_score_total[$k] = sprintf('%.2f', $user_score_total[$k]);
                    array_push($tbody[$k], $user_score_total[$k]);//追加评分
                    $total_score += sprintf('%.2f',$user_score_total[$k]);
                }else{
                    array_push($tbody[$k], 0);//追加评分
                    $total_score += 0;
                }
            }
            $th = array_merge($th, $other_th_score, ['总评分']);
            $tmp['th'] = $th;
            $tmp['tbody'] = $tbody;
    		$tmp['total_score'] = $total_score+100;//加上基数100分
    		
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
                $score_users[$key]['percent'] = $value->percent.'%';
    			$score_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['score_users'] = $score_users;
    		// $verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($info->hasVerify as $key => $value) {
                $tmp_verify_users[$key]['realname'] = $user_data['id_realname'][$value->verify_user_id];
    			$tmp_verify_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}
    	if(isset($inputs['month']) && !empty($inputs['month'])){
    		$inputs['year_month'] = date('Y-m', strtotime($inputs['month']));
    	}
        $inputs['in_status'] = [0,1,2,3];//去掉已撤销
    	$data = $assign_users->getDataList($inputs);
        foreach ($data['datalist'] as $key => $value) {
	        $items = array();
    		$items['id'] = $value->id;
    		$items['month'] = $value->year.'-'.$value->month;
    		if($value->total_score > 0){
    			$items['total_score'] = $value->total_score;
    		}else{
    			$items['total_score'] = '--';
    		}
    		$items['status'] = $value->status;
    		$items['if_confirm_content'] = 0;//是否确认内容
    		if($value->status == 0){
    			$items['status_txt'] = '确认内容中';
    			$items['if_confirm_content'] = 1;//用来显示确认内容按钮
    		}else if($value->status == 1){
    			$items['status_txt'] = '评分中';
    		}else if($value->status == 2){
    			$items['status_txt'] = '审核中';
    		}else if($value->status == 3){
    			$items['status_txt'] = '已完成';
    		}else if($value->status == 4){
    			$items['status_txt'] = '已撤销';
    		}
    		$data['datalist'][$key] = $items;
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  绩效评分
    *  @author molin
    *	@date 2018-12-11
    */
    public function score(){
    	$inputs = request()->all();
    	$cur_user_id = auth()->user()->id;
    	$assign_users = new \App\Models\AchievementAssignUser;
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$dept = new \App\Models\Dept;
    	$dept_data = $dept->getIdToData();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit_load'){
    		//加载修改
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		if($info->if_edit == 2){
    			return response()->json(['code' => 0, 'message' => '已确认的内容不可修改']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$tmp['th'] = explode(',', $info->th);
    		$tmp['tbody'] = unserialize($info->tbody);
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
    			$score_users[$key]['percent'] = $value->percent.'%';
    			// $score_users['if_view'] = $value->if_view;
    		}
    		$tmp['score_users'] = $score_users;
    		$verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($verify_users as $key => $value) {
    			$tmp_verify_users[] = $user_data['id_realname'][$value];
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit_save'){
    		//保存修改
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		unset($inputs['cur_user_id']);
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$rules = [
	            'th' => 'required|array',
	            'tbody' => 'required|array',
	        ];
	        $attributes = [
	            'th' => '表格头部',
	            'tbody' => '表格内容'
	        ];
	    	$validator = validator($inputs, $rules, [], $attributes);
	        if ($validator->fails()) {
	            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
	        }
    		$info->th = implode(',', $inputs['th']);
    		$info->tbody = serialize($inputs['tbody']);
    		$info->status = 0;//待确认内容状态
    		$info->if_edit = 1;//可以编辑
    		$res = $info->save();
    		if($res){
                systemLog('绩效', '编辑了绩效内容');
                addNotice($info->user_id, '绩效', '您有一条绩效内容待确认', '', 0, 'achievement-list-index','achievement_user/index');//提醒评分
    			return response()->json(['code' => 1, 'message' => '操作成功']);
    		}
    		return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'score_load'){
    		//加载评分
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		//$inputs['cur_user_id'] = $cur_user_id;
    		$inputs['status'] = 1;//评分中
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$th = explode(',', $info->th);
    		$tbody = unserialize($info->tbody);
    		// $th_end = array($th[count($th) - 1]);
    		// unset($th[count($th) - 1]);
    		$other_th_score = array();
    		$if_view_other = false;
    		foreach ($info->hasScore as $key => $value) {
    			if($value->score_user_id == $cur_user_id && $value->if_view == 1){
    				//当前评分人是否可以查看他人评分 1可以  2不可以
    				$if_view_other = true;
    			}
    		}
    		foreach ($info->hasScore as $key => $value) {
    			if(!empty($value->score) && $if_view_other){
    				$other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
    				$score_tmp = explode(',', $value->score);
    				foreach ($tbody as $k => $v) {
    					//$tbody_end = $v[count($v) - 1];//保存最后一个元素
    					//array_splice($tbody[$k], -1, 1, $score_tmp[$k]);//替换最后一个元素
    					array_push($tbody[$k], $score_tmp[$k]);//追加最后一个元素
    				}
    			}
    		}
    		$th = array_merge($th, $other_th_score);
    		$tmp['th'] = $th;
    		$tmp['tbody'] = $tbody;
    		
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
    			$score_users[$key]['percent'] = $value->percent.'%';
    			$score_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['score_users'] = $score_users;
    		$verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($info->hasVerify as $key => $value) {
                $tmp_verify_users[$key]['realname'] = $user_data['id_realname'][$value->verify_user_id];
                $tmp_verify_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'score_save'){
    		//保存评分
    		$rules = [
	            'id' => 'required|integer',
                'score' => 'required|array',
	            'remarks' => 'required|max:255'
	        ];
	        $attributes = [
	            'id' => 'id',
                'score' => '评分',
	            'remarks' => '备注'
	        ];
	    	$validator = validator($inputs, $rules, [], $attributes);
	        if ($validator->fails()) {
	            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
	        }
	        //$inputs['cur_user_id'] = $cur_user_id;
	        $inputs['status'] = 1;
	        $info = $assign_users->getDataInfo($inputs);
	        $user_score = new \App\Models\AchievementUserScore;
            //初次评分
            $user_score_info = $user_score->where('score_user_id', $cur_user_id)->where('assign_user_id', $inputs['id'])->orderBy('id', 'asc')->where('status', 0)->first();
	        if(empty($user_score_info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
            $score_tmp = [];
            foreach ($inputs['score'] as $value) {
                if(empty($value)) $value = 0;
                if(abs($value) > 100) return response()->json(['code' => 0, 'message' => '评分超出范围，请检查']);//评分只允许在 -100～100之间 （2019-07-15加的需求）
                $score_tmp[] = $value;
            }
            $inputs['score'] = $score_tmp;
    		$user_score_info->score = implode(',', $inputs['score']);
            $user_score_info->status = 1;
    		$user_score_info->remarks = $inputs['remarks'];
    		$total_score = 0;//评分人个人总评分
    		foreach ($inputs['score'] as $value) {
    			$total_score += sprintf('%.2f', ($value * ($user_score_info->percent / 100)));
    		}
    		$user_score_info->total_score = $total_score;
    		$res = false;
    		DB::transaction(function () use ($user_score_info, $user_score, $info, $user_data) {
    			$user_score_info->save();
    			$if_over = $user_score->where('assign_user_id', $user_score_info->assign_user_id)->where('status',0)->orderBy('id', 'asc')->first();
    			if(empty($if_over)){
    				//已经评分完
    				$info->status = 2;//待审核
    				$verify_users = explode(',', $info->verify_user_ids);
    				$info->cur_user_id = $verify_users[0];
    				//统计总分数
    				$sum  = $user_score->where('assign_user_id', $user_score_info->assign_user_id)->sum('total_score');
    				$info->total_score = $sum + 100;//加上基数100
    				$info->save();
                    addNotice($info->cur_user_id, '绩效', '['.$user_data['id_realname'][$info->user_id].']的绩效需要审核', '', 0, 'achievement-list-audit','achievement_user/verify');//提醒审核
    			}else{
    				//下一个评分人
    				$next_user_id = $if_over->score_user_id;
    				$info->cur_user_id = $next_user_id;
    				$info->save();
    			}
    		}, 5);
    		$res = true;
    		if($res){
                systemLog('绩效', '对'.$user_data['id_realname'][$info->user_id].'的绩效进行了评分');
    			return response()->json(['code' => 1, 'message' => '操作成功']);
    		}
    		return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'score_edit_save'){
            //保存编辑评分
            $rules = [
                'id' => 'required|integer',
                'score' => 'required|array',
                'remarks' => 'required|max:255'
            ];
            $attributes = [
                'id' => 'id',
                'score' => '评分',
                'remarks' => '备注'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $info = $assign_users->getDataInfo($inputs);
            $user_score = new \App\Models\AchievementUserScore;
            //编辑评分
            $user_score_info = $user_score->where('score_user_id', $cur_user_id)->where('assign_user_id', $inputs['id'])->where('status', 1)->first();
            if(empty($user_score_info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $score_tmp = [];
            foreach ($inputs['score'] as $value) {
                if(empty($value)) $value = 0;
                $score_tmp[] = $value;
            }
            $inputs['score'] = $score_tmp;
            $user_score_info->score = implode(',', $inputs['score']);
            $total_score = 0;//评分人个人总评分
            foreach ($inputs['score'] as $value) {
                $total_score += sprintf('%.2f', ($value * ($user_score_info->percent / 100)));
            }
            $user_score_info->total_score = $total_score;
            $user_score_info->remarks = $inputs['remarks'];
            $res = false;
            DB::transaction(function () use ($user_score_info, $user_score, $info) {
                $user_score_info->save();
                $if_over = $user_score->where('assign_user_id', $user_score_info->assign_user_id)->where('status',0)->orderBy('id', 'asc')->first();
                if(empty($if_over)){
                    //统计总分数
                    $sum  = $user_score->where('assign_user_id', $user_score_info->assign_user_id)->sum('total_score');
                    $info->total_score = $sum + 100;//加上基数100
                    $info->save();
                }
            }, 5);
            $res = true;
            if($res){
                systemLog('绩效', '对'.$user_data['id_realname'][$info->user_id].'的绩效评分进行了修改');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$th = explode(',', $info->th);
    		$tbody = unserialize($info->tbody);
    		$other_th_score = array();
    		$if_view_other = false;
    		foreach ($info->hasScore as $key => $value) {
    			if($value->score_user_id == $cur_user_id && $value->if_view == 1){
    				//当前评分人是否可以查看他人评分 1可以  2不可以
    				$if_view_other = true;
    			}
    		}
    		$user_score_total = array(); 
    		foreach ($info->hasScore as $key => $value) {
    			if(!empty($value->score) && $if_view_other){
    				$other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
    				$score_tmp = explode(',', $value->score);
    				foreach ($tbody as $k => $v) {
    					$user_score_total[$k] = $user_score_total[$k] ?? 0;
    					$user_score_total[$k] += sprintf('%.2f', ($score_tmp[$k] * ($value->percent / 100)));//得分
    					array_push($tbody[$k], $score_tmp[$k]);//追加分数
    				}
    			}
    		}
            $total_score = 0;
            foreach ($tbody as $k => $v) {
                if(isset($user_score_total[$k])){
                    $user_score_total[$k] = sprintf('%.2f', $user_score_total[$k]);
                    array_push($tbody[$k], $user_score_total[$k]);//追加评分
                    $total_score += sprintf('%.2f',$user_score_total[$k]);
                }else{
                    array_push($tbody[$k], 0);//追加评分
                    $total_score += 0;
                }
            }
    		$th = array_merge($th, $other_th_score, ['总评分']);
    		$tmp['th'] = $th;
            $tmp['tbody'] = $tbody;
    		$tmp['total_score'] = $total_score+100;//加上基数100分
    		
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
    			$score_users[$key]['percent'] = $value->percent.'%';
    			$score_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['score_users'] = $score_users;
    		// $verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($info->hasVerify as $key => $value) {
                $tmp_verify_users[$key]['realname'] = $user_data['id_realname'][$value->verify_user_id];
    			$tmp_verify_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'score_edit'){
            //加载编辑评分
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $inputs['edit_score'] = 0;//可编辑评分
            $info = $assign_users->getDataInfo($inputs);
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $tmp = array();
            $tmp['id'] = $info->id;
            $tmp['name'] = $info->name;
            $tmp['realname'] = $user_data['id_realname'][$info->user_id];
            $tmp['year_month'] = $info->year_month;
            $th = explode(',', $info->th);
            $tbody = $a_tbody = unserialize($info->tbody);
            $other_th_score = array();
            $if_view_other = false;
            $own_score = array();
            foreach ($info->hasScore as $key => $value) {
                if($value->score_user_id == $cur_user_id && $value->if_view == 1){
                    //当前评分人是否可以查看他人评分 1可以  2不可以
                    $if_view_other = true;
                }
            }
            foreach ($info->hasScore as $key => $value) {
                if(!empty($value->score) && $if_view_other && $value->score_user_id != $cur_user_id){
                    $other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
                    $score_tmp = explode(',', $value->score);
                    foreach ($tbody as $k => $v) {
                        array_push($tbody[$k], $score_tmp[$k]);//追加最后一个元素
                    }
                }
                if($value->score_user_id == $cur_user_id){
                    if(!empty($value->score)) $score_tmp = explode(',', $value->score);
                    foreach ($a_tbody as $k => $v) {
                        $own_score[$k] = $score_tmp[$k] ?? 0;//自己的评分
                    }
                }
            }
            $th = array_merge($th, $other_th_score);
            $tmp['th'] = $th;
            $tmp['tbody'] = $tbody;
            $tmp['own_score'] = $own_score;
            
            $score_users = array();
            $cur_remarks = '';
            foreach ($info->hasScore as $key => $value) {
                $score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
                $score_users[$key]['percent'] = $value->percent.'%';
                $score_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
                if($cur_user_id == $value->score_user_id){
                    $cur_remarks = $value->remarks ? $value->remarks : '';//当前评分用户的备注
                }
            }
            $tmp['score_users'] = $score_users;
            // $verify_users = explode(',', $info->verify_user_ids);
            $tmp_verify_users = array();
            foreach ($info->hasVerify as $key => $value) {
                $tmp_verify_users[$key]['realname'] = $user_data['id_realname'][$value->verify_user_id];
                $tmp_verify_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
            }
            $tmp['verfiy_users'] = $tmp_verify_users;
            $tmp['remarks'] = $cur_remarks;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
        }
    	if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
    		$dt_start = strtotime($inputs['start_time']);
	        $dt_end = strtotime($inputs['end_time']);
	        $months = [];
	        while ($dt_start <= $dt_end) {
	            $months[] = date('Y-m', $dt_start);
	            $dt_start = strtotime('+1 month', $dt_start);
	        }
	        $inputs['in_year_month'] = $months;
    	}
    	$inputs['in_status'] = [0,1,2,3];//去掉已撤销
        $user_score = new \App\Models\AchievementUserScore;
        $inputs['ids'] = $user_score->where('score_user_id', $cur_user_id)->pluck('assign_user_id')->toArray();//只有评分人
        $data = $assign_users->getDataList($inputs);
        $assign_user_ids = array();
        foreach ($data['datalist'] as $key => $value) {
            $assign_user_ids[] = $value->id;
        }
        $all_score_user = $user_score->whereIn('assign_user_id', $assign_user_ids)->select(['assign_user_id','score_user_id','status'])->get();
        $score_status = array();
        foreach ($all_score_user as $key => $value) {
            $score_status[$value->assign_user_id][$value->score_user_id] = $value->status;
        }
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['realname'] = $user_data['id_realname'][$value->user_id];
    		$items['dept'] = $dept_data['id_name'][$value->dept_id];
    		$items['year_month'] = $value->year_month;
    		if($value->total_score > 0){
    			$items['total_score'] = $value->total_score;
    		}else{
    			$items['total_score'] = '--';
    		}
    		$items['status'] = $value->status;
    		$items['if_edit'] = $cur_user_id == $value->hasAssign->assign_user_id ? $value->if_edit : 2;//是否可以编辑内容[不是分派人不可以编辑]
            if($value->status != 0){
                $items['if_edit'] = 2;//待确认内容时才可编辑
            }
    		$items['if_score'] = 1;//是否显示评分按钮 0显示 1不可显示
            $items['edit_score'] = 1;//是否可以编辑评分 0显示编辑评分按钮  1隐藏编辑评分按钮
    		if($value->status == 0){
    			$items['status_txt'] = '确认内容中';
    		}else if($value->status == 1){
    			$items['status_txt'] = '评分中';
    			if($score_status[$value->id][$cur_user_id] == 0){
    				$items['if_score'] = 0;//当前评分人显示评分
    			}else{
                    $items['edit_score'] = 0;//当前评分人显示修改评分按钮
                }
    		}else if($value->status == 2){
                $items['edit_score'] = $value->edit_score;
    			$items['status_txt'] = '审核中';
    		}else if($value->status == 3){
    			$items['status_txt'] = '已完成';
    		}else if($value->status == 4){
    			$items['status_txt'] = '已撤销';
    		}
    		$data['datalist'][$key] = $items;
    	}
    	$data['status'] = [['id'=>0,'name'=>'考核内容确认内容'],['id'=>1,'name'=>'考核人评分中'],['id'=>2,'name'=>'审核中'],['id'=>3,'name'=>'已完成'],['id'=>4,'name'=>'已撤销']];
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  绩效审核
    *  @author molin
    *	@date 2018-12-11
    */
    public function verify(){
    	$inputs = request()->all();
    	$cur_user_id = auth()->user()->id;
    	$assign_users = new \App\Models\AchievementAssignUser;
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$dept = new \App\Models\Dept;
    	$dept_data = $dept->getIdToData();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'verify_load'){
    		//加载审核
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$inputs['cur_user_id'] = $cur_user_id;
    		$inputs['status'] = 2;//评分中
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$th = explode(',', $info->th);
    		$tbody = unserialize($info->tbody);
    		$other_th_score = array();
    		$user_score_total = array();
    		foreach ($info->hasScore as $key => $value) {
    			if(!empty($value->score)){
    				$other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
    				$score_tmp = explode(',', $value->score);
    				foreach ($tbody as $k => $v) {
    					$user_score_total[$k] = $user_score_total[$k] ?? 0;
    					$user_score_total[$k] += sprintf('%.2f', ($score_tmp[$k] * ($value->percent / 100)));//得分
    					array_push($tbody[$k], $score_tmp[$k]);//追加分数
    				}
    			}
    		}
            $total_score = 0;
            foreach ($tbody as $k => $v) {
                if(isset($user_score_total[$k])){
                    $user_score_total[$k] = sprintf('%.2f', $user_score_total[$k]);
                    array_push($tbody[$k], $user_score_total[$k]);//追加评分
                    $total_score += sprintf('%.2f',$user_score_total[$k]);
                }else{
                    array_push($tbody[$k], 0);//追加评分
                    $total_score += 0;
                }
            }
    		$th = array_merge($th, $other_th_score, ['总评分']);
    		$tmp['th'] = $th;
            $tmp['tbody'] = $tbody;
    		$tmp['total_score'] = $total_score+100;//加上基数100分
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
    			$score_users[$key]['percent'] = $value->percent.'%';
    			$score_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['score_users'] = $score_users;
    		// $verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($info->hasVerify as $key => $value) {
                $tmp_verify_users[$key]['realname'] = $user_data['id_realname'][$value->verify_user_id];
    			$tmp_verify_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'verify_save'){
    		//保存审核
    		if(!isset($inputs['id']) || empty($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		if(!isset($inputs['type']) || empty($inputs['type'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数type']);
    		}	
  
    		$inputs['cur_user_id'] = $cur_user_id;
	        $inputs['status'] = 2;
	        $info = $assign_users->getDataInfo($inputs);
	        if(empty($info)){
	        	return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
	        }
	        $user_verify = new \App\Models\AchievementUserVerify;
	        $user_score = new \App\Models\AchievementUserScore;
            $user_verify_info = $user_verify->where('verify_user_id', $cur_user_id)->where('assign_user_id', $inputs['id'])->orderBy('id', 'asc')->where('status', 0)->first();
            if(empty($user_verify_info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
    		if($inputs['type'] == 'pass'){
    			//通过
                $user_verify_info->status = 1;
	    		$user_verify_info->remarks = isset($inputs['remarks']) && !empty($inputs['remarks']) ? '通过--'.$inputs['remarks'] : '通过';
    			$user_verify_info->save();
    			$if_over = $user_verify->where('assign_user_id', $inputs['id'])->orderBy('id', 'asc')->where('status', 0)->first();
    			if(empty($if_over)){
    				//审核完成
    				$info->status = 3;
    			}else{
    				//下一个审核人
    				$info->cur_user_id = $if_over->verify_user_id;//下一个审核人
    			}
                $info->edit_score = 1;//不可修改评分
    			$result = $info->save();
    			if($result){
                    systemLog('绩效', '审核了'.$user_data['id_realname'][$info->user_id].'的绩效');
                    if($info->status == 3){
                        addNotice($info->user_id, '绩效', '您的绩效审核已完成', '', 0, 'achievement-list-index','achievement_user/index');//提醒被考核人
                    }else{
                        addNotice($info->cur_user_id, '绩效', '['.$user_data['id_realname'][$info->user_id].']的绩效需要审核', '', 0, 'achievement-list-audit','achievement_user/verify');//提醒审核人
                    }
    				return response()->json(['code' => 1, 'message' => '操作成功']);
    			}
    			return response()->json(['code' => 0, 'message' => '操作失败']);
    		}else if($inputs['type'] == 'return_score'){
    			//驳回至评分
                if(!isset($inputs['remarks']) || empty($inputs['remarks'])){
                    return response()->json(['code' => -1, 'message' => '备注字段必填']);
                }  
                $info->status = 1;//评分中
                $score_user_ids = explode(',', $info->score_user_ids);
                $info->cur_user_id = $score_user_ids[0];
                $info->edit_score = 0;
    			$info->total_score = 0;
                $user_verify_info->remarks = '驳回到评分--'.$inputs['remarks'];
                $user_verify_info->save();
    			$res = false;
    			DB::transaction(function () use ($info, $user_verify, $user_score, $user_data, $score_user_ids) {
	    			$info->save();
	    			$user_score->where('assign_user_id', $info->id)->update(['score'=>'','status'=>0,'total_score'=>0, 'updated_at'=>date('Y-m-d H:i:s')]);
	    			$user_verify->where('assign_user_id', $info->id)->update(['status'=>0, 'updated_at'=>date('Y-m-d H:i:s')]);
	    		}, 5);
	    		$res = true;
	    		if($res){
                    systemLog('绩效', '驳回了'.$user_data['id_realname'][$info->user_id].'的绩效审核');
                    addNotice($score_user_ids, '绩效', '您有一条绩效待评分', '', 0, 'achievement-list-score','achievement_user/score');//提醒评分人
	    			return response()->json(['code' => 1, 'message' => '操作成功']);
	    		}
	    		return response()->json(['code' => 0, 'message' => '操作失败']);
    		}else if($inputs['type'] == 'return_confirm'){
    			//驳回至确认内容
                if(!isset($inputs['remarks']) || empty($inputs['remarks'])){
                    return response()->json(['code' => -1, 'message' => '备注字段必填']);
                }  
    			$info->if_edit = 1;//确认内容中
    			$info->status = 0;
    			$info->total_score = 0;
    			$score_user_ids = explode(',', $info->score_user_ids);
                $info->cur_user_id = $score_user_ids[0];
    			$info->edit_score = 0;
                $user_verify_info->remarks = '驳回到确认内容--'.$inputs['remarks'];
                $user_verify_info->save();
    			$res = false;
    			DB::transaction(function () use ($info, $user_verify, $user_score, $user_data, $score_user_ids) {
	    			$info->save();
	    			$user_score->where('assign_user_id', $info->id)->update(['score'=>'','status'=>0,'total_score'=>0, 'updated_at'=>date('Y-m-d H:i:s')]);
	    			$user_verify->where('assign_user_id', $info->id)->update(['status'=>0, 'updated_at'=>date('Y-m-d H:i:s')]);
	    		}, 5);
	    		$res = true;
	    		if($res){
                    systemLog('绩效', '驳回了'.$user_data['id_realname'][$info->user_id].'的绩效审核');
                    addNotice($info->user_id, '绩效', '您有一条绩效内容待确认', '', 0, 'achievement-list-index','achievement_user/index');//提醒被考核人
                    addNotice($score_user_ids, '绩效', '您有一条绩效待评分', '', 0, 'achievement-list-score','achievement_user/score');//提醒评分人
	    			return response()->json(['code' => 1, 'message' => '操作成功']);
	    		}
	    		return response()->json(['code' => 0, 'message' => '操作失败']);
    		}
    		return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$th = explode(',', $info->th);
    		$tbody = unserialize($info->tbody);
    		$other_th_score = array();
    		$user_score_total = array(); 
    		foreach ($info->hasScore as $key => $value) {
    			if(!empty($value->score)){
    				$other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
    				$score_tmp = explode(',', $value->score);
    				foreach ($tbody as $k => $v) {
    					$user_score_total[$k] = $user_score_total[$k] ?? 0;
    					$user_score_total[$k] += sprintf('%.2f', ($score_tmp[$k] * ($value->percent / 100)));//得分
    					array_push($tbody[$k], $score_tmp[$k]);//追加得分
    				}
    			}
    		}
            $total_score = 0;
            foreach ($tbody as $k => $v) {
                if(isset($user_score_total[$k])){
                    $user_score_total[$k] = sprintf('%.2f', $user_score_total[$k]);
                    array_push($tbody[$k], $user_score_total[$k]);//追加评分
                    $total_score += sprintf('%.2f',$user_score_total[$k]);
                }else{
                    array_push($tbody[$k], 0);//追加评分
                    $total_score += 0;
                }
            }
    		$th = array_merge($th, $other_th_score, ['总评分']);
    		$tmp['th'] = $th;
            $tmp['tbody'] = $tbody;
    		$tmp['total_score'] = $total_score+100;//加上基数100分
    		
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
    			$score_users[$key]['percent'] = $value->percent.'%';
    			$score_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['score_users'] = $score_users;
    		// $verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($info->hasVerify as $key => $value) {
                $tmp_verify_users[$key]['realname'] = $user_data['id_realname'][$value->verify_user_id];
    			$tmp_verify_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}
    	if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
    		$dt_start = strtotime($inputs['start_time']);
	        $dt_end = strtotime($inputs['end_time']);
	        $months = [];
	        while ($dt_start <= $dt_end) {
	            $months[] = date('Y-m', $dt_start);
	            $dt_start = strtotime('+1 month', $dt_start);
	        }
	        $inputs['in_year_month'] = $months;
    	}
    	$inputs['status'] = 2;//待审核
        $user_verify = new \App\Models\AchievementUserVerify;
        $inputs['ids'] = $user_verify->where('verify_user_id', $cur_user_id)->pluck('assign_user_id')->toArray();//只有审核人
    	$data = $assign_users->getDataList($inputs);
    	
    	foreach ($data['datalist'] as $key => $value) {
            $items = array();
    		$items['id'] = $value->id;
    		$items['realname'] = $user_data['id_realname'][$value->user_id];
    		$items['dept'] = $dept_data['id_name'][$value->dept_id];
    		$items['year_month'] = $value->year_month;
    		if($value->total_score > 0){
    			$items['total_score'] = $value->total_score;
    		}else{
    			$items['total_score'] = '--';
    		}
    		$items['status'] = $value->status;
    		$items['if_verify'] = 1;//是否显示评分按钮 0显示 1不可显示
    		if($value->status == 0){
    			$items['status_txt'] = '确认内容中';
    		}else if($value->status == 1){
    			$items['status_txt'] = '评分中';
    		}else if($value->status == 2){
    			$items['status_txt'] = '审核中';
    			if($value->cur_user_id == $cur_user_id){
    				$items['if_verify'] = 0;//当前审核人 = 当前用户时才显示审核按钮
    			}
    		}else if($value->status == 3){
    			$items['status_txt'] = '已完成';
    		}else if($value->status == 4){
    			$items['status_txt'] = '已撤销';
    		}
    		$data['datalist'][$key] = $items;
    	}
    	$data['status'] = [['id'=>0,'name'=>'考核内容确认内容'],['id'=>1,'name'=>'考核人评分中'],['id'=>2,'name'=>'审核中'],['id'=>3,'name'=>'已完成'],['id'=>4,'name'=>'已撤销']];
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  绩效汇总
    *  @author molin
    *	@date 2018-12-12
    */
    public function list(){
    	$inputs = request()->all();
    	$assign_users = new \App\Models\AchievementAssignUser;
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $assign_users->getDataInfo($inputs);
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		$tmp = array();
    		$tmp['id'] = $info->id;
    		$tmp['name'] = $info->name;
    		$tmp['realname'] = $user_data['id_realname'][$info->user_id];
    		$tmp['year_month'] = $info->year_month;
    		$th = explode(',', $info->th);
            $tbody = unserialize($info->tbody);
            $other_th_score = array();
            $user_score_total = array(); 
            foreach ($info->hasScore as $key => $value) {
                if(!empty($value->score)){
                    $other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
                    $score_tmp = explode(',', $value->score);
                    foreach ($tbody as $k => $v) {
                        $user_score_total[$k] = $user_score_total[$k] ?? 0;
                        $user_score_total[$k] += sprintf('%.2f', ($score_tmp[$k] * ($value->percent / 100)));//得分
                        array_push($tbody[$k], $score_tmp[$k]);//追加评分
                    }
                }
            }
            $total_score = 0;
            foreach ($tbody as $k => $v) {
                if(isset($user_score_total[$k])){
                    $user_score_total[$k] = sprintf('%.2f', $user_score_total[$k]);
                    array_push($tbody[$k], $user_score_total[$k]);//追加评分
                    $total_score += sprintf('%.2f',$user_score_total[$k]);
                }else{
                    array_push($tbody[$k], 0);//追加评分
                    $total_score += 0;
                }
            }
            // dd($tbody);
            $th = array_merge($th, $other_th_score, ['总评分']);
    		$tmp['th'] = $th;
    		$tmp['tbody'] = $tbody;
    		$tmp['total_score'] = $total_score+100;//加上基数100分
    		$score_users = array();
    		foreach ($info->hasScore as $key => $value) {
    			$score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
    			$score_users[$key]['percent'] = $value->percent.'%';
    			$score_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['score_users'] = $score_users;
    		// $verify_users = explode(',', $info->verify_user_ids);
    		$tmp_verify_users = array();
    		foreach ($info->hasVerify as $key => $value) {
                $tmp_verify_users[$key]['realname'] = $user_data['id_realname'][$value->verify_user_id];
    			$tmp_verify_users[$key]['remarks'] = $value->remarks ? $value->remarks : '';
    		}
    		$tmp['verfiy_users'] = $tmp_verify_users;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
    	}
    	
    	if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
    		$dt_start = strtotime($inputs['start_time']);
	        $dt_end = strtotime($inputs['end_time']);
	        $months = [];
	        while ($dt_start <= $dt_end) {
	            $months[] = date('Y-m', $dt_start);
	            $dt_start = strtotime('+1 month', $dt_start);
	        }
	        $inputs['in_year_month'] = $months;
    	}
    	if(isset($inputs['keywords']) && !empty($inputs['keywords'])){
    		$user_ids = $user->where('realname', 'like', '%'.$inputs['keywords'].'%')->orWhere('username', 'like', '%'.$inputs['keywords'].'%')->pluck('id')->toArray();
    		$inputs['user_ids'] = $user_ids;
    	}
    	$data = $assign_users->getDataList($inputs);
    	//部门
    	$dept = new \App\Models\Dept;
    	$dept_list = $dept->select(['id', 'name'])->get();
    	$data['dept_list'] = $dept_list;
    	$dept_data = $dept->getIdToData();

    	foreach ($data['datalist'] as $key => $value) {
            $items = array();
    		$items['id'] = $value->id;
    		$items['realname'] = $user_data['id_realname'][$value->user_id];
    		$items['dept'] = $dept_data['id_name'][$value->dept_id];
    		$items['month'] = $value->year.'-'.$value->month;
    		if($value->total_score > 0){
    			$items['total_score'] = $value->total_score;
    		}else{
    			$items['total_score'] = '--';
    		}
    		$items['status'] = $value->status;
    		if($value->status == 0){
    			$items['status_txt'] = '确认内容中';
    		}else if($value->status == 1){
    			$items['status_txt'] = '评分中';
    		}else if($value->status == 2){
    			$items['status_txt'] = '审核中';
    		}else if($value->status == 3){
    			$items['status_txt'] = '已完成';
    		}else if($value->status == 4){
    			$items['status_txt'] = '已撤销';
    		}
    		$data['datalist'][$key] = $items;
    	}
    	$data['status'] = [['id'=>0,'name'=>'考核内容确认内容'],['id'=>1,'name'=>'考核人评分中'],['id'=>2,'name'=>'审核中'],['id'=>3,'name'=>'已完成'],['id'=>4,'name'=>'已撤销']];
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  绩效统计
    *  @author molin
    *	@date 2018-12-12
    */
    public function statistics(){
    	$inputs = request()->all();
    	$start_month = strtotime(date('Y-01-01'));//默认获取当年时间
    	$end_month = strtotime(date('Y-12-01'));
    	if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
    		$start_month = strtotime($inputs['start_time']);
	        $end_month = strtotime($inputs['end_time']);
	        
    	}
    	$months = [];
        while ($start_month <= $end_month) {
            $ym = date('Y-m', $start_month);
            $months[$ym] = $ym;
            $start_month = strtotime('+1 month', $start_month);
        }
        $inputs['in_year_month'] = $months;
        $user = new \App\Models\User;
        if(isset($inputs['keywords']) && !empty($inputs['keywords'])){
    		$user_ids = $user->where('realname', 'like', '%'.$inputs['keywords'].'%')->orWhere('username', 'like', '%'.$inputs['keywords'].'%')->pluck('id')->toArray();
    		$inputs['user_ids'] = $user_ids;
    	}
    	$assign_users = new \App\Models\AchievementAssignUser;
        $inputs['all'] = 1;
    	$inputs['in_status'] = [0,1,2,3];
    	$list = $assign_users->getDataList($inputs);
    	$user_score_data = array();
    	foreach ($list['datalist'] as $key => $value) {
    		$user_score_data[$value['user_id']][$value['year_month']] = $value['total_score'] > 0 ? $value['total_score'] : '--';
    	}
    	$data = $user->getDataList($inputs);
    	$th = ['dept'=>'部门','realname'=>'姓名'];
    	$th = array_merge($th, $months);
    	$export_data = array();
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
        	$tmp['dept'] = $value->dept->name;
        	$tmp['realname'] = $value->realname;
        	foreach ($months as $m) {
    			$tmp[$m] = $user_score_data[$value->id][$m] ?? '--';
    		}
    		$data['datalist'][$key] = $tmp;
    		$export_data[] = $tmp;
        }
        if(isset($inputs['export']) && $inputs['export'] == 1){
        	//导出
            $filedata = pExprot($th, $export_data,'achievement_statistics');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        $data['header'] = $th;
        $dept = new \App\Models\Dept;
        $dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
        $data['dept_list'] = $dept_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }
}

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyTrainingController extends Controller
{

    /*
    * 培训申请
    * @author molin
    * @date 2018-10-010
    */
    public function store(){
    	$inputs = request()->all();
        $training = new \App\Models\ApplyTraining;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'training'){
    		$data = array();
    		$user = new \App\Models\User;
    		$user_list = $user->where('status', 1)->select(['id','realname'])->get();
    		$projects = new \App\Models\TrainingProject;
    		$project_list = $projects->where('status', 1)->select(['id','name'])->get();
    		$data['user_list'] = $user_list;
    		$data['project_list'] = $project_list;
    		$addr = new \App\Models\TrainingAddr;
    		$addr_list = $addr->get();
    		$data['addr_list'] = $addr_list;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'check'){
    		$data = array();
    		if(!isset($inputs['addr_id']) || !is_numeric($inputs['addr_id'])){
    			return response()->json(['code' => -1, 'message' => '缺少地点id']);
    		}
    		if(!isset($inputs['start_time']) || empty($inputs['start_time'])){
    			return response()->json(['code' => -1, 'message' => '培训开始时间']);
    		}
    		if(!isset($inputs['end_time']) || empty($inputs['end_time'])){
    			return response()->json(['code' => -1, 'message' => '培训结束时间']);
    		}
            //申请列表是否已占用
    		$if_exist = $training->whereIn('status', [0,1])->where('addr_id',$inputs['addr_id'])->whereBetween('end_time', [$inputs['start_time'], $inputs['end_time']])->first();
    		if($if_exist){
    			return response()->json(['code' => -1, 'message' => '该时间段内培训地点被占用，请另选时间']);
    		}
            //安排列表是否已占用
            $if_exist2 = (new \App\Models\TrainingList)->where('addr_id',$inputs['addr_id'])->whereBetween('end_time', [$inputs['start_time'], $inputs['end_time']])->first();
            if($if_exist2){
                return response()->json(['code' => -1, 'message' => '该时间段内培训地点被占用，请另选时间']);
            }
    		return response()->json(['code' => 1, 'message' => '该时间段内培训地点可用']);
    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $training::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
    	if($inputs['type_id'] == 1){
    		//入职培训
	    	$rules = [
	            'type_id' => 'required|integer',
	            'name' => 'required',
	            'content' => 'required|array'
	        ];
	        $attributes = [
	            'type_id' => '培训类型',
	            'name' => '培训名称',
	            'content' => '被培训人，培训计划'
	        ];

    	}else if($inputs['type_id'] == 2){
    		//拓展培训
    		$if_exist = $training->whereIn('status', [0,1])->whereBetween('end_time', [$inputs['start_time'], $inputs['end_time']])->first();
    		if($if_exist){
    			return response()->json(['code' => -1, 'message' => '该时间段内培训地点被占用，请另选时间']);
    		}
    		$rules = [
	            'type_id' => 'required|integer',
	            'name' => 'required',
	            'content' => 'required|array',
	            'addr_id' => 'required|integer',
	            'start_time' => 'required',
	            'end_time' => 'required',
	        ];
	        $attributes = [
	            'type_id' => '培训类型',
	            'name' => '培训名称',
	            'content' => '被培训人，培训计划',
	            'addr_id' => '地点',
	            'start_time' => '培训开始时间',
	            'end_time' => '培训结束时间'
	        ];
    	}

    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $training::type)->orderBy('id', 'desc')->first();//获取最新的配置
    	if(empty($setting_info)){
    		return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
    	}
        $steps = new \App\Models\AuditProcessStep;
        $step1 = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();
        if(empty($step1)){
            return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
        }
    	$setting_info['setting_content'] = unserialize($setting_info['setting_content']);
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $projects = new \App\Models\TrainingProject;
        $projects_list = $projects->select(['id','explain_people'])->get();
        $projects_list = $projects_list ? $projects_list : array();
        $projects_explain_people = array();
        foreach ($projects_list as $key => $value) {
        	$projects_explain_people[$value['id']] = $value['explain_people'];
        }
        $by_training_users = $training_users = array();
        foreach ($inputs['content'] as $key => $value) {
        	$response = $this->inputsChecked($value,$inputs['type_id']);
			if($response['code'] != 1){
				return response()->json($response);
			}
			if($inputs['type_id'] == 1){
				$by_training_users[$value['by_training_user']] = $user_data['id_realname'][$value['by_training_user']];//保存用于关键词搜索
				foreach ($value['training_projects'] as $projects_id) {
					if(isset($projects_explain_people[$projects_id])){
						$training_users[$projects_explain_people[$projects_id]] = $user_data['id_realname'][$projects_explain_people[$projects_id]];
					}
				}
				
			}else if($inputs['type_id'] == 2){
				foreach ($value['by_training_user'] as $val) {
					$by_training_users[$val] = $user_data['id_realname'][$val];//保存用于关键词搜索
				}
				$training_users[$value['training_user']] = $user_data['id_realname'][$value['training_user']];
			}
			
        }
        $inputs['by_training_users'] = implode(',', $by_training_users);
        $inputs['training_users'] = implode(',', $training_users);
        $inputs['user_id'] = auth()->user()->id;
        $inputs['dept_id'] = auth()->user()->dept_id;
        
        $result = $training->storeData($inputs, $setting_info);
        if($result){
            systemLog('培训申请', '提交了培训申请['.$inputs['by_training_users'].']');
        	return response()->json(['code' => 1, 'message' => '申请成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败,最后一步审核人不能申请']);
    }

    
    /** 
    *  培训内容--被培训人、培训计划
    *  @author molin
    *	@date 2018-10-10
    */
    public function inputsChecked($value, $type_id){
    	if($type_id == 1){
    		//入职培训
    		if(!isset($value['by_training_user']) || empty($value['by_training_user'])){
	    		return $response = ['code' => -1, 'message' => '缺少被培训人'];
	    	}
	    	if(!isset($value['training_projects']) || !is_array($value['training_projects'])){
	    		return $response = ['code' => -1, 'message' => '缺少培训计划'];
	    	}
    	}else if($type_id == 2){
    		//拓展培训
    		if(!isset($value['training_projects']) || empty($value['training_projects'])){
	    		return $response = ['code' => -1, 'message' => '缺少培训计划'];
	    	}
	    	if(!isset($value['training_user']) || empty($value['training_user'])){
	    		return $response = ['code' => -1, 'message' => '缺少培训人'];
	    	}
	    	if(!isset($value['by_training_user']) || !is_array($value['by_training_user'])){
	    		return $response = ['code' => -1, 'message' => '缺少被培训人'];
	    	}
    	}
    	
    	return $response = ['code' => 1, 'message' => '验证通过'];
    }

    /** 
    *  查看详情页面
    *  @author molin
    *	@date 2018-10-11
    */
    public function show(){
    	$inputs = request()->all();
    	$user_info = auth()->user();
    	$audit_proces = new \App\Models\AuditProces;
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
			return response()->json(['code' => -1, 'message' => '缺少参数id']);
		}
        $inputs['apply_id'] = $inputs['id'];
        unset($inputs['id']);
		$proces_info = $audit_proces->getTrainingInfo($inputs);
		//拼接数据
		$data = $apply_training = array();
		//申请信息
    	$apply_training['name'] = $proces_info->applyTraining->name;
    	$content = unserialize($proces_info->applyTraining->content);
    	$type_id = $proces_info->applyTraining->type_id;
    	$by_training_users = $training_users = $training_projects = $projects_ids = $supervision_peoples = array();
    	foreach ($content as $key => $value) {
    		if($type_id == 1){
    			$by_training_users[] = $user_data['id_realname'][$value['by_training_user']];
    			foreach ($value['training_projects'] as $val) {
    				//$training_projects[] = $user_data['id_realname'][$val];
    				$projects_ids[] = $val;
    			}
    		}
    		if($type_id == 2){
    			foreach($value['by_training_user'] as $val){
    				$by_training_users[] = $user_data['id_realname'][$val];
    			}
    			$training_users[] = $user_data['id_realname'][$value['training_user']];
    			$training_projects[] = $value['training_projects'];
    		}
    	}
    	
    	if(!empty($projects_ids) && $type_id == 1){
    		$training = new \App\Models\TrainingProject;
	    	$projects_list = $training->whereIn('id', $projects_ids)->select(['id','name','explain_people','supervision_people','time'])->get();
	    	if(empty($projects_list)){
	    		return response()->json(['code' => 0, 'message' => '培训项目不存在']);
	    	}
	    	foreach ($projects_list as $key => $value) {
	    		$training_projects[] = $value['name'];//讲解人
	    		$training_users[] = $user_data['id_realname'][$value['explain_people']];//讲解人
	    		$supervision_peoples[] = $user_data['id_realname'][$value['supervision_people']];//监督人
	    	}
    	}
    	$apply_training['by_training_users'] = implode(',', $by_training_users);
    	$apply_training['training_projects'] = implode(',', $training_projects);
    	$apply_training['training_users'] = implode(',', $training_users);
    	if($type_id == 1){
    		$apply_training['type'] = '入职培训';
    	}else if($type_id == 2){
    		$apply_training['type'] = '拓展培训';
    	}else{
    		$apply_training['type'] = '未知';
    	}
    	//地点 时间
    	$addr = new \App\Models\TrainingAddr;
    	$addr_list = $addr->get();
    	$addr_arr = array();
    	foreach ($addr_list as $key => $value) {
    		$addr_arr[$value['id']] = $value['name']; 
    	}

    	$apply_training['addr_info'] = $addr_arr[$proces_info->applyTraining->addr_id] ?? '';
    	$apply_training['start_time'] = $proces_info->applyTraining->start_time ? $proces_info->applyTraining->start_time : '';
    	$apply_training['end_time'] = $proces_info->applyTraining->end_time ? $proces_info->applyTraining->end_time : '';
    	$data['apply_training'] = $apply_training;
    	//加载已经审核的人的评价
    	$pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyTraining)::type);
    	$data['pre_audit_opinion'] = $audit_opinions = array();
    	if(!empty($pre_verify_users_data)){
    		foreach ($pre_verify_users_data as $key => $value) {
    			$audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
    			$audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
    		}
    		$data['pre_audit_opinion'] = $audit_opinions;
    	}
		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
	}

	

    /** 
    *  培训管理主体
    *  @author molin
    *   @date 2018-10-12
    */
    public function index(){
    	$inputs = request()->all();
    	$training = new \App\Models\TrainingList;
    	$training_list = $training->getDataList($inputs);

    	$projects = new \App\Models\TrainingProject;
    	$projects_list = $projects->getIdToData();
    	$addr = new \App\Models\TrainingAddr;
    	$addr_data = $addr->getAddrData();
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$tmp = array();
    	foreach ($training_list['datalist'] as $key => $value) {
    		$tmp['id'] = $value['id'];
    		if($value['type_id'] == 1){
    			$tmp['type'] = '入职培训';
    		}else{
    			$tmp['type'] = '拓展培训';
    		}
    		$tmp['name'] = $value['name'];
    		$tmp['training_user'] = $user_data['id_realname'][$value['training_user']];//讲解人
    		$tmp['by_training_user'] = $user_data['id_realname'][$value['by_training_user']];//被培训人
    		if(is_numeric($value['training_project'])){
    			$tmp['training_project'] = $projects_list['id_name'][$value['training_project']];
    		}else{
    			$tmp['training_project'] = $value['training_project'];
    		}
    		$tmp['time_str'] = $value['start_time'].'~'.$value['end_time'];
    		$tmp['addr_info'] = $addr_data[$value['addr_id']] ?? '';
    		if(time() > strtotime($value['start_time']) && time() < strtotime($value['end_time']) && !empty($value['start_time']) && !empty($value['end_time'])){
    			$tmp['status_xx'] = '进行中';
    		}else if(time() > strtotime($value['end_time']) && !empty($value['end_time'])){
    			$tmp['status_xx'] = '已结束';
    		}else if(time() < strtotime($value['start_time']) && !empty($value['start_time'])){
    			$tmp['status_xx'] = '未开始';
    		}else{
    			$tmp['time_str'] = '--';
    			$tmp['status_xx'] = '未安排';
    		}

    		if($value['score'] > 0){
    			$tmp['score'] = $value['score'];
    		}else{
    			$tmp['score'] = '--';
    		}
    		$training_list['datalist'][$key] = $tmp;
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $training_list]);
    }

    /** 
    *  我参加的培训
    *  @author molin
    *   @date 2018-10-15
    */
    public function myList(){
    	$inputs = request()->all();
    	$inputs['by_or_training_user'] = auth()->user()->id;
    	$training = new \App\Models\TrainingList;
    	$training_list = $training->getDataList($inputs);

    	$projects = new \App\Models\TrainingProject;
    	$projects_list = $projects->getIdToData();
    	$addr = new \App\Models\TrainingAddr;
    	$addr_data = $addr->getAddrData();
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$tmp = array();
    	foreach ($training_list['datalist'] as $key => $value) {
    		$tmp['id'] = $value['id'];
    		if($value['type_id'] == 1){
    			$tmp['type'] = '入职培训';
    		}else{
    			$tmp['type'] = '拓展培训';
    		}
    		$tmp['name'] = $value['name'];
    		$tmp['training_user'] = $user_data['id_realname'][$value['training_user']];//讲解人
            $tmp['if_comment'] = 0;//不能评分
    		$tmp['by_training_user'] = $user_data['id_realname'][$value['by_training_user']];//被培训人
    		if(is_numeric($value['training_project'])){
    			$tmp['training_project'] = $projects_list['id_name'][$value['training_project']];
    		}else{
    			$tmp['training_project'] = $value['training_project'];
    		}
    		$tmp['time_str'] = $value['start_time'].'~'.$value['end_time'];
    		$tmp['addr_info'] = $addr_data[$value['addr_id']] ?? '--';
    		if(time() > strtotime($value['start_time']) && time() < strtotime($value['end_time']) && !empty($value['start_time']) && !empty($value['end_time'])){
    			$tmp['status_xx'] = '进行中';
    		}else if(time() > strtotime($value['end_time']) && !empty($value['end_time'])){
                if($value['score'] > 0){
                    $tmp['status_xx'] = '已评价';
                }else{
                    if($value['training_user'] == $inputs['by_or_training_user']){
                        $tmp['if_comment'] = 1;//能评分
                    }
                    $tmp['status_xx'] = '评价中';
                }
    		}else if(time() < strtotime($value['start_time']) && !empty($value['start_time'])){
    			$tmp['status_xx'] = '未开始';
    		}else{
    			$tmp['time_str'] = '--';
    			$tmp['status_xx'] = '未安排';
    		}

    		if($value['score'] > 0){
    			$tmp['score'] = $value['score'];
    		}else{
    			$tmp['score'] = '--';
    		}
    		$training_list['datalist'][$key] = $tmp;
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $training_list]);
    }

    /** 
    *  我参加的培训-查看/评分
    *  @author molin
    *   @date 2018-10-15
    */
    public function myListShow(){
    	$inputs = request()->all();
    	if(!isset($inputs['id']) || empty($inputs['id'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数id']);
    	}
    	$training = new \App\Models\TrainingList;
		$training_info = $training->getTrainingInfo($inputs);
		$user = new \App\Models\User;
		$user_data = $user->getIdToData();
		$projects = new \App\Models\TrainingProject;
		$projects_arr = $projects->getIdToData();
		$addr = new \App\Models\TrainingAddr;
		$addr_data = $addr->getAddrData();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		
    		$items = array();
    		$items['supervision_people'] = $user_data['id_realname'][$training_info->user_id];
    		if($training_info->type_id == 1){
    			$items['supervision_people'] = $user_data['id_realname'][$projects_arr['id_supervision_people'][$training_info->training_project]];
    			$training_info->training_project = $projects_arr['id_name'][$training_info->training_project];
    		}
    		$items['training_project'] = $training_info->training_project;
    		$items['training_user'] = $user_data['id_realname'][$training_info->training_user];//讲解人
    		$items['by_training_user'] = $user_data['id_realname'][$training_info->by_training_user];
    		$items['addr'] = $addr_data[$training_info->addr_id] ?? '';
    		if(!empty($training_info->start_time) && !empty($training_info->end_time)){
    			$items['time_str'] = $training_info->start_time.'~'.$training_info->end_time;
    		}else{
    			$items['time_str'] = '--';
    		}
            $file_data = array();
            if($training_info->type_id == 1){
                //入职培训 文档
                $content = unserialize($training_info->hasTraining->content);
                foreach ($content as $key => $value) {
                    foreach ($value['training_projects'] as $v) {
                        $file_data[$key]['name'] = $projects_arr['id_name'][$v];
                        $file_data[$key]['training_doc'] = !empty($projects_arr['id_training_doc'][$v]) ? asset($projects_arr['id_training_doc'][$v]) : '';
                        $file_data[$key]['test_doc'] = !empty($projects_arr['id_test_doc'][$v]) ? asset($projects_arr['id_test_doc'][$v]) : '';
                    }
                 } 
            }
            $items['file_data'] = $file_data;
    		$data['training_info'] = $items;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'comment'){
    		
    		$items = array();
    		$items['training_id'] = $training_info->training_id;
    		$items['name'] = $training_info->name;
    		$items['supervision_people'] = $user_data['id_realname'][$training_info->user_id];
            $items['type'] = '拓展培训'; 
    		$items['type_id'] = $training_info->type_id; 
    		if($training_info->type_id == 1){
    			$items['supervision_people'] = $user_data['id_realname'][$projects_arr['id_supervision_people'][$training_info->training_project]];
    			$items['type'] = '入职培训'; 
    			$training_info->training_project = $projects_arr['id_name'][$training_info->training_project];
    		}
    		$items['training_project'] = $training_info->training_project;
    		$items['training_user'] = $user_data['id_realname'][$training_info->training_user];//讲解人
    		//$items['by_training_user'] = $training_info->hasTraining->by_training_users;
    		$items['addr'] = $addr_data[$training_info->addr_id] ?? '';
    		if(!empty($training_info->start_time) && !empty($training_info->end_time)){
    			$items['time_str'] = $training_info->start_time.'~'.$training_info->end_time;
    		}else{
    			$items['time_str'] = '--';
    		}

            $items['by_training_user'][0]['id'] = $inputs['id'];
            $items['by_training_user'][0]['realname'] = $user_data['id_realname'][$training_info->by_training_user];
            $file_data = array();
            if($training_info->type_id == 1){
                //入职培训 文档
                $content = unserialize($training_info->hasTraining->content);
                foreach ($content as $key => $value) {
                    foreach ($value['training_projects'] as $v) {
                        $file_data[$key]['name'] = $projects_arr['id_name'][$v];
                        $file_data[$key]['training_doc'] = !empty($projects_arr['id_training_doc'][$v]) ? asset($projects_arr['id_training_doc'][$v]) : '';
                        $file_data[$key]['test_doc'] = !empty($projects_arr['id_test_doc'][$v]) ? asset($projects_arr['id_test_doc'][$v]) : '';
                    }
                 } 
            }
            $items['file_data'] = $file_data;
    		$data['training_info'] = $items;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'submit'){
    		//评分提交
    		if(!isset($inputs['comment']) || empty($inputs['comment'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数comment']);
    		}
    		if(!is_array($inputs['comment'])){
    			return response()->json(['code' => -1, 'message' => 'comment必须为数组']);
    		}
    		$sql_data = array();
            $ids = array();
    		foreach ($inputs['comment'] as $key => $value) {
    			$aa = explode('-', $value);
    			if(!is_numeric($aa[1])){
    				return response()->json(['code' => -1, 'message' => '分数只能填写数字']);
    			}
    			$sql_data[$key]['id'] = $aa[0];
    			$sql_data[$key]['score'] = $aa[1];
                $ids[] = $aa[0];
    		}
    		$result = $training->updateBatch($sql_data);
    		if($result){
                systemLog('培训管理', '对['.$user_data['id_realname'][$training_info->user_id].']的['.$training_info->name.']培训进行了评分');
    			return response()->json(['code' => 1, 'message' => '保存成功']);
    		}
    		return response()->json(['code' => 0, 'message' => '保存失败']);
    	}
    }

    /** 
    *  我安排的培训-列表
    *  @author molin
    *   @date 2018-10-16
    */
    public function myApplyList(){
    	$inputs = request()->all();
    	$inputs = request()->all();
        $user_info = auth()->user();
        $audit_proces = new \App\Models\ApplyTraining;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
        	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数id']);
        	}
        	$data = array();
        	$projects = new \App\Models\TrainingProject;
        	$projects_data = $projects->getIdToData();
        	$apply_info = $audit_proces->where('id', $inputs['id'])->first();
        	$info = array();
            $info['type_id'] = $apply_info->type_id;
        	if($apply_info->type_id == 1){
        		//入职培训
        		$info['type'] = '入职培训'; 
        		$info['name'] = $apply_info->name; 
        		$content = unserialize($apply_info->content); 
        		foreach ($content as $key => $value) {
        			$content[$key]['by_training_user'] = $user_data['id_realname'][$value['by_training_user']];
        			foreach ($value['training_projects'] as $k => $v) {
        				$content[$key]['training_projects'][$k] = ($k+1).'.'.$projects_data['id_name'][$v];
        			}
        		}
        		$info['content'] = $content;
        	}else if($apply_info->type_id == 2){
        		//拓展培训
        		$info['type'] = '拓展培训'; 
        		$info['name'] = $apply_info->name; 
        		$info['supervision_people'] = $user_data['id_realname'][$apply_info->user_id]; 
        		$content = unserialize($apply_info->content); 
        		foreach ($content as $key => $value) {
        			$content[$key]['training_projects'] = $value['training_projects'];
        			$content[$key]['training_user'] = $user_data['id_realname'][$value['training_user']];
        			foreach ($value['by_training_user'] as $k => $v) {
        				$content[$key]['by_training_user'][$k] = $user_data['id_realname'][$v];
        			}
        		}
        		$info['content'] = $content;
        	}
        	$data['info'] = $info;
        	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit'){
        	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数id']);
        	}
        	$data = array();
        	$user = new \App\Models\User;
    		$user_list = $user->where('status', 1)->select(['id','realname'])->get();
        	$projects = new \App\Models\TrainingProject;
        	$project_list = $projects->select(['id','name'])->get();
    		$data['user_list'] = $user_list;
    		$data['project_list'] = $project_list;
        	$apply_info = $audit_proces->where('id', $inputs['id'])->first();
        	$info = array();
        	$info['name'] = $apply_info->name;
        	$info['type_id'] = $apply_info->type_id;
        	$info['content'] = unserialize($apply_info->content);
        	$info['addr_id'] = $apply_info->addr_id;
        	$info['start_time'] = $apply_info->start_time;
        	$info['end_time'] = $apply_info->end_time;
        	$data['apply_info'] = $info;
        	$addr = new \App\Models\TrainingAddr;
    		$addr_list = $addr->get();
    		$data['addr_list'] = $addr_list;
        	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        $inputs['user_id'] = $user_info->id;
        $audit_list = $audit_proces->getQueryList($inputs);
        //拼接数据
        $items = array();
        foreach ($audit_list['datalist'] as $key => $value) {
        	$items[$key]['id'] = $value->id;
        	$items[$key]['type_id'] = $value->type_id;
        	if($value->type_id == 1){
        		$items[$key]['type'] = '入职培训';
        	}else if($value->type_id == 2){
        		$items[$key]['type'] = '拓展培训';
        	}
        	$items[$key]['user'] = $user_data['id_realname'][$value->user_id];
            $items[$key]['name'] = $value->name;
            $items[$key]['by_training_users'] = $value->by_training_users;
            $items[$key]['status_txt'] = $value->status_txt;
        	$items[$key]['if_edit'] = $value->if_edit;
        	$items[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            
        }
        $audit_list['datalist'] = $items;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $audit_list]);
    }

    /** 
    *  入职安排
    *  @author molin
    *   @date 2018-10-16
    */
    public function arrange(){
    	$inputs = request()->all();
    	$training = new \App\Models\TrainingList;
    	$inputs['type_id'] = 1;//入职培训
    	$projects = new \App\Models\TrainingProject;
        $projects_data = $projects->getIdToData();
        $addr = new \App\Models\TrainingAddr;
        $addr_data = $addr->getAddrData();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
        	//查看
        	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数id']);
        	}
        	$data = array();
        	$data = $training->where('id', $inputs['id'])->first();
        	$data->by_training_user = $user_data['id_realname'][$data->by_training_user];
        	$data->training_user = $user_data['id_realname'][$data->training_user];
        	$data->supervision_people = $user_data['id_realname'][$projects_data['id_supervision_people'][$data->training_project]];
        	$data->training_project = $projects_data['id_name'][$data->training_project];
        	$data->addr = $addr_data[$data->addr_id] ?? '';
        	if(!empty($data->start_time) && !empty($data->end_time)){
        		$data->time_str = $data->start_time.'~'.$data->end_time;
        	}else{
        		$data->time_str = '--';
        	}
        	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
        	//安排-加载
        	if(!isset($inputs['ids'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数ids']);
        	}
        	if(!is_array($inputs['ids'])){
        		return response()->json(['code' => -1, 'message' => 'ids必须为数组']);
        	}
        	$list = $training->getDataList($inputs);
        	$training_project_str = $supervision_people_str = $training_user_str = $by_training_user_str = array();
        	foreach ($list['datalist'] as $key => $value) {
        		$training_project_str[$value->training_project] = $projects_data['id_name'][$value->training_project];
        		$supervision_people_str[$projects_data['id_supervision_people'][$value->training_project]] = $user_data['id_realname'][$projects_data['id_supervision_people'][$value->training_project]];
        		$training_user_str[$value->training_user] = $user_data['id_realname'][$value->training_user];
        		$by_training_user_str[$value->by_training_user] = $user_data['id_realname'][$value->by_training_user];
        	}
        	$training_project_str = implode(',', $training_project_str);
        	$supervision_people_str = implode(',', $supervision_people_str);
        	$training_user_str = implode(',', $training_user_str);
        	$by_training_user_str = implode(',', $by_training_user_str);
        	$data = array();
        	$data['ids'] = $inputs['ids'];
        	$data['training_project'] = $training_project_str;
        	$data['supervision_people'] = $supervision_people_str;
        	$data['training_user'] = $training_user_str;
        	$data['by_training_user'] = $by_training_user_str;
        	$addr_list = $addr->get();
        	$data['addr_list'] = $addr_list;
        	
        	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'submit'){
        	//安排-提交
        	if(!isset($inputs['ids'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数ids']);
        	}
        	if(!is_array($inputs['ids'])){
        		return response()->json(['code' => -1, 'message' => 'ids必须为数组']);
        	}
        	if(!isset($inputs['addr_id']) || !is_numeric($inputs['addr_id'])){
        		return response()->json(['code' => -1, 'message' => '请选择地址']);
        	}
        	if(!isset($inputs['start_time']) || empty($inputs['start_time']) || !isset($inputs['end_time']) || empty($inputs['end_time'])){
        		return response()->json(['code' => -1, 'message' => '请填写开始时间和结束时间']);
        	}
        	if($inputs['start_time'] >= $inputs['end_time']){
        		return response()->json(['code' => -1, 'message' => '开始时间不能大于或等于结束时间']);
        	}
            $alist = $training->whereIn('id', $inputs['ids'])->pluck('training_project')->toArray();
            if(count(array_unique($alist)) != 1){
                return response()->json(['code' => -1, 'message' => '勾选的项目不一致，不能同时安排']);
            }
            //申请列表是否已占用
            $if_exist = (new \App\Models\ApplyTraining)->whereIn('status', [0,1])->where('addr_id',$inputs['addr_id'])->whereBetween('end_time', [$inputs['start_time'], $inputs['end_time']])->first();
            if($if_exist){
                return response()->json(['code' => -1, 'message' => '该时间段内培训地点已经有人申请，请另选时间']);
            }
            //安排列表是否已占用
            $if_exist2 = $training->where('addr_id',$inputs['addr_id'])->where('training_project','<>', $alist[0])->whereBetween('end_time', [$inputs['start_time'], $inputs['end_time']])->first();//不同培训项目是否占用场地
            if($if_exist2){
                return response()->json(['code' => -1, 'message' => '该时间段内培训地点被占用，请另选时间']);
            }
        	$rs = $training->whereIn('id', $inputs['ids'])->update(['addr_id' => $inputs['addr_id'], 'start_time' => $inputs['start_time'], 'end_time' => $inputs['end_time']]);
        	if($rs){
                systemLog('培训管理', '安排了培训');
        		return response()->json(['code' => 1, 'message' => '安排成功']);
        	}
        	return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);
        }
        $data = $training->getDataList($inputs);
        foreach ($data['datalist'] as $key => $value) {
        	$data['datalist'][$key]['training_project'] = $projects_data['id_name'][$value['training_project']];
        	$data['datalist'][$key]['by_training_user'] = $user_data['id_realname'][$value['by_training_user']];
        	$data['datalist'][$key]['training_user'] = $user_data['id_realname'][$value['training_user']];
        	$data['datalist'][$key]['name'] = $value['name'];
        	$data['datalist'][$key]['created_at'] = $value['created_at'];
        	if(!empty($value['start_time']) && !empty($value['end_time'])){
        		$data['datalist'][$key]['time_str'] = $value['start_time'].'~'.$value['end_time'];
        		if(time() > strtotime($value['start_time']) && time() < strtotime($value['end_time']) && !empty($value['start_time']) && !empty($value['end_time'])){
        			$data['datalist'][$key]['status'] = '进行中';
        		}else if(time() < strtotime($value['start_time'])){
        			$data['datalist'][$key]['status'] = '未开始';
        		}else{
        			$data['datalist'][$key]['status'] = '已结束';
        		}
                $data['datalist'][$key]['if_arrange'] = 0;//不能安排
        	}else{
        		$data['datalist'][$key]['time_str'] = '--';
        		$data['datalist'][$key]['status'] = '未安排';
                $data['datalist'][$key]['if_arrange'] = 1;//能安排
        	}
        	$data['datalist'][$key]['addr'] = $addr_data[$value['addr_id']] ?? '';

        	
        }
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  培训汇总
    *  @author molin
    *   @date 2018-10-17
    */
    public function trainingTotal(){
    	$inputs = request()->all();
    	$training = new \App\Models\TrainingList;
    	$projects = new \App\Models\TrainingProject;
        $projects_data = $projects->getIdToData();
        $addr = new \App\Models\TrainingAddr;
        $addr_data = $addr->getAddrData();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
    	
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$data = array();
        	$data = $training->where('id', $inputs['id'])->first();
        	$data->by_training_user = $user_data['id_realname'][$data->by_training_user];
        	$data->training_user = $user_data['id_realname'][$data->training_user];
        	if($data->type_id == 1){
        		$data->supervision_people = $user_data['id_realname'][$projects_data['id_supervision_people'][$data->training_project]];
        		$data->training_project = $projects_data['id_name'][$data->training_project];
        	}else if($data->type_id == 2){
        		$data->supervision_people = $user_data['id_realname'][$data->user_id];
        		$data->training_project = $data->training_project;
        	}
        	$data->addr = $addr_data[$data->addr_id] ?? '';
        	if(!empty($data->start_time) && !empty($data->end_time)){
        		$data->time_str = $data->start_time.'~'.$data->end_time;
        	}else{
        		$data->time_str = '--';
        	}
        	if(empty($data->score)){
        		$data->score = '--';
        	}
        	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
    	$project_list = $projects->select(['id','name'])->get();
    	$data['project_list'] = $project_list;
    	$data = $training->getDataList($inputs);
        foreach ($data['datalist'] as $key => $value) {
        	if($value->type_id == 1){
        		$data['datalist'][$key]['type'] = '入职培训';
        		$data['datalist'][$key]['training_project'] = $projects_data['id_name'][$value['training_project']];
        	}else if($value->type_id == 2){
        		$data['datalist'][$key]['type'] = '拓展培训';
        		$data['datalist'][$key]['training_project'] = $value['training_project'];
        	}
        	$data['datalist'][$key]['by_training_user'] = $user_data['id_realname'][$value['by_training_user']];
        	$data['datalist'][$key]['training_user'] = $user_data['id_realname'][$value['training_user']];
        	$data['datalist'][$key]['name'] = $value['name'];
        	$data['datalist'][$key]['created_at'] = $value['created_at'];
        	if(!empty($value['start_time']) && !empty($value['end_time'])){
        		$data['datalist'][$key]['time_str'] = $value['start_time'].'~'.$value['end_time'];
        		if(time() > strtotime($value['start_time']) && time() < strtotime($value['end_time']) && !empty($value['start_time']) && !empty($value['end_time'])){
        			$data['datalist'][$key]['status'] = '进行中';
        		}else if(time() < strtotime($value['start_time'])){
        			$data['datalist'][$key]['status'] = '未开始';
        		}else{
        			$data['datalist'][$key]['status'] = '已结束';
        		}
        	}else{
        		$data['datalist'][$key]['time_str'] = '--';
        		$data['datalist'][$key]['status'] = '未安排';
        	}
        	$data['datalist'][$key]['addr'] = $addr_data[$value['addr_id']] ?? '';
        	if(empty($value->score)){
        		$data['datalist'][$key]['score'] = '--';
        	}
        	
        }
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

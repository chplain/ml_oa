<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProjectPlanController extends Controller
{
    //日投递计划
    /**
     * 日投递计划设置
     * @Author: molin
     * @Date:   2019-02-13
     */
    public function index(){
    	$inputs = request()->all();
    	$plan = new \App\Models\ProjectPlan;
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	if(!isset($inputs['start_time'])){
    		$inputs['start_time'] = date('Y-m-d');
    	}
    	if(!isset($inputs['end_time'])){
    		$start_date = $inputs['start_time'];
    		$inputs['end_time'] = date('Y-m-d', strtotime("$start_date +9 day"));
    	}
        $suspend = new \App\Models\BusinessProjectSuspend;
        $suspend_list = $suspend->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->get();
        $suspend_data = array();
        foreach ($suspend_list as $key => $value) {
            $suspend_data[$value->project_id][$value->date] = 1;//是否存在暂停
        }
    	$days = prDates($inputs['start_time'], $inputs['end_time']);
    	if(isset($inputs['realname']) && !empty($inputs['realname'])){
    		$inputs['user_ids'] = $user->where('realname', 'like', '%'.$inputs['realname'].'%')->pluck('id')->toArray();
    	}
		$plan_list = $plan->getQueryData($inputs);   
		$plan_data = array();
		foreach ($plan_list as $key => $value) {
			$plan_data[$value->project_id][$value->date] = sprintf("%.2f", $value->amount);
		}

    	$group =  new \App\Models\ProjectGroup;
    	$group_list = $group->select(['id', 'name', 'amount'])->get();

    	$project =  new \App\Models\BusinessProject;
        $inputs['all'] = 1;//查询全部
    	// $inputs['status'] = 1;//只显示投递中的项目
        $inputs['status_in'] = [1,3];//只显示暂停和投递中的项目
    	$project_list = $project->getDataList($inputs);
    	$project_list = $project_list['datalist'];
    	$week = ['0'=>'周日','1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六'];

    	$data = $data_list = $head = array();
    	$head['name'] = '每天计划量';
    	$head['label'] = '总投递量（万）';
    	$total = 0;
        $data_list[0]['id'] = 0;
        $data_list[0]['name'] = '未分配组';
        $data_list[0]['label'] = '剩余量（万）';
    	foreach ($group_list as $key => $value) {
    		$data_list[$value->id]['id'] = $value->id;
    		$data_list[$value->id]['name'] = $value->name;
    		$data_list[$value->id]['label'] = '剩余量（万）';
    		$items = $never_items = array();
    		$date_amount = array();
    		foreach ($project_list as $val) {
    			$th = $body = array();
    			if($val->group_id == $value->id){
    				$th['project_name'] = '项目名称';
    				$th['trade'] = '行业';
    				$th['charge'] = '项目负责人';
    				$th['execute'] = '执行';

    				$body['project_id'] = $val->id;
    				$body['project_name'] = $val->project_name;
    				$body['trade'] = $val->trade->name;
    				$body['charge'] = $user_data['id_realname'][$val->charge_id];
    				$body['execute'] = $user_data['id_realname'][$val->execute_id];
    				foreach ($days as $d) {
    					$body[$d] = $plan_data[$val->id][$d] ?? 0;
                        if (isset($suspend_data[$val->id][$d])) {
                            $body[$d] = '停';
                        }
    					$th[$d]['date'] = date('m-d', strtotime($d));
    					$th[$d]['week'] = $week[date('w',strtotime($d))];
    					$date_amount[$val->group_id][$d] = $date_amount[$val->group_id][$d] ?? 0;
    					$date_amount[$val->group_id][$d] += $plan_data[$val->id][$d] ?? 0;
    				}
    				$items['th'] = $th;
    				$items['body'][] = $body;
    			}
                if($val->group_id == 0){
                    //未分配组
                    $never_th = $never_body = array();
                    $never_th['project_name'] = '项目名称';
                    $never_th['trade'] = '行业';
                    $never_th['charge'] = '项目负责人';
                    $never_th['execute'] = '执行';
                    $never_body['project_id'] = $val->id;
                    $never_body['project_name'] = $val->project_name;
                    $never_body['trade'] = $val->trade->name;
                    $never_body['charge'] = $user_data['id_realname'][$val->charge_id];
                    $never_body['execute'] = $user_data['id_realname'][$val->execute_id];
                    foreach ($days as $d) {
                        $never_body[$d] = $plan_data[$val->id][$d] ?? 0;
                        if (isset($suspend_data[$val->id][$d])) {
                            $never_body[$d] = '停';
                        }
                        $never_th[$d]['date'] = date('m-d', strtotime($d));
                        $never_th[$d]['week'] = $week[date('w',strtotime($d))];
                        $date_amount[0][$d] = $date_amount[0][$d] ?? 0;
                        $date_amount[0][$d] += $plan_data[$val->id][$d] ?? 0;
                    }
                    $never_items['th'] = $never_th;
                    $never_items['body'][] = $never_body;
                }
    		}
    		// dd($date_amount);
    		foreach ($days as $day) {
    			$b = $date_amount[$value->id][$day] ?? 0;
                $data_list[$value->id][$day] = sprintf('%.2f', $value->amount - $b);
                if(isset($date_amount[0][$day])){
                    $b = $date_amount[0][$day];
                }
    			$data_list[0][$day] = sprintf('%.2f', $value->amount - $b);
    		}
    		$data_list[$value->id]['child'] = $items;
    		$total += $value->amount;

    	}
    	foreach ($days as $day) {
			$head[$day]['date'] = date('m-d', strtotime($day));
			$head[$day]['week'] = $week[date('w',strtotime($day))];
			$head[$day]['amount'] = sprintf("%.2f", $total);
        }
        $data_list[0]['child'] = $never_items;
    	$data['head'] = $head;
    	$data['datalist'] = $data_list;
    	$data['group_list'] = $group_list;
    	$data['cooperation_cycle'] = [['id'=>1,'name'=>'长期项目'],['id'=>2, 'name'=>'短期项目']];
        // dd($data);
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

    /**
     * 日投递计划设置-移动
     * @Author: molin
     * @Date:   2019-02-13
     */
    public function move(){
    	$inputs = request()->all();
    	if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数project_id']);
    	}
    	if(!isset($inputs['group_id']) || !is_numeric($inputs['group_id'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数group_id']);
    	}
    	$project =  new \App\Models\BusinessProject;
    	$project_info = $project->where('id', $inputs['project_id'])->first();
    	$project_info->group_id = $inputs['group_id'];
    	$result = $project_info->save();
    	if($result){
            $group = new \App\Models\ProjectGroup;
            $group_info = $group->where('id', $inputs['group_id'])->first();
            systemLog('项目汇总', '移动了项目['.$project_info->project_name.']到组段['.$group_info->name.']');
    		return response()->json(['code' => 1, 'message' => '操作成功']);
    	}
    	return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 日投递计划设置-设置量
     * @Author: molin
     * @Date:   2019-02-13
     */
    public function update(){
    	$inputs = request()->all();
        $rules = [
            'project_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'amount' => 'required|numeric'

        ];
        $attributes = [
            'project_id' => 'project_id',
            'date' => '日期',
            'amount' => '设置量'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $suspend = new \App\Models\BusinessProjectSuspend;
        if(!isset($inputs['if_cover']) || (isset($inputs['if_cover']) && $inputs['if_cover'] != 1)){
            $if_stop = $suspend->where('project_id', $inputs['project_id'])->where('date', $inputs['date'])->first();
            if(!empty($if_stop)){
                return response()->json(['code' => 1, 'message'=> '当天项目状态为暂停，是否继续修改？', 'data'=>['if_cover' => 1]]);
            }
        }

    	$plan =  new \App\Models\ProjectPlan;
    	$project_info = $plan->where('project_id', $inputs['project_id'])->where('date', $inputs['date'])->first();
    	if(!empty($plan_info)){
    		$plan_info->amount = $inputs['amount'];
    		$result = $plan_info->save();//修改
    	}else{
    		$result = $plan->storeData($inputs);//新增
    	}
    	if($result){
            $project =  new \App\Models\BusinessProject;
            $project_info = $project->where('id', $inputs['project_id'])->select(['id','project_name'])->first();
            if(isset($inputs['if_cover']) && $inputs['if_cover'] == 1){
                $suspend->where('project_id', $inputs['project_id'])->where('date', $inputs['date'])->delete();
            }
            systemLog('项目汇总', '设置了日投递量,项目:'.$project_info->project_name.',日期:'.$inputs['date'].',数量:'.$inputs['amount']);
    		return response()->json(['code' => 1, 'message' => '操作成功']);
    	}
    	return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 日投递计划设置-导出
     * @Author: molin
     * @Date:   2019-02-15
     */
    public function export(){
    	$inputs = request()->all();
    	$plan = new \App\Models\ProjectPlan;
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	if(!isset($inputs['start_time'])){
    		$inputs['start_time'] = date('Y-m-d');
    	}
    	if(!isset($inputs['end_time'])){
    		$start_date = $inputs['start_time'];
    		$inputs['end_time'] = date('Y-m-d', strtotime("$start_date +9 day"));
    	}
    	$days = prDates($inputs['start_time'], $inputs['end_time']);
    	if(isset($inputs['realname']) && !empty($inputs['realname'])){
    		$inputs['user_ids'] = $user->where('realname', 'like', '%'.$inputs['realname'].'%')->pluck('id')->toArray();
    	}
		$plan_list = $plan->getQueryData($inputs);   
		$plan_data = array();
		foreach ($plan_list as $key => $value) {
			$plan_data[$value->project_id][$value->date] = $value->amount;
		}

    	$group =  new \App\Models\ProjectGroup;
    	$group_list = $group->select(['id', 'name', 'amount'])->get();

    	$project =  new \App\Models\BusinessProject;
    	$inputs['all'] = 1;//查询全部
    	$project_list = $project->getDataList($inputs);
    	$project_list = $project_list['datalist'];
    	$week = ['0'=>'周日','1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六'];

    	$data = $data_list = $head = array();
    	$head['project_name'] = '每天计划量';
    	$head['trade'] = '';
    	$head['charge'] = '';
    	$head['execute'] = '';
    	$total = 0;
    	$data_list = array();
        $data_list[0]['project_name'] = '未分配组';
        $data_list[0]['trade'] = '';
        $data_list[0]['charge'] = '';
        $data_list[0]['execute'] = '剩余量（万）';
        $data_list[1]['project_name'] = '项目名称';
        $data_list[1]['trade'] = '行业';
        $data_list[1]['charge'] = '项目负责人';
        $data_list[1]['execute'] = '执行';
    	foreach ($group_list as $key => $value) {
    		$tmp = array();
    		$tmp['project_name'] = $value->name;
    		$tmp['trade'] = '';
    		$tmp['charge'] = '';
    		$tmp['execute'] = '剩余量（万）';
    		$th = array();
    		$th['project_name'] = '项目名称';
			$th['trade'] = '行业';
			$th['charge'] = '项目负责人';
			$th['execute'] = '执行';
    		$date_amount = array();
    		$body_arr = $never_body_arr = array();
    		foreach ($project_list as $val) {
    			$body = array();
    			if($val->group_id == $value->id){
    				$body['project_name'] = $val->project_name;
    				$body['trade'] = $val->trade->name;
    				$body['charge'] = $user_data['id_realname'][$val->charge_id];
    				$body['execute'] = $user_data['id_realname'][$val->execute_id];
    				foreach ($days as $d) {
    					$body[$d] = $plan_data[$val->id][$d] ?? 0;
    					$date_amount[$val->group_id][$d] = $date_amount[$val->group_id][$d] ?? 0;
    					$date_amount[$val->group_id][$d] += $plan_data[$val->id][$d] ?? 0;
    				}
    				$body_arr[] = $body;
    			}
                if($val->group_id == 0){
                    $never_body = array();
                    $never_body['project_name'] = $val->project_name;
                    $never_body['trade'] = $val->trade->name;
                    $never_body['charge'] = $user_data['id_realname'][$val->charge_id];
                    $never_body['execute'] = $user_data['id_realname'][$val->execute_id];
                    foreach ($days as $d) {
                        $never_body[$d] = $plan_data[$val->id][$d] ?? 0;
                        $date_amount[0][$d] = $date_amount[0][$d] ?? 0;
                        $date_amount[0][$d] += $plan_data[$val->id][$d] ?? 0;
                    }
                    $never_body_arr[] = $never_body;
                }
    		}
            
    		foreach ($days as $day) {
    			$b = $date_amount[$value->id][$day] ?? 0;
    			$tmp[$day] = $value->amount - $b;
    			$th[$day] = $day.$week[date('w',strtotime($day))];
                $data_list[0][$day] = $value->amount - ($date_amount[0][$day] ?? 0);
    		}
            $data_list[] = $tmp;
            $data_list[] = $th;
            $data_list = array_merge($data_list, $body_arr);
            $total += $value->amount;
        }
    	foreach ($days as $day) {
			$head[$day] = $day.$week[date('w',strtotime($day))].$total;
            $data_list[1][$day] = $day.$week[date('w',strtotime($day))];
		}
        array_splice($data_list, 2, 0, $never_body_arr);
    	$filedata = pExprot($head, $data_list, 'plan_list');
        $filepath = 'storage/exports/' . $filedata['file'];//下载链接
        $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
        return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);

    }

    /**
     * 我的日投递计划
     * @Author: molin
     * @Date:   2019-02-14
     */
    public function list(){
    	$inputs = request()->all();
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	if(!isset($inputs['start_time'])){
    		$inputs['start_time'] = date('Y-m-d');
    	}
    	if(!isset($inputs['end_time'])){
    		$start_date = $inputs['start_time'];
    		$inputs['end_time'] = date('Y-m-d', strtotime("$start_date +9 day"));
    	}
    	$days = prDates($inputs['start_time'], $inputs['end_time']);
    	$plan = new \App\Models\ProjectPlan;
    	$plan_list = $plan->getQueryData($inputs);   
		$plan_data = array();
		foreach ($plan_list as $key => $value) {
			$plan_data[$value->project_id][$value->date] = $value->amount;
		}

        $suspend = new \App\Models\BusinessProjectSuspend;
        $suspend_list = $suspend->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->get();
        $suspend_data = array();
        foreach ($suspend_list as $key => $value) {
            $suspend_data[$value->project_id][$value->date] = 1;//是否存在暂停
        }

    	$project =  new \App\Models\BusinessProject;
    	$inputs['charge_or_execute'] = auth()->user()->id;
    	$data = $project->getDataList($inputs);
    	$week = ['0'=>'周日','1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六'];
    	$items = $th = array();
    	$th['project_name'] = '项目名称';
    	$th['trade'] = '行业';
    	$th['charge'] = '项目负责人';
    	$th['execute'] = '执行人';
    	foreach ($days as $d) {
    		$th[$d]['date'] = $d;
    		$th[$d]['week'] = $week[date('w',strtotime($d))];
    	}
    	foreach ($data['datalist'] as $key => $value) {
    		$tmp = array();
    		$tmp['id'] = $value->id;
    		$tmp['project_name'] = $value->project_name;
    		$tmp['trade'] = $value->trade->name;
    		$tmp['charge'] = $user_data['id_realname'][$value->charge_id];
    		$tmp['execute'] = $user_data['id_realname'][$value->execute_id];
    		foreach ($days as $day) {
    			$tmp[$day] = $plan_data[$value->id][$day] ?? 0;
                if(isset($suspend_data[$value->id][$day])){
                    $tmp[$day] = '停';
                }
    		}
    		$items[] = $tmp;
    	}
    	$data['th'] = $th;
    	$data['datalist'] = $items;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

    /**
     * 日投递计划汇总
     * @Author: molin
     * @Date:   2019-05-09
     */
    public function summary(){
        $inputs = request()->all();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(!isset($inputs['start_time'])){
            $inputs['start_time'] = date('Y-m-d');
        }
        if(!isset($inputs['end_time'])){
            $start_date = $inputs['start_time'];
            $inputs['end_time'] = date('Y-m-d', strtotime("$start_date +9 day"));
        }
        $days = prDates($inputs['start_time'], $inputs['end_time']);
        $plan = new \App\Models\ProjectPlan;
        $plan_list = $plan->getQueryData($inputs);   
        $plan_data = array();
        $project_ids = [];
        foreach ($plan_list as $key => $value) {
            $plan_data[$value->project_id][$value->date] = $value->amount;
            $project_ids[] = $value->project_id;
        }
        $inputs['ids'] = $project_ids;
        $project =  new \App\Models\BusinessProject;
        $data = $project->getDataList($inputs);
        $week = ['0'=>'周日','1'=>'周一','2'=>'周二','3'=>'周三','4'=>'周四','5'=>'周五','6'=>'周六'];
        $items = $th = array();
        $th['project_name'] = '项目名称';
        $th['trade'] = '行业';
        $th['charge'] = '项目负责人';
        $th['execute'] = '执行人';
        foreach ($days as $d) {
            $th[$d]['date'] = $d;
            $th[$d]['week'] = $week[date('w',strtotime($d))];
        }
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['project_name'] = $value->project_name;
            $tmp['trade'] = $value->trade->name;
            $tmp['charge'] = $user_data['id_realname'][$value->charge_id];
            $tmp['execute'] = $user_data['id_realname'][$value->execute_id];
            foreach ($days as $day) {
                $tmp[$day] = $plan_data[$value->id][$day] ?? 0;
            }
            $items[] = $tmp;
        }
        $data['th'] = $th;
        $data['datalist'] = $items;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

    /**
     * 日投递计划设置-暂停
     * @Author: molin
     * @Date:   2019-05-09
     */
    public function suspend(){
        $inputs = request()->all();
        if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数project_id']);
        }
        $suspend =  new \App\Models\BusinessProjectSuspend;
        
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
            $rules = [
                'suspend_list' => 'required|array'

            ];
            $attributes = [
                'suspend_list' => '日期'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $suspend_data = $suspend->where('project_id', $inputs['project_id'])->get()->toArray();
            $suspend_list = array();
            foreach ($suspend_data as $key => $value) {
                $suspend_list[] = $value['date'];
            }
            sort($suspend_list);
            sort($inputs['suspend_list']);
            $project =  new \App\Models\BusinessProject;
            $project_info = $project->where('id', $inputs['project_id'])->select(['id','project_name'])->first();
            if(implode(',', $suspend_list) == implode(',', $inputs['suspend_list'])){
                //没有变化
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }else{
                if(empty($suspend_list) && !empty($inputs['suspend_list'])){
                    $insert = array();
                    foreach ($inputs['suspend_list'] as $key => $value) {
                        $insert[$key]['project_id'] = $inputs['project_id'];
                        $insert[$key]['date'] = $value;
                        $insert[$key]['created_at'] = date('Y-m-d H:i:s');
                        $insert[$key]['updated_at'] = date('Y-m-d H:i:s');
                    }
                    $result = $suspend->insert($insert);
                    if($result){
                        systemLog('项目汇总', '设置了项目-'.$project_info->project_name.'暂停日期:'.implode(',', $inputs['suspend_list']));
                        return response()->json(['code' => 1, 'message' => '操作成功']);
                    }
                }else{
                    $insert = array();//新增集
                    $delete = array();//删除集
                    $log_date = array();
                    foreach ($inputs['suspend_list'] as $key => $value) {
                        $tmp = array();
                        if(!in_array($value, $suspend_list)){
                            $tmp['project_id'] = $inputs['project_id'];
                            $tmp['date'] = $value;
                            $tmp['created_at'] = date('Y-m-d H:i:s');
                            $tmp['updated_at'] = date('Y-m-d H:i:s');
                            $insert[] = $tmp;
                            $log_date[] = $value;
                        }
                    }
                    if(!empty($insert)){
                        $suspend->insert($insert);
                        systemLog('项目汇总', '设置了项目-'.$project_info->project_name.'暂停日期:'.implode(',', $log_date));
                    }
                    $log_date = array();
                    foreach ($suspend_data as $key => $value) {
                        if(!in_array($value['date'], $inputs['suspend_list'])){
                            $delete[] = $value['id'];
                            $log_date[] = $value['date'];
                        }
                    }
                    if(!empty($delete)){
                        $suspend->whereIn('id', $delete)->delete();
                        systemLog('项目汇总', '设置了项目-'.$project_info->project_name.'取消暂停日期:'.implode(',', $log_date));
                    }
                    return response()->json(['code' => 1, 'message' => '操作成功']);
                }
                
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        $suspend_list = $suspend->where('project_id', $inputs['project_id'])->pluck('date')->toArray();
        sort($suspend_list);
        $data = array();
        $data['suspend_list'] = $suspend_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 日投递计划-批量操作
     * @Author: molin
     * @Date:   2019-05-09
     * 说明：存在数据则修改 不存在则新增 
     */
    public function batch(){
        $inputs = request()->all();
        $project = new \App\Models\BusinessProject;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
            $rules = [
                'project_ids' => 'required|array',
                'start_time' => 'required|date_format:Y-m-d',
                'end_time' => 'required|date_format:Y-m-d',
                'amount' => 'required|numeric'

            ];
            $attributes = [
                'project_ids' => '项目id集',
                'start_time' => '开始时间',
                'end_time' => '结束时间',
                'amount' => '每日发送量'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $suspend = new \App\Models\BusinessProjectSuspend;
            if(!isset($inputs['if_cover']) || (isset($inputs['if_cover']) && $inputs['if_cover'] != 1)){
                $if_stop = $suspend->whereIn('project_id', $inputs['project_ids'])->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->first();
                if(!empty($if_stop)){
                    return response()->json(['code' => 1, 'message'=> '当天项目状态为暂停，是否继续修改？', 'data'=>['if_cover' => 1]]);
                }
            }

            $project_ids = array();
            foreach ($inputs['project_ids'] as $key => $value) {
                $project_ids[$value] = $value;
            }
            $plan = new \App\Models\ProjectPlan;
            $plan_list = $plan->whereIn('project_id', $inputs['project_ids'])->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->get();
            $plan_ids = array();
            foreach ($plan_list as $key => $value) {
                $plan_ids[] = $value->id;
                unset($project_ids[$value->project_id]);
            }
            if(!empty($plan_ids)){
                //更新已存在数据
                $result1 = $plan->whereIn('id', $plan_ids)->update(['amount'=>$inputs['amount'], 'updated_at'=>date('Y-m-d H:i:s')]);
                if(!$result1){
                    return response()->json(['code' => 0, 'message' => '更新失败']);
                }
            }
            if(!empty($project_ids)){
                //新增数据 
                $days = prDates($inputs['start_time'], $inputs['end_time']);
                $insert = array();
                foreach($days as $key => $value) {
                    foreach ($project_ids as $v) {
                        $tmp = array();
                        $tmp['project_id'] = $v;
                        $tmp['date'] = $value;
                        $tmp['amount'] = $inputs['amount'];
                        $tmp['real_amount'] = 0;
                        $tmp['created_at'] = date('Y-m-d H:i:s');
                        $tmp['updated_at'] = date('Y-m-d H:i:s');
                        $insert[] = $tmp;
                    }
                }
                if(!empty($insert)){
                    $result2 = $plan->insert($insert);
                    if(!$result2){
                        return response()->json(['code' => 0, 'message' => '更新失败']);
                    }
                }
            }
            //删除暂停记录
            if(isset($inputs['if_cover']) && $inputs['if_cover'] == 1){
                $suspend->whereIn('project_id', $inputs['project_ids'])->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->delete();
            }
            return response()->json(['code' => 1, 'message' => '操作成功']);
            
        }
        $project_list = $project->where('status', 1)->select(['id','project_name'])->get();
        $data = array();
        $data['project_list'] = $project_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

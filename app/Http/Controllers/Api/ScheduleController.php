<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ScheduleController extends Controller
{
    //日程
    /**
    * 日程-列表
    * @author molin
    * @date 2019-01-29
    */
    public function index(){
    	$inputs = request()->all();
    	if(isset($inputs['date']) && !empty($inputs['date'])){
    		$inputs['start_date'] = $start_date = $inputs['date'];//选择日期
    	}else{
    		$inputs['start_date'] = $start_date = date('Y-m-d');//默认当前日期
        }
    	$inputs['end_date'] = $end_date = date('Y-m-d', strtotime("$start_date +6 day"));
    	$days = prDates($inputs['start_date'], $inputs['end_date']);
    	$data = array();
        $inputs['user_id'] = auth()->user()->id;
    	$schedule = new \App\Models\Schedule;
    	$schedule_list = $schedule->getDataList($inputs);
    	$week = ['0'=>'星期日','1'=>'星期一','2'=>'星期二','3'=>'星期三','4'=>'星期四','5'=>'星期五','6'=>'星期六'];
    	$th = $tbody = array();
    	foreach ($days as $i => $day) {
    		$th[$day]['date'] = $day;
    		$th[$day]['week'] = $week[date('w',strtotime($day))];

    		$tmp['morning'] = array();
    		$tmp['afternoon'] = array();
    		foreach ($schedule_list as $key => $value) {
    			if($value->type == 1){
    				$type = '自建任务';
    			}elseif($value->type == 2){
    				$type = '普通任务';
    			}elseif($value->type == 3){
    				$type = '项目任务';
    			}
    			$time = $day.' 12:30:59';//区分上午、下午
    			$a = array();
    			if($value->date == $day && strtotime($value->start_time) < strtotime($time)){
    				$a['id'] = $value->id;
    				$a['title'] = $value->title;
                    $a['type_id'] = $value->type;
                    $a['type'] = $type;
	    			$a['if_ok'] = $value->if_ok;
	    			$a['time'] = $value->start_time.'-'.$value->end_time;
	    			// $a['content'] = $value->content;
	    			$tmp['morning'][] = $a;
    			}elseif($value->date == $day && strtotime($value->start_time) >= strtotime($time)){
    				$a['id'] = $value->id;
    				$a['title'] = $value->title;
                    $a['type_id'] = $value->type;
	    			$a['type'] = $type;
                    $a['if_ok'] = $value->if_ok;
	    			$a['time'] = $value->start_time.'-'.$value->end_time;
	    			// $a['content'] = $value->content;
	    			$tmp['afternoon'][] = $a;
    			}
    		}
    		$tbody[$day] = $tmp;
    	}
    	$data['th'] = $th;
    	$data['tbody'] = $tbody;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data'=> $data]);
    }

    /**
    * 添加日程
    * @author molin
    * @date 2019-01-29
    */
    public function store(){
    	$inputs = request()->all();
    	$data = array();
    	$schedule = new \App\Models\Schedule;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'create'){
    		$data['type_list'] = [['id'=>1, 'name'=>'自建任务'],['id'=>2, 'name'=>'普通任务'],['id'=>3, 'name'=>'项目任务']];
    		$data['level_list'] = [['id'=>1, 'name'=>'一般'],['id'=>2, 'name'=>'排期'],['id'=>3, 'name'=>'紧急']];
    		$data['project_list'] = [['id'=>1, 'name'=>'项目1'],['id'=>2, 'name'=>'项目2'],['id'=>3, 'name'=>'项目3']];
    		$user = new \App\Models\User;
    		$data['user_list'] = $user->where('status', 1)->select(['id', 'realname'])->get();
    		return response()->json(['code' => 1, 'message'=> '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit'){
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $schedule->where('id', $inputs['id'])->where('user_id', auth()->user()->id)->first();
    		if(empty($info)){
    			return response()->json(['code' => 0, 'message' => '获取数据失败^_^']);
    		}
    		$info->user_ids = explode(',', $info->user_ids);
    		$data['type_list'] = [['id'=>1, 'name'=>'自建任务'],['id'=>2, 'name'=>'普通任务'],['id'=>3, 'name'=>'项目任务']];
    		$data['level_list'] = [['id'=>1, 'name'=>'一般'],['id'=>2, 'name'=>'排期'],['id'=>3, 'name'=>'紧急']];
    		$data['project_list'] = [['id'=>1, 'name'=>'项目1'],['id'=>2, 'name'=>'项目2'],['id'=>3, 'name'=>'项目3']];
    		$user = new \App\Models\User;
    		$data['user_list'] = $user->where('status', 1)->select(['id', 'realname'])->get();
    		$data['info'] = $info;
    		return response()->json(['code' => 1, 'message'=> '获取成功', 'data' => $data]);
    	}
    	//保存数据
    	$rules = [
            'title' => 'required|max:50',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'type' => 'required|integer',
            'project_id' => 'integer',
            'level' => 'required|integer',
            'user_ids' => 'array',
            'content' => 'required|max:250'
        ];
        $attributes = [
            'title' => '标题',
            'date' => '日期',
            'start_time' => '开始时间',
            'end_time' => '结束时间',
            'type' => '类型',
            'project_id' => '关联项目',
            'level' => '紧急度',
            'user_ids' => '干系人',
            'content' => '内容描述',
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if($inputs['type'] == 3){
        	if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
        		return response()->json(['code' => -1, '缺少参数project_id']);
        	}
        }
        $result = $schedule->storeData($inputs);
        if($result){
        	return response()->json(['code' => 1, 'message'=> '操作成功']);
        }
        return response()->json(['code' => 0, 'message'=> '操作失败']);

    }

}

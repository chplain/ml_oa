<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyAttendanceController extends Controller
{
    /*
    * 出勤申请
    * @author molin
    * @date 2018-09-30
    */

    public function store(){
    	$inputs = request()->all();
        $applyAttend = new \App\Models\ApplyAttendance;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'apply_attendance'){
    		$data = array();
    		//1请假申请2加班申请3外勤申请
    		$data['type'] = [
    			1 => '请假申请',
    			2 => '加班申请',
    			3 => '外勤申请'
    		];
    		//请假类型：1事假2病假3年假4婚假5丧假6产假7其它；
            $holiday_type = new \App\Models\HolidayType;
            $type_list = $holiday_type->where('status', 1)->select(['id','name'])->get();
            $items = array();
            foreach ($type_list as $key => $value) {
                $items[$value->id] = $value->name;
            }
            $data['leave_type'] = $items;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $applyAttend::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
    	//保存
    	if(!isset($inputs['type']) || !is_numeric($inputs['type'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数type']);
    	}
    	if($inputs['type'] == 1){
            //请假申请
            $log_txt = '请假申请';
	    	$rules = [
	            'start_time' => 'required|date',
	            'end_time' => 'required|date',
                'leave_type' => 'required',
	            'leave_time' => 'required|numeric',
	            'remarks' => 'required|max:255'
	        ];
	        $attributes = [
	            'start_time' => '请假开始时间',
	            'end_time' => '请假结束时间',
	            'leave_type' => '请假类型',
                'leave_time' => '时间(天)',
	            'remarks' => '请假事由'
	        ];
            $holiday = new \App\Models\HolidayType;
            $type_info = $holiday->where('id', $inputs['leave_type'])->first();
            $user_info = auth()->user();
            if($type_info->suit == 2 && $type_info->suit_sex != $user_info->sex){
                return response()->json(['code' => 0, 'message' => '您不能申请此请假类型']);
            }
            if($type_info->if_nianjia == 1){
                $contract = new \App\Models\UserContract;
                $contract_info = $contract->where('user_id', $user_info->id)->select(['positive_date'])->first();
                if(empty($contract_info->positive_date)){
                    return response()->json(['code' => 0, 'message' => '您还未转正，不能申请年假']);
                }
                $holiday_year = new \App\Models\HolidayYear;
                $apply_detail = new \App\Models\ApplyAttendanceDetail;
                $my_year_holiday = $holiday_year->getYearHoliday($user_info->id, date('Y-01-01',time()), date('Y-m-d', strtotime("-1 day")));//我当前应有年假
                $my_year_qingjia = $apply_detail->where('type', 1)->where('leave_type', $inputs['leave_type'])->whereBetween('date', [date('Y-01-01',time()), date('Y-m-d', time())])->where('user_id', $user_info->id)->sum('time_str');//今年已使用年假
                $has_year_day = $my_year_holiday-$my_year_qingjia-$inputs['leave_time'];
                if($has_year_day < 0){
                    return response()->json(['code' => 0, 'message' => '已用年假已不足,不能申请年假']);
                }
            }
            if($type_info->if_lianxiu == 1){
                $setting = new \App\Models\AttendanceSetting;
                $setting_info = $setting->orderBy('created_at', 'asc')->first();
                if(empty($setting_info)){
                    return response()->json(['code' => 0, 'message' => '请先设置好下班时间']);
                }
                if($type_info->lianxiu_date <= 4){
                    //小于等于4天的时候  只算工作日；比如连休三天，从星期五开始请假 请到下周星期二
                    $stime = $inputs['start_time'];
                    $y1 = date('Y', strtotime($inputs['start_time']));
                    $m1 = date('m', strtotime($inputs['start_time']));
                    $y2 = date('Y', strtotime("$stime +1 month"));
                    $m2 = date('m', strtotime("$stime +1 month"));
                    $att = new \App\Models\Attendance;
                    //获取这个月和下个月的数据
                    $att_list = $att->where(function ($query) use ($y1,$m1){
                                    $query->where('year', $y1)->where('month', $m1);
                                })
                                ->orWhere(function ($query) use ($y2,$m2){
                                    $query->where('year', $y2)->where('month', $m2);
                                })->get();
                    $if_next_year = $att->where('year', $y2)->first();
                    if(empty($if_next_year) && $type_info->lianxiu_date == 4 && strtotime($inputs['start_time']) > strtotime($y1.'-'.$m1.'-27 00:00:00')){
                        return response()->json(['code' => 0, 'message' => '此请假类型连休四天，请假开始时间不能大于12月27，因为下一年工作日期尚未录入']);
                    }
                    if(empty($if_next_year) && $type_info->lianxiu_date == 3 && strtotime($inputs['start_time']) > strtotime($y1.'-'.$m1.'-28 00:00:00')){
                        return response()->json(['code' => 0, 'message' => '此请假类型连休三天，请假开始时间不能大于12月28，因为下一年工作日期尚未录入']);
                    }
                    if(empty($if_next_year) && $type_info->lianxiu_date == 2 && strtotime($inputs['start_time']) > strtotime($y1.'-'.$m1.'-29 00:00:00')){
                        return response()->json(['code' => 0, 'message' => '此请假类型连休两天，请假开始时间不能大于12月29，因为下一年工作日期尚未录入']);
                    }
                    $i = 0;
                    foreach ($att_list as $key => $value) {
                        if(strtotime(date('Y-m-d', strtotime($inputs['start_time']))) == strtotime($value->date) && $value->type != 0){
                            return response()->json(['code' => 0, 'message' => '开始时间不能是周末或者节假日']);
                        }
                        if($value->type == 0 && strtotime(date('Y-m-d', strtotime($inputs['start_time']))) <= strtotime($value->date) && $i < $type_info->lianxiu_date){
                            $etime = $value->date;
                            $i++;
                        }
                    }
                    $etime = $etime.' '.$setting_info['pm_end_time'].':00';
                    if(strtotime($inputs['end_time']) != strtotime($etime)){
                        return response()->json(['code' => 0, 'message' => '此请假类型为连休假期，结束时间应为'.$etime]);
                    }
                }else{
                    //大于4天的时候 连周末、节假日算在内
                    $n = $type_info->lianxiu_date - 1;
                    $stime = $inputs['start_time'];
                    $etime = date('Y-m-d '.$setting_info['pm_end_time'].':00', strtotime("$stime +$n day"));
                    if(strtotime($inputs['end_time']) != strtotime($etime)){
                        return response()->json(['code' => 0, 'message' => '此请假类型为连休假期，结束时间应为'.$etime]);
                    }
                }
            }else{
                //非连休假期类型
                $attendance =  new \App\Models\Attendance;
                $attendance_list = $attendance->whereBetween('date',[date('Y-m-d', strtotime($inputs['start_time'])), date('Y-m-d', strtotime($inputs['end_time']))])->select(['type'])->get();
                $if_work = true;
                foreach ($attendance_list as $key => $value) {
                    if($value->type == 0){
                        $if_work = false;
                    }
                }
                if($if_work){
                    return response()->json(['code' => 0, 'message' => '非连休类型只能请工作日']);
                }
            }
    	}else if($inputs['type'] == 2){
    		//加班申请
            $log_txt = '加班申请';
	    	$rules = [
	            'time_data' => 'required|array',
	            'remarks' => 'required|max:255'
	        ];
	        $attributes = [
	            'time_data' => '加班开始时间',
	            'remarks' => '加班事由'
	        ];
    	}else{
    		//外出申请
            $log_txt = '外勤申请';
	    	$rules = [
	            'start_time' => 'required',
	            'end_time' => 'required',
                'time_str' => 'required|numeric',
	            'outside_addr' => 'required',
	            'remarks' => 'required|max:255'
	        ];
	        $attributes = [
	            'start_time' => '外勤开始时间',
	            'end_time' => '外勤结束时间',
                'time_str' => '时间(小时)',
	            'outside_addr' => '外勤地点',
	            'remarks' => '工作内容'
	        ];
    	}
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if($inputs['type'] == 1 || $inputs['type'] == 3){
            $if_exist = $applyAttend->where('start_time', $inputs['start_time'])->where('end_time', $inputs['end_time'])->where('user_id', auth()->user()->id)->where('type', $inputs['type'])->where('status', '<>', 3)->first();//防止重复提交
            if(!empty($if_exist)){
                return response()->json(['code' => 0, 'message' => '您已经提交过了']);
            }
        }else{
            //加班 --所提时间必须同一个月
            $time_str = 0;
            $m = '';
            foreach ($inputs['time_data'] as $key => $value) {
                $if_exist = $applyAttend->where('start_time', $value['start_time'])->where('end_time', $value['end_time'])->where('user_id', auth()->user()->id)->where('type', $inputs['type'])->where('status', '<>', 3)->first();//防止重复提交
                if(!empty($if_exist)){
                    return response()->json(['code' => 0, 'message' => '您已经提交过时间段：'.$value['start_time'].'~'.$value['end_time']]);
                }
                if(!isset($value['start_time']) || empty($value['start_time'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数start_time']);
                }
                if(!isset($value['end_time']) || empty($value['end_time'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数end_time']);
                }
                if(strtotime($value['start_time']) >= strtotime($value['end_time'])){
                    return response()->json(['code' => -1, 'message' => '开始时间必须小于结束时间']);
                }
                if(!isset($value['time_str']) || !is_numeric($value['time_str'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数time_str']);
                }
                $time_str += $value['time_str'];
                $month = date('m', strtotime($value['start_time']));
                if($m != '' && $m != $month){
                    return response()->json(['code' => 0, 'message' => '只能提交同一个月份的加班时间']);
                }
                $m = $month;
            }
            if($time_str == 0){
                return response()->json(['code' => 0, 'message' => '请填写完整时间']);
            }
            $inputs['start_time'] = $inputs['time_data'][0]['start_time'];
            $inputs['end_time'] = $inputs['time_data'][0]['end_time'];
            $inputs['time_str'] = $time_str;
        }
        
        $days = prDates(date('Y-m-d', strtotime($inputs['start_time'])),date('Y-m-d', strtotime($inputs['end_time'])));
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->whereIn('date', $days)->where('type', $inputs['type'])->where('user_id', auth()->user()->id)->first();
        if(!empty($detail_list)){
            return response()->json(['code' => 0, 'message' => '该时间段已经申请过了']);
        }
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $applyAttend::type)->orderBy('id', 'desc')->first();//获取最新的配置
    	if(empty($setting_info)){
    		return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
    	}
        $steps = new \App\Models\AuditProcessStep;
        $step1 = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();
        if(empty($step1)){
            return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
        }
    	$setting_info['setting_content'] = unserialize($setting_info['setting_content']);
        $inputs['user_id'] = auth()->user()->id;
        $inputs['dept_id'] = auth()->user()->dept_id;
        
        $result = $applyAttend->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('出勤申请', '提交了'.$log_txt);
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);


    }


    /*
    * 出勤申请-列表
    * @author molin
    * @date 2018-10-08
    */
    public function index(){
        $inputs = request()->all();

        $applyAtt = new \App\Models\ApplyAttendance;
        $leave_list = $applyAtt->getDataList($inputs);
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $items = array();
        foreach ($leave_list['datalist'] as $key => $value) {
            $items[$key]['realname'] = $user_data['id_realname'][$value->user_id];
            if($value->status == 1){
                $items[$key]['type'] = '请假申请';
            }else if($value->status == 2){
                $items[$key]['type'] = '加班申请';
            }else{
                $items[$key]['type'] = '外勤申请';
            }
            $items[$key]['remarks'] = $value->remarks;
            if($value->status == 1){
                $items[$key]['status_txt'] = '已通过';
            }else if($value->status == 2){
                $items[$key]['status_txt'] = '已驳回';
            }else{
                $items[$key]['status_txt'] = '待审核';
            }
            $items[$key]['created_at'] = $value['created_at']->format('Y-m-d H:i:s'); 

        }
        if(isset($inputs['export']) && !empty($inputs['export'])){
            //导出
            $header = array('姓名','类型','内容','状态','提交时间');
            $filedata = pExprot($header, $items,'leave_list');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);

        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $items]);
    }

    /*
    * 出勤申请-详情
    * @author molin
    * @date 2018-10-08
    */
    public function show(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $inputs['apply_id'] = $inputs['id'];
        unset($inputs['id']);
        $audit_proces =new \App\Models\AuditProces;
        $proces_info = $audit_proces->getAttInfo($inputs);
        if(empty($proces_info)){
            return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
        }
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();

        //拼接数据
        $data = $user_info = $apply_att = array();
        //员工信息
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $user_data['id_dept'][$proces_info->user_id];
        $user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
        $data['user_info'] = $user_info;
        
        $apply_att['start_time'] = $proces_info->applyAtt->start_time;
        $apply_att['end_time'] = $proces_info->applyAtt->start_time;
        $apply_att['remarks'] = $proces_info->applyAtt->remarks;
        //申请类型：1请假申请2加班申请3外勤申请
        if($proces_info->applyAtt->type == 1){
            $apply_att['type'] = '请假申请';
            $holiday = new \App\Models\HolidayType;
            $type_data = $holiday->getIdToData();
            $apply_att['leave_type'] = $type_data[$proces_info->applyAtt->leave_type];
        }else if($proces_info->applyAtt->type == 2){
            $apply_att['type'] = '加班申请';
        }else{
            $apply_att['type'] = '外勤申请';
            $apply_att['outside_addr'] = $proces_info->applyAtt->outside_addr;
        }
        $apply_att['created_at'] = $proces_info->applyAtt->created_at->format('Y-m-d H:i:s');
        $apply_att['status_txt'] = $proces_info->applyAtt->status_txt;
        $data['apply_att'] = $apply_att;

        //加载已经审核的人的评价
        $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyAttendance)::type);
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
    *  考勤管理-申请汇总
    *  @author molin
    *   @date 2018-11-19
    */
    public function collect(){
        $inputs = request()->all();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            if(!isset($inputs['type_id']) || !is_numeric($inputs['type_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数type_id']);
            }
            if(!isset($inputs['apply_id']) || !is_numeric($inputs['apply_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数apply_id']);
            }
            $audit_proces = new \App\Models\AuditProces;
            switch ($inputs['type_id']) {
                case 8:
                //报备
                $proces_info = $audit_proces->getReportInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                //拼接数据
                $data = $user_info = array();
                //员工信息
                $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
                $user_info['dept'] = $user_data['id_dept'][$proces_info->user_id];
                $user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
                $data['user_info'] = $user_info;
                //打卡时间
                $data['daka_info'] = unserialize($proces_info->applyReport->content);
                //备注
                $data['remarks'] = $proces_info->applyReport->remarks;

                //加载已经审核的人的评价
                $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), $proces_info['type_id']);
                $data['pre_audit_opinion'] = $audit_opinions = array();
                if(!empty($pre_verify_users_data)){
                    foreach ($pre_verify_users_data as $key => $value) {
                        $audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
                        $audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
                    }
                    $data['pre_audit_opinion'] = $audit_opinions;
                }
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            default:
                //出勤
                $proces_info = $audit_proces->getAttInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                //拼接数据
                $data = $user_info = $apply_att = array();
                //员工信息
                $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
                $user_info['dept'] = $user_data['id_dept'][$proces_info->user_id];
                $user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
                $data['user_info'] = $user_info;
                
                $apply_att['start_time'] = $proces_info->applyAtt->start_time;
                $apply_att['end_time'] = $proces_info->applyAtt->start_time;
                $apply_att['remarks'] = $proces_info->applyAtt->remarks;
                //申请类型：1请假申请2加班申请3外勤申请
                if($proces_info->applyAtt->type == 1){
                    $apply_att['type'] = '请假申请';
                    $holiday = new \App\Models\HolidayType;
                    $type_data = $holiday->getIdToData();
                    $apply_att['leave_type'] = $type_data[$proces_info->applyAtt->leave_type];
                }else if($proces_info->applyAtt->type == 2){
                    $apply_att['type'] = '加班申请';
                }else{
                    $apply_att['type'] = '外勤申请';
                    $apply_att['outside_addr'] = $proces_info->applyAtt->outside_addr;
                }
                $data['apply_att'] = $apply_att;

                //加载已经审核的人的评价
                $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), $proces_info['type_id']);
                $data['pre_audit_opinion'] = $audit_opinions = array();
                if(!empty($pre_verify_users_data)){
                    foreach ($pre_verify_users_data as $key => $value) {
                        $audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
                        $audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
                    }
                    $data['pre_audit_opinion'] = $audit_opinions;
                }
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            }
        }
        if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
            if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
                return response()->json(['code' => -1, 'message' => '开始时间必须小于结束时间']);
            }   
        }
        $mains = new \App\Models\ApplyMain;
        $app_att = new \App\Models\ApplyAttendance;
        $app_rep = new \App\Models\ApplyReport;
        $inputs['type_ids'] = [1,8];
        if(isset($inputs['type_id']) && in_array($inputs['type_id'], [1,2,3])){
            //出勤申请--检索
            $inputs['type_ids'] = [1];
            $app_list = $app_att->where('type', $inputs['type_id'])->select(['id'])->get();
            $apply_ids = array();
            foreach ($app_list as $key => $value) {
                $apply_ids[] = $value->id;
            }
            $inputs['apply_ids'] = $apply_ids;
        }else if(isset($inputs['type_id']) && $inputs['type_id'] == 4){
            //报备--检索
            $inputs['type_ids'] = [8];
            $app_list = $app_rep->select(['id'])->get();
            $apply_ids = array();
            foreach ($app_list as $key => $value) {
                $apply_ids[] = $value->id;
            }
            $inputs['apply_ids'] = $apply_ids;
        }

        //我处理过的
        if(isset($inputs['is_mine']) && $inputs['is_mine'] == 1){
            $audit_proces = new \App\Models\AuditProces;
            $user_id = auth()->user()->id;
            $inputs['is_mine'] = $user_id;
            $proces_list =  $audit_proces->when(isset($inputs['type_ids']), function ($query) use ($inputs){
                                return $query->whereIn('type_id', $inputs['type_ids']);
                            })
                            ->where(function($query) use ($user_id){
                                $query->where('current_verify_user_id', $user_id)->orWhere('user_id', $user_id);
                            })
                            ->whereIn('status', [1,2])
                            ->select(['type_id', 'apply_id'])->get();
            $mine_data = $tmp_mine = array();
            foreach ($proces_list as $key => $value) {
                $tmp_mine['type_id'] = $value->type_id;
                $tmp_mine['apply_id'] = $value->apply_id;
                $mine_data[] = $tmp_mine;
            }
            $inputs['mine_data'] = $mine_data;
        }

        $data = $mains->getQueryList($inputs);
        
        $tmp_att = array();
        foreach ($data['datalist'] as $key => $value) {
            if($value->type_id == 1){
                $tmp_att[] = $value->apply_id;
            }
        }
        $att_data = array();
        if(!empty($tmp_att)){
            $att_list = $app_att->whereIn('id', $tmp_att)->select(['id','type','start_time','end_time','leave_time','time_str','time_data'])->get();
            foreach ($att_list as $key => $value) {
                $txt = '';
                $time = 0;
                if($value->type == 1){
                    $txt = '请假申请'; 
                    $time = $value->leave_time;
                }else if($value->type == 2){
                    $txt = '加班申请'; 
                    $time = $value->time_str;
                }else if($value->type == 3){
                    $txt = '外勤申请'; 
                    $time = $value->time_str;
                }
                $time_data = [0=>['start_time'=>$value->start_time,'end_time'=>$value->end_time, 'time'=>$time]];
                if($value->time_data){
                    $tdata = unserialize($value->time_data);
                    $time_data = [];
                    foreach ($tdata as $k => $v) {
                        $time_data[$k]['start_time'] = $v['start_time'];
                        $time_data[$k]['end_time'] = $v['end_time'];
                        $time_data[$k]['time'] = $v['time_str'];
                    }
                }
                $att_data[$value->id]['type'] = $txt;
                $att_data[$value->id]['time'] = $time;
                $att_data[$value->id]['time_data'] = $time_data;
            }
        }

        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            $items['type_id'] = $value->type_id;
            $items['apply_id'] = $value->apply_id;
            $items['time'] = 0;
            $items['time_data'] = [];
            if($value->type_id == 1){
                $items['type'] = $att_data[$value->apply_id]['type'];
                $items['time'] = $att_data[$value->apply_id]['time'];
                $items['time_data'] = $att_data[$value->apply_id]['time_data'];
            }else{
                $items['time'] = '--';
                $items['type'] = '报备申请';
            }
            $items['realname'] = $user_data['id_realname'][$value->user_id];
            $items['dept'] = $user_data['id_dept'][$value->user_id];
            $items['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $items['status_txt'] = $value->status_txt;
            $data['datalist'][$key] = $items;
        }
        //申请类型
        $type_list = [['type_id'=>1, 'name'=>'请假申请'],['type_id'=>2, 'name'=>'加班申请'],['type_id'=>3, 'name'=>'外勤申请'],['type_id'=>4, 'name'=>'报备申请']];
        $data['type_list'] = $type_list;
        $dept = new \App\Models\Dept;
        $dept_list = $dept->where('status', 1)->select(['id','name'])->get();
        $data['dept_list'] = $dept_list;
        $data['status_list'] = [['status'=>0, 'name'=>'审核中'],['status'=>1, 'name'=>'已通过'],['status'=>2, 'name'=>'已驳回']];
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

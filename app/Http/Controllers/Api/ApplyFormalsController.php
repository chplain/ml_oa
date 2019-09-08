<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyFormalsController extends Controller
{
    /** 
    *  转正申请
    *  @author molin
    *	@date 2018-09-26
    */
    public function store(){
    	$inputs = request()->all();
		$data = array();
        $formal = new \App\Models\ApplyFormal;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'formal'){
    		//加载数据
    		$user = new \App\Models\User;
    		$inputs['user_id'] = auth()->user()->id;
    		$user_info = $user->queryUserInfo($inputs);
    		$tmp = array();
    		$tmp['realname'] = $user_info->realname;
            $tmp['dept'] = $user_info->dept->name;
            $tmp['rank'] = $user_info->rank->name;
            $tmp['entry_date'] = $user_info->contracts->entry_date;
    		$data['user_info'] = $tmp;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $formal::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用^_^']);
        }
    	//保存数据
    	$rules = [
            'formal_date' => 'required|date_format:Y-m-d',
            'work_content' => 'required',
            'work_ok' => 'required',
            'work_learn' => 'required',
            'work_plan' => 'required'
        ];
        $attributes = [
            'formal_date' => '转正日期',
            'work_content' => '工作内容',
            'work_ok' => '工作目标完成情况',
            'work_learn' => '学习到的知识、技能',
            'work_plan' => '工作的计划及感想'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $user_contract = new \App\Models\UserContract;
        $contract = $user_contract->where('user_id', auth()->user()->id)->select(['positive_date'])->first();
        if(!empty($contract->positive_date)){
            //转正日期不为空
            return response()->json(['code' => 0, 'message' => '非法操作^_^']);
        }
        if(auth()->user()->id == 1){
            //超级管理员不能申请转正
            return response()->json(['code' => 0, 'message' => '超级管理员不能申请转正^_^']);
        }
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $formal::type)->orderBy('id', 'desc')->first();//获取最新的配置
    	if(empty($setting_info)){
    		return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员^_^']);
    	}
        $steps = new \App\Models\AuditProcessStep;
        $step1 = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();
        if(empty($step1)){
            return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员^_^']);
        }
    	$setting_info['setting_content'] = unserialize($setting_info['setting_content']);
        $inputs['user_id'] = auth()->user()->id;
        $inputs['dept_id'] = auth()->user()->dept_id;
        
        $result = $formal->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('转正申请', '提交了转正申请');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /** 
    *  转正申请汇总-列表
    *  @author molin
    *	@date 2018-09-27
    */
    public function index(){
    	$inputs = request()->all();
    	$formal = new \App\Models\ApplyFormal;
    	$formal_list = $formal->getDataList($inputs);
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	foreach ($formal_list['datalist'] as $key => $value) {
    		$formal_list['datalist'][$key]['dept'] = $value->hasDept->name;
    		$formal_list['datalist'][$key]['rank'] = $user_data['id_rank'][$value->user_id];
    		$formal_list['datalist'][$key]['username'] = $user_data['id_realname'][$value->user_id];
    		$formal_list['datalist'][$key]['dept_manager'] = $user_data['id_realname'][$value->hasDept->supervisor_id];
    		$formal_list['datalist'][$key]['status_txt'] = $value->hasMain->status_txt;
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $formal_list]);
    }

    /** 
    *  查看详情页面
    *  @author molin
    *	@date 2018-09-29
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
		$proces_info = $audit_proces->getFormalInfo($inputs);
		//拼接数据
		$data = $user_detail = $apply_formals = $attendance = $evaluation_score = array();
		$data['id'] = $proces_info['id'];
        //步骤信息
        $setting = new \App\Models\ApplyProcessSetting;
        $setting_info = $setting->where('id', $proces_info->setting_id)->select(['setting_content'])->first();//获取配置 
        $setting_content = unserialize($setting_info['setting_content']);
        // $user_data['id_rank'][$vv['user_id']].$user_data['id_realname'][$vv['user_id']];
        $current_step = $proces_info->current_step;
        $step_arr = ['0'=>['s'=> '1', 'if_current'=> $current_step == 'step0' ? 1:0, 'step' => '自我评价']];
        $j = 1;
        foreach ($setting_content as $key => $val) {
            $j++;
            $verify_user = array();
            foreach ($val as $v) {
                $verify_user[] = $user_data['id_rank'][$v['user_id']].$user_data['id_realname'][$v['user_id']];
            }
            $verify_user = implode('/', $verify_user);
            $step_arr[] = ['s' => $j, 'if_current'=> $current_step == $key ? 1:0, 'step' => $verify_user];
            
        }
        $data['step_arr'] = $step_arr;

		//员工信息
    	$user_detail['realname'] = $user_data['id_realname'][$proces_info->user_id];
		$user_detail['birthday'] = $proces_info->hasUser->birthday;
		$user_detail['dept'] = $user_data['id_dept'][$proces_info->user_id];
		$user_detail['rank'] = $user_data['id_rank'][$proces_info->user_id];
		if($proces_info->hasUser->sex == 1){
			$user_detail['sex'] = '男';
		}else if($proces_info->hasUser->sex == 2){
			$user_detail['sex'] = '女';
		}else{
			$user_detail['sex'] = '未知';
		}

		$user_detail['entry_date'] = $proces_info->contracts->entry_date;
		$user_detail['positive_date'] = $proces_info->contracts->positive_date ? $proces_info->contracts->positive_date : '--';
        $user_detail['created_at'] = $proces_info->applyFormal->created_at->format('Y-m-d H:i:s');
        $user_detail['status_txt'] = $proces_info->applyFormal->status_txt;
    	$data['user_detail'] = $user_detail;
    	//自我评价
    	$apply_formals['work_content'] = $proces_info->applyFormal->work_content;
    	$apply_formals['work_ok'] = $proces_info->applyFormal->work_ok;
    	$apply_formals['work_learn'] = $proces_info->applyFormal->work_learn;
    	$apply_formals['work_plan'] = $proces_info->applyFormal->work_plan;
        $apply_formals['formal_date'] = $proces_info->applyFormal->formal_date;
    	$data['apply_formals'] = $apply_formals;

    	//部门评审
    	//--出勤情况
        // $data['attendance'] = $attendance;
    	$data['attendance'] = $this->getAttendance($proces_info->user_id,$proces_info->contracts->entry_date,$proces_info->applyFormal->created_at->format('Y-m-d'));

        //评分
        $total_score = '--';
        if(!empty($proces_info->applyFormal->score)){
            $total_score = 0;
            $score = unserialize($proces_info->applyFormal->score);
            $evaluation_score['work_achievement'] = $score['work_achievement'];
            $evaluation_score['work_attitude'] = $score['work_attitude'];
            $evaluation_score['work_ability'] = $score['work_ability'];
            foreach ($score['work_achievement'] as $num) {
                $total_score += $num;
            }
            foreach ($score['work_attitude'] as $num) {
                $total_score += $num;
            }
            foreach ($score['work_ability'] as $num) {
                $total_score += $num;
            }
        }else{
            $evaluation_score['work_achievement'] = [1,1,1];
            $evaluation_score['work_attitude'] = [1,1,1,1];
            $evaluation_score['work_ability'] = [1,1,1,1];

        }
        
        $data['evaluation_score'] = $evaluation_score;
        $data['total_score'] = $total_score;
    	//加载已经审核的人的评价
    	$pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyFormal)::type);
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
    * 出勤情况
    * @author molin
    * @date 2018-09-19
    * user_id 申请人id  start_time  入职时间  end_time 申请转正的时间
    */
    public function getAttendance($user_id, $start_time, $end_time){
        //入职时间后一天 考虑到入职当天未打卡  申请提交前一天
        $end_time = date('Y-m-d', strtotime("$end_time -1 day"));//提交前一天
        $start_time = date('Y-m-d', strtotime("$start_time +1 day"));//入职第二天开始算
        $start_time_cr = $start_time.' 00:00:00';
        $end_time_cr = $end_time.' 23:59:59';//前一天为止
        $user = new \App\Models\User;
        $data = $user->queryUserInfo(['user_id'=>$user_id]);
        $attendance_record = new \App\Models\AttendanceRecord;
        //获取考勤记录
        $my_record_list = $attendance_record->where('user_id', $user_id)->whereBetween('punch_time',[$start_time_cr, $end_time_cr])->get();
        //日期对应的打卡记录
        $tmp_record = array();
        foreach ($my_record_list as $key => $value) {
            $tmp_record[date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
        }
        
        //假期类型
        $holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
        }

        //请假、外勤申请--用于抵消迟到、未签到、早退、未签退
        $apply_att = new \App\Models\ApplyAttendance;
        $apply_list = $apply_att->where('status', 1)->where('user_id', $user_id)->whereIn('type', [1,3])->get();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            $days = prDates(date('Y-m-d', strtotime($value->start_time)), date('Y-m-d', strtotime($value->end_time)));
            foreach ($days as $d) {
                $apply_data[$d][] = $value;
            }
        }

        //请假、外勤、加班详细信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->whereBetween('date',[$start_time, $end_time])->where('user_id', $user_id)->get();
        $qingjia_list = $jiaban_list = $waiqin_list = array();
        foreach ($detail_list as $key => $value) {
            if($value->type == 1){
                //请假
                $qingjia_list[$value->date][$value->leave_type] = floatval($value->time_str);
            }
            if($value->type == 2){
                //加班
                $jiaban_list[$value->date][] = floatval($value->time_str);
            }
            if($value->type == 3){
                //外勤
                $waiqin_list[$value->date][] = floatval($value->time_str);
            }
        }
        //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();

        //日期对应第一次打卡 和最后一次打卡记录
        $date_record_list = array();
        foreach ($tmp_record as $key => $value) {
            //获取上班时间配置
            $setting_info = array();
            foreach ($setting_list as $sett) {
                //取打卡日期大于配置修改的日期
                if(strtotime($key) > strtotime($sett['punch_time'])){
                    $setting_info = $sett;
                }
            }
            if(empty($setting_info)){
                $setting_info = $setting_list[0];
            }
            $am_start_time = strtotime($key.' '.$setting_info['am_start_time'].':00');//当天上班时间
            $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
            $pm_end_time = strtotime($key.' '.$setting_info['pm_end_time'].':00');//当天下班时间
            $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟
            $tmp_arr = array();
            foreach ($value as $k => $v) {
                if(strtotime($v['punch_time']) >= $am_start_before_time && strtotime($v['punch_time']) <= $pm_end_after_time){
                    $tmp_arr[] = strtotime($v['punch_time']); //一天内上班时间内的打卡记录
                }
            }

            $first = date('H:i:s', min($tmp_arr));//第一次打卡时间
            $last = date('H:i:s', max($tmp_arr));//最后一次打卡时间
            $date_record_list[$key]['first'] = $first;
            $date_record_list[$key]['last'] = $last;

        }

        //节假日、工作日列表
        $attendance =  new \App\Models\Attendance;
        $attendance_list = $attendance->whereBetween('date',[$start_time, $end_time])->get();
        //拼装我的考勤数组
        $items = array();
        //旷工
        $queqin_total = 0;
        //迟到、早退、未签到、未签退
        $chidao_num = $zaotui_num = $weiqiandao_num = $weiqiantui_num = $chidao_sum = $zaotui_sum = 0;
        $jiaqi_total = $jiaqi_time = array();
        foreach ($attendance_list as $key => $value) {
            //获取上班时间配置
            $setting_info = array();
            foreach ($setting_list as $sett) {
                //取打卡日期大于配置修改的日期
                if(strtotime($value['date']) > strtotime($sett['created_at'])){
                    $setting_info = $sett;
                }
            }
            if(empty($setting_info)){
                $setting_info = $setting_list[0];
            }
            $a_time = $setting_info['am_start_time'];
            $p_time = $setting_info['pm_end_time'];
            $am_start_time = strtotime($value['date'].' '.$a_time.':00');//当天上班时间
            $am_end_time = strtotime($value['date'].' '.$setting_info['am_end_time'].':00');//当天上午下班时间
            $pm_start_time = strtotime($value['date'].' '.$setting_info['pm_start_time'].':00');//当天下午下班时间
            $pm_end_time = strtotime($value['date'].' '.$p_time.':00');//当天下班时间
            $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
            $am_start_after_time = $am_start_time + $setting_info['am_start_after_time'] * 60;//上班后多少分钟
            $pm_end_before_time = $pm_end_time - $setting_info['pm_end_before_time'] * 60;//下班前多少分钟
            $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟

            $queqin_num = 0;//缺勤次数
            $cur_day_time = $jiaban_day_time = 0;
            $chidao_time = $zaotui_time = 0;
            //假期
            $shijia = $bingjia = $nianjia = $hunjia = $chanjia = $sangjia = $qita = 0;
            if(isset($date_record_list[$value['date']]) && $value['type'] == 0){
                //工作日
                $first_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['first']);//第一次打卡时间
                $last_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['last']);//最后一次打卡时间
                if(!empty($first_time)){
                    //正常出勤工时
                    if($first_time <= ($am_start_time+59) && $last_time >= $pm_end_time){
                        //正常打卡
                        $shangwu_time = ($am_end_time - $am_start_time) / 3600;//小时
                        $xiawu_time = ($pm_end_time - $pm_start_time) / 3600;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $last_time >= $pm_end_time){
                        //早上迟到
                        $shangwu_time = ($am_end_time - $first_time) / 3600;//小时
                        $xiawu_time = ($pm_end_time - $pm_start_time) / 3600;//小时
                    }else if($first_time >= $am_end_time && $first_time <= ($pm_start_time+59) && $last_time >= $pm_end_time){
                        //中午第一次打卡
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($pm_end_time - $pm_start_time) / 3600;//小时
                    }else if($first_time > ($pm_start_time+59) && $first_time < $pm_end_time && $last_time >= $pm_end_time){
                        //下午第一次打卡
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($pm_end_time - $first_time) / 3600;//小时
                    }else if($first_time <= ($am_start_time+59) && $last_time > ($am_start_time+59) && $last_time < $am_end_time){
                        //第一次打卡正常 最后一次打卡在早上
                        $shangwu_time = ($last_time - $am_start_time) / 3600;//小时
                        $xiawu_time = 0;//小时
                    }else if($first_time <= ($am_start_time+59) && $last_time >= $am_end_time && $last_time < $pm_start_time){
                        //第一次打卡正常 最后一次打卡在中午
                        $shangwu_time = ($am_end_time - $am_start_time) / 3600;//小时
                        $xiawu_time = 0;//小时
                    }else if($first_time <= ($am_start_time+59) && $last_time >= $pm_start_time && $last_time < $pm_end_time){
                        //第一次打卡正常 最后一次打卡在下午
                        $shangwu_time = ($am_end_time - $am_start_time) / 3600;//小时
                        $xiawu_time =  ($last_time - $pm_start_time) / 3600;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $first_time < $last_time && $last_time <= $am_end_time){
                        //第一次迟到或者未签到 最后一次打卡在上午下班前
                        $shangwu_time = ($last_time - $first_time) / 3600;//小时
                        $xiawu_time =  0;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $first_time < $last_time && $last_time > $am_end_time && $last_time <= ($pm_start_time+59)){
                        //第一次迟到或者未签到 最后一次打卡在中午
                        $shangwu_time = ($am_end_time - $first_time) / 3600;//小时
                        $xiawu_time =  0;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $first_time < $last_time && $last_time > ($pm_start_time+59) && $last_time < $pm_end_time){
                        //第一次迟到或者未签到 最后一次打卡在下午下班前
                        $shangwu_time = ($am_end_time - $first_time) / 3600;//小时
                        $xiawu_time = ($last_time - $pm_start_time) / 3600;//小时
                    }else if($first_time >= $am_end_time && $first_time <= ($pm_start_time+59) && $first_time < $last_time && $last_time > ($pm_start_time+59) && $last_time < $pm_end_time){
                        //第一次中午 最后一次打卡在下午下班前
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($last_time - $pm_start_time) / 3600;//小时
                    }else if($first_time >= ($pm_start_time+59) && $first_time < $last_time && $last_time < $pm_end_time){
                        //第一次打卡在下午上班后 最后一次打卡在下午下班前
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($last_time - $first_time) / 3600;//小时
                    }else{
                        //在有效打卡时间内 早退
                        $shangwu_time = 0;//小时
                        $xiawu_time = 0;//小时
                    }
                    $cur_day_time = intval($shangwu_time + $xiawu_time);//出勤工时
                    //迟到、早退、未签到、未签退
                    //首先判断当天是否正常上班和正常下班  
                    $start_zhengchang = false;
                    $end_zhengchang = false;
                    if($first_time >= $am_start_before_time && $first_time <= ($am_start_time + 59)){
                        //打卡时间在上班前有效时间内 属于正常打卡
                        $start_zhengchang = true;//正常上班
                    }
                    if($last_time >= $pm_end_time && $last_time <= ($pm_end_after_time)){
                        //打卡时间在下班前有效时间内 属于正常打卡
                        $end_zhengchang = true;//正常下班
                    }
                    $if_chidao = false;//是否迟到
                    $if_zaotui = false;//是否早退
                    $if_queqin = false;//是否异常考勤
                    if(!$start_zhengchang && $end_zhengchang){
                        //未正常上班  但是正常下班
                        // 迟到
                        if($first_time < $am_start_after_time && $first_time > $am_start_time){
                            //第一个打卡记录大于上班时间 小于有效时间  迟到
                            $if_chidao = true; 
                            //计算迟到时间  第一次打卡时间 - 上班时间 = 迟到时间
                            $chidao_time = floor(($first_time - $am_start_time) / 60);
                        }
                        //是否异常缺勤
                        if($first_time >= $am_start_after_time && $first_time < $pm_end_before_time){
                            //第一次打卡在10:30-16:30
                            $if_queqin = true;//判断缺勤
                        } 
                        //查看是否有请假、外勤
                        if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                            if($am_start_time >= strtotime($app->start_time) && $am_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除迟到、未签到记录、迟到时间
                                $if_chidao = false; 
                                $chidao_time = 0;
                                $if_queqin = false;
                            }
                        }}
                        if($first_time > $pm_end_before_time && $first_time < $pm_end_after_time){
                            //第一次打卡在16:30-00:00  有请假和外勤也不能抵消  只有报备才能抵消 
                            $if_queqin = true;
                        }

                    }else if($start_zhengchang && !$end_zhengchang){
                        //正常上班  但是未正常下班
                        // 早退
                        if($last_time >= $pm_end_before_time && $last_time < $pm_end_time){
                            //最后一个打卡记录大于下班前有效时间小于下班时间  早退
                            $if_zaotui = true; 
                            //计算早退时间  下班时间 - 最后一次打卡时间 = 早退时间
                            $zaotui_time = floor(($pm_end_time - $last_time) / 60);
                        }
                        //是否异常缺勤
                        if($last_time > $am_start_after_time && $last_time < $pm_end_before_time){
                            //最后一次打卡在10:30-16:30
                            $if_queqin = true;//判断缺勤
                        }
                        //查看是否有请假、外勤
                        if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                            if($pm_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除早退、未签退记录、早退时间
                                $if_zaotui = false; 
                                $zaotui_time = 0;
                                $if_queqin = false;
                            }
                        }}
                        if($last_time > $am_start_before_time && $last_time < $am_start_after_time){
                            //最后一次打卡在07:30-10:30  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                        }
                    }else if(!$start_zhengchang && !$end_zhengchang){
                        //未正常上班  也未正常下班
                        //迟到
                        if($first_time < $am_start_after_time && $first_time > $am_start_time){
                            //第一个打卡记录大于上班时间 小于有效时间  迟到
                            $if_chidao = true; 
                            //计算迟到时间  第一次打卡时间 - 上班时间 = 迟到时间
                            $chidao_time = floor(($first_time - $am_start_time) / 60);
                        }
                        // 早退
                        if($last_time >= $pm_end_before_time && $last_time < $pm_end_time){
                            //最后一个打卡记录大于下班前有效时间小于下班时间  早退
                            $if_zaotui = true; 
                            //计算早退时间  下班时间 - 最后一次打卡时间 = 早退时间
                            $zaotui_time = floor(($pm_end_time - $last_time) / 60);
                        }
                        if($first_time > $am_start_after_time && $first_time < $pm_end_before_time && $last_time > $am_start_after_time && $last_time < $pm_end_before_time){
                            //第一次和最后一次都在 10:30-16:30之间
                            $if_queqin = true;
                        }
                        //查看是否有请假、外勤
                        if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                            if($am_start_time >= strtotime($app->start_time) && $am_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除迟到、未签到记录、迟到时间
                                $if_chidao = false; 
                                $chidao_time = 0;
                            }
                            if($pm_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除早退、未签退记录、早退时间
                                $if_zaotui = false; 
                                $zaotui_time = 0;
                            }
                            if(strtotime($app->start_time) <= $am_start_time && strtotime($app->end_time) >= $pm_end_time){
                                $if_queqin = false;//请假一天 抵消异常缺勤
                            }
                        }}
                        if($first_time > $pm_end_before_time && $first_time < $pm_end_after_time){
                            //第一次打卡在16:30-00:00  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                        }
                        if($last_time > $am_start_before_time && $last_time < $am_start_after_time){
                            //最后一次打卡在07:30-10:30  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                        }
                    }
                    if($if_chidao){
                        $chidao_num ++;
                    }
                    if($if_zaotui){
                        $zaotui_num ++;
                    }
                    if($if_queqin){
                        $queqin_num ++;
                    }
                    $chidao_sum += $chidao_time;
                    $zaotui_sum += $zaotui_time;
                }
            }
            if(isset($jiaban_list[$value['date']])){
                $jiaban_day_time = array_sum($jiaban_list[$value['date']]);
            }
            
            if($value['type'] == 0){
                //旷工天数
                if(!isset($date_record_list[$value['date']])){
                    //工作日无打卡记录 
                    $if_queqin = true;
                    if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                        if($am_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                            $if_queqin = false;
                        }
                    }}
                    if($if_queqin){
                        $queqin_num++;
                    }
                }
            }
            $queqin_total += $queqin_num; //异常缺勤

            //计算请假(工作日)
            if($value['type'] == 0 && isset($qingjia_list[$value['date']]) && is_array($qingjia_list[$value['date']])){
                foreach ($qingjia_list[$value['date']] as $kk => $vv) {
                    $jiaqi_time[$kk]['num'] = $jiaqi_time[$kk]['num'] ?? 0;
                    $jiaqi_time[$kk]['num'] += $vv;
                }
            }
            foreach ($type_data as $type_id => $v) {
                if(isset($jiaqi_time[$type_id])){
                    $jiaqi_total[$type_id]['num'] = $jiaqi_time[$type_id]['num'];
                    $jiaqi_total[$type_id]['name'] = $v['name'];
                }else{
                    $jiaqi_total[$type_id]['num'] = 0;
                    $jiaqi_total[$type_id]['name'] = $v['name'];
                }
            }
        }
        
        
        $items['chidao_sum'] = $chidao_sum;//迟到
        $items['zaotui_sum'] = $zaotui_sum;//早退
        $items['queqin_total'] = $queqin_total;//异常考勤
        foreach ($type_data as $type_id => $val) {
            $items['jiaqi_'.$type_id] = $jiaqi_total[$type_id]['num'] ?? 0;
        }
        $fields = array_keys($items);

        $table_head = ['chidao_sum'=> '迟到时间','zaotui_sum'=>'早退时间','queqin_total'=>'异常考勤(次)','chufa'=>'处罚','jiangli'=>'奖励'];
        $tmp = [];
        foreach ($fields as $value) {
            if(substr($value, 0, 6) == 'jiaqi_'){
                $tmp[$value] = $type_data[substr($value,6)]['name'];
            }
        }

        $items['chufa'] = 0;
        $items['jiangli'] = 0;
        $headers = array_merge($table_head, $tmp);
        
        return ['table_head'=>$headers, 'table_body'=>$items];
    } 


}

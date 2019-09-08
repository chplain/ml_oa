<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class AttendanceRecordController extends Controller
{


    /**
    * 我的出勤-主界面
    * @author molin
    * @date 2018-09-19
    */
    public function index(){
    	$inputs = request()->all();
    	if(!isset($inputs['year']) || empty($inputs['year'])){
    		$inputs['year'] = date('Y');
    	}
    	if(!isset($inputs['month']) || empty($inputs['month'])){
    		$inputs['month'] = date('m');
    	}
        $holiday_year = new \App\Models\HolidayYear;
        $my_year_holiday = $holiday_year->getYearHoliday();//我当月应有年假
        $start_time = date($inputs['year'].'-'.$inputs['month'].'-01');
        $end_time = date('Y-m-d', strtotime("$start_time +1 month -1 day"));
    	$user_info = auth()->user();
    	$attendance_record = new \App\Models\AttendanceRecord;
    	//获取考勤记录
    	$my_record_list = $attendance_record->where('user_id', $user_info->id)->where('year', $inputs['year'])->where('month', $inputs['month'])->get();

        //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();//根据签到时间

        //日期对应的打卡记录
        $tmp_record = array();
        foreach ($my_record_list as $key => $value) {
            $tmp_record[date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
        }

    	//日期对应的打卡记录
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
        // dd($date_record_list);
        //节假日、工作日列表
        $attendance =  new \App\Models\Attendance;
        $attendance_list = $attendance->where('year', $inputs['year'])->where('month', $inputs['month'])->get();
        
        $date_work = array();
        foreach ($attendance_list as $key => $value) {
            $date_work[$value->date] = $value->type;
        }

        //请假、出勤申请--用于抵消迟到、早退
        $apply_att = new \App\Models\ApplyAttendance;
        $apply_list = $apply_att->where('status', 1)->where('user_id', $user_info->id)->whereIn('type', [1,3])->get();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            $days = prDates(date('Y-m-d', strtotime($value->start_time)),date('Y-m-d', strtotime($value->end_time)));
            foreach ($days as $d) {
                $apply_data[$d][] = $value;
            }
        }
        //请假、出勤、加班信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->where('year', $inputs['year'])->where('month', $inputs['month'])->where('user_id', $user_info->id)->get();
    	
        $holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name', 'if_nianjia'])->get();
        $type_data = array();
        $nianjia_id = 0;
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
            $type_data[$value->id]['if_nianjia'] = $value->if_nianjia;
            if($value->if_nianjia == 1){
                $nianjia_id = $value->id;//年假id
            }
        }
        if($nianjia_id > 0){
            $my_year_qingjia = $apply_detail->where('type', 1)->where('leave_type', $nianjia_id)->whereBetween('date', [date('Y-01-01',time()), $end_time])->where('user_id', $user_info->id)->sum('time_str');//今年已使用年假
        }else{
            $my_year_qingjia = 0;
        }
        $waiqin_time = $jiaban_time = 0;//外勤 、加班时间
        $leave_days = array();//请假天数
        foreach ($detail_list as $val) {
            if($val->type == 1){
                $leave_days[$val->leave_type]['num'] = $leave_days[$val->leave_type]['num'] ?? 0;
                $leave_days[$val->leave_type]['num'] += $val->time_str;
            }else if($val->type == 3){
                $waiqin_time += $val->time_str;
            }
            if($val->type == 2){
                $jiaban_time += $val->time_str;
            }
        }
        $has_nianjia = array();
        foreach ($type_data as $type_id => $v) {
            if(isset($leave_days[$type_id])){
                $leave_days[$type_id]['num'] = $leave_days[$type_id]['num'];
                $leave_days[$type_id]['name'] = $v['name'];
            }else{
                $leave_days[$type_id]['num'] = 0;
                $leave_days[$type_id]['name'] = $v['name'];
            }
            if($v['if_nianjia'] == 1){
                $has_nianjia['num'] = $my_year_holiday - $my_year_qingjia;
                $has_nianjia['name'] = '剩余年假';
            }
        }
        $leave_days[] = $has_nianjia;
        //免签人员
        $free = new \App\Models\AttendanceFree;
        $free_list = $free->get();
        $free_data = array();
        foreach ($free_list as $key => $value) {
            $free_data[] = $value->user_id;//免签
        }
        
    	
    	$data = array();
    	//拼装我的考勤数组
        $chidao_num = $zaotui_num = $queqin_total = 0;
    	$chidao_sum = $zaotui_sum = 0;//迟到 早退时间累积
        foreach ($attendance_list as $key => $value) {
            $chidao_time = $zaotui_time = 0;
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
            if(isset($setting_info['am_start_time']) && !empty($setting_info['am_start_time']) && isset($setting_info['pm_end_time']) && !empty($setting_info['pm_end_time'])){
                $a_time = $setting_info['am_start_time'];
                $p_time = $setting_info['pm_end_time'];
                $am_start_time = strtotime($value['date'].' '.$a_time.':00');//当天上班时间
                $am_end_time = strtotime($value['date'].' '.$setting_info['am_end_time'].':00');//当天上午下班时间
                $pm_start_time = strtotime($value['date'].' '.$setting_info['pm_start_time'].':00');//当天下午上班时间
                $pm_end_time = strtotime($value['date'].' '.$p_time.':00');//当天下班时间
                $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
                $am_start_after_time = $am_start_time + $setting_info['am_start_after_time'] * 60;//上班后多少分钟
                $pm_end_before_time = $pm_end_time - $setting_info['pm_end_before_time'] * 60;//下班前多少分钟
                $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟

                if(isset($date_record_list[$value['date']]) && $value['type'] == 0){
                    //工作日
                    //首先判断当天是否正常上班和正常下班  
                    $start_zhengchang = false;
                    $end_zhengchang = false;
                    $first_time = $value['date'].' '.$date_record_list[$value['date']]['first'];
                    $last_time = $value['date'].' '.$date_record_list[$value['date']]['last'];
                    $first_time = strtotime($first_time);
                    $last_time = strtotime($last_time);
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
                    $if_queqin = false;//是否缺勤
                    if($start_zhengchang && $end_zhengchang){
                        //正常 
                        continue;
                    }else if(!$start_zhengchang && $end_zhengchang){
                        //未正常上班  但是正常下班
                        //迟到
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
                        //早退
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
                    //当天查看，早退
                    if(time() < $pm_end_time){
                        $if_zaotui = false;
                        $zaotui_time = 0;
                    }
                    if($if_chidao){
                        $chidao_num ++;
                    }
                    if($if_zaotui){
                        $zaotui_num ++;
                    }
                    if($if_queqin && time() > $pm_end_after_time){
                        $queqin_total ++;
                    }
                    $chidao_sum += $chidao_time;
                    $zaotui_sum += $zaotui_time;
                }else if(!isset($date_record_list[$value['date']]) && $value['type'] == 0){
                    //判断是否请假、外勤
                    $if_queqin = true; 
                    if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                        if($am_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                            $if_queqin = false;
                        }
                    }}
                    if($if_queqin && time() > $pm_end_after_time){
                        $queqin_total ++;//一天都没有打卡记录
                    }
                }
            }

        }
    	
    	$data['chidao_num'] = !in_array($user_info->id, $free_data) ? $chidao_num : 0;
    	$data['zaotui_num'] = !in_array($user_info->id, $free_data) ? $zaotui_num : 0;
        $data['chidao_sum'] = !in_array($user_info->id, $free_data) ? $chidao_sum : 0;
        $data['zaotui_sum'] = !in_array($user_info->id, $free_data) ? $zaotui_sum : 0;
    	$data['queqin_total'] = !in_array($user_info->id, $free_data) ? $queqin_total : 0;//缺勤次数

        $data['jiaban_time'] = $jiaban_time;
        $data['waiqin_time'] = $waiqin_time;
        $data['leave_days'] = $leave_days;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

    /**
    * 我的考勤-详情
    * @author molin
    * @date 2018-09-19
    */
    public function detail(){
    	$inputs = request()->all();
    	if(!isset($inputs['year']) || empty($inputs['year'])){
    		$inputs['year'] = date('Y');
    	}
    	if(!isset($inputs['month']) || empty($inputs['month'])){
    		$inputs['month'] = date('m');
    	}
        $start_time = date($inputs['year'].'-'.$inputs['month'].'-01');
        $end_time = date('Y-m-d', strtotime("$start_time +1 month -1 day"));
    	$user_info = auth()->user();
    	$attendance_record = new \App\Models\AttendanceRecord;
    	//获取考勤记录
    	$my_record_list = $attendance_record->where('user_id', $user_info->id)->where('year', $inputs['year'])->where('month', $inputs['month'])->get();

    	//日期对应的打卡记录
    	$tmp_record = array();
    	foreach ($my_record_list as $key => $value) {
    		$tmp_record[date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
    	}

        $holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
        }

        //请假、出勤申请--用于抵消迟到、未签到、早退、未签退
        $apply_att = new \App\Models\ApplyAttendance;
        $apply_list = $apply_att->where('status', 1)->where('user_id', $user_info->id)->whereIn('type', [1,3])->get();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            $days = prDates(date('Y-m-d', strtotime($value->start_time)), date('Y-m-d', strtotime($value->end_time)));
            foreach ($days as $d) {
                $apply_data[$d][] = $value;
            }
        }
        //请假、出勤、加班详细信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->getDetailList(['year' => $inputs['year'], 'month' => $inputs['month'], 'user_id' => $user_info->id]);
        $qingjia_list = $jiaban_list = $waiqin_list = $view_data = array();
        $a = $b = $c = array();
        foreach ($detail_list as $key => $value) {
            if($value->type == 1){
                //请假
                $qingjia_list[$value->date][$value->leave_type] = $value->time_str;
                $a[$value->date][] = date('Y-m-d H:i:s', strtotime($value->hasApply->start_time)).'~'.date('Y-m-d H:i:s', strtotime($value->hasApply->end_time));
            }
            if($value->type == 2){
                //加班
                $jiaban_list[$value->date][] = $value->time_str;
                if(!empty($value->hasApply->time_data)){
                    $time_data = unserialize($value->hasApply->time_data);
                    foreach ($time_data as $v) {
                        if($value->date == date('Y-m-d', strtotime($v['start_time']))){
                            $b[$value->date][] = date('Y-m-d H:i:s', strtotime($v['start_time'])).'~'.date('Y-m-d H:i:s', strtotime($v['end_time']));
                        }
                    }
                }else{
                    $b[$value->date][] = date('Y-m-d H:i:s', strtotime($value->hasApply->start_time)).'~'.date('Y-m-d H:i:s', strtotime($value->hasApply->end_time));
                }
            }
            if($value->type == 3){
                //外勤
                $waiqin_list[$value->date][] = $value->time_str;
                $c[$value->date][] = date('Y-m-d H:i:s', strtotime($value->hasApply->start_time)).'~'.date('Y-m-d H:i:s', strtotime($value->hasApply->end_time));
            }

        }
        $view_data['if_qingjia'] = $a;
        $view_data['if_jiaban'] = $b;
        $view_data['if_waiqin'] = $c;
        //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();
        // dd($setting_list);

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
    	$attendance_list = $attendance->where('year', $inputs['year'])->where('month', $inputs['month'])->get();
        //免签人员
        $free = new \App\Models\AttendanceFree;
        $free_list = $free->get();
        $free_data = array();
        foreach ($free_list as $key => $value) {
            $free_data[] = $value->user_id;//免签
        }

    	//拼装我的考勤数组
    	$items = array();
        //出勤工时 异常缺勤工时
        $chuqin_total = $queqin_total = 0;
        //迟到、早退、
        $chidao_num = $zaotui_num = $chidao_sum = $zaotui_sum = 0;
        //加班、外勤统计
        $gongzuori_jiaban_total = $zhoumo_jiaban_total = $jiejiari_jiaban_total = $waiqin_time_total = 0;
        //假期统计
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

    		$day = intval(date('d', strtotime($value['date'])));
    		$items[$day]['date'] = $value['date'];
    		$items[$day]['week'] = date('w',strtotime($value['date']));

            $cur_day_time = $jiaban_day_time = 0;
            $chidao_time = $zaotui_time = 0;
            $gongzuori_jiaban =  $zhoumo_jiaban = $jiejiari_jiaban = $waiqin_time =0;//加班、外勤
            //假期
    		if(isset($date_record_list[$value['date']]) && $value['type'] == 0){
    			//工作日
                $first_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['first']);
                $last_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['last']);
    			$items[$day]['if_jiaban'] = $view_data['if_jiaban'][$value['date']] ?? array();
                $items[$day]['if_qingjia'] = $view_data['if_qingjia'][$value['date']] ?? array();
                $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
                $first_txt = $date_record_list[$value['date']]['first'];
                $last_txt = $date_record_list[$value['date']]['last'];
                if($first_time == $last_time){
                    //只有一次打卡记录
                    if($am_end_time >= $first_time){
                        $last_txt = '未打卡';
                    }
                    if($am_end_time < $first_time){
                        $first_txt = '未打卡';
                    }
                }
                $items[$day]['work_time'] = $first_txt.'——'.$last_txt;
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
                $first_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['first']);//第一次打卡时间
                if($first_time >= $am_start_before_time && $first_time <= ($am_start_time + 59)){
                    //打卡时间在上班前有效时间内 属于正常打卡
                    $start_zhengchang = true;//正常上班
                }
                $last_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['last']);//第一次打卡时间
                if($last_time >= $pm_end_time && $last_time <= ($pm_end_after_time)){
                    //打卡时间在下班前有效时间内 属于正常打卡
                    $end_zhengchang = true;//正常下班
                }
                
                $if_chidao = false;//是否迟到
                $if_zaotui = false;//是否早退
                $if_queqin = false;//是否异常考勤
                if(!$start_zhengchang && $end_zhengchang){
                    //未正常上班  但是正常下班
                    //迟到
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
                
                //当天查看，早退
                if(time() < $pm_end_time){
                    $if_zaotui = false;
                    $zaotui_time = 0;
                }
                if($if_chidao){
                    $chidao_num ++;
                }
                if($if_zaotui){
                    $zaotui_num ++;
                }
                if($if_queqin && time() > $pm_end_after_time){
                    $queqin_total ++;
                }
                
                $chidao_sum += $chidao_time;
                $zaotui_sum += $zaotui_time;

    		}else if(isset($date_record_list[$value['date']]) && $value['type'] == 1){
    			//周六、周日
                $first_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['first']);
                $last_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['last']);
    			$items[$day]['if_jiaban'] = $view_data['if_jiaban'][$value['date']] ?? array();
                $items[$day]['if_qingjia'] = $view_data['if_qingjia'][$value['date']] ?? array();
                $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
                $first_txt = $date_record_list[$value['date']]['first'];
                $last_txt = $date_record_list[$value['date']]['last'];
                if($first_time == $last_time){
                    //只有一次打卡记录
                    if($am_end_time >= $first_time){
                        $last_txt = '未打卡';
                    }
                    if($am_end_time < $first_time){
                        $first_txt = '未打卡';
                    }
                }
                $items[$day]['work_time'] = $first_txt.'——'.$last_txt;
    		}else if(isset($date_record_list[$value['date']]) && $value['type'] == 2){
    			//节假日
                $first_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['first']);
                $last_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['last']);
                $items[$day]['if_jiaban'] = $view_data['if_jiaban'][$value['date']] ?? array();
                $items[$day]['if_qingjia'] = $view_data['if_qingjia'][$value['date']] ?? array();
                $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
                $first_txt = $date_record_list[$value['date']]['first'];
                $last_txt = $date_record_list[$value['date']]['last'];
                if($first_time == $last_time){
                    //只有一次打卡记录
                    if($am_end_time >= $first_time){
                        $last_txt = '未打卡';
                    }
                    if($am_end_time < $first_time){
                        $first_txt = '未打卡';
                    }
                }
                $items[$day]['work_time'] = $first_txt.'——'.$last_txt;
    		}else{
    			$items[$day]['if_jiaban'] = $view_data['if_jiaban'][$value['date']] ?? array();
                $items[$day]['if_qingjia'] = $view_data['if_qingjia'][$value['date']] ?? array();
                $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
    			$items[$day]['work_time'] = '';
    		}
            if(isset($jiaban_list[$value['date']])){
                $jiaban_day_time = array_sum($jiaban_list[$value['date']]);
            }
            $chuqin_total += $cur_day_time;//出勤时长

            //计算加班时间
            if($value['type'] == 0){
                //工作日加班时间
                if(isset($jiaban_list[$value['date']]) && !empty($jiaban_list[$value['date']])){
                    $gongzuori_jiaban = array_sum($jiaban_list[$value['date']]);
                    $items[$day]['if_jiaban'] = $view_data['if_jiaban'][$value['date']] ?? array();
                }
                if(isset($qingjia_list[$value['date']]) && !empty($qingjia_list[$value['date']])){
                    $items[$day]['if_qingjia'] = $view_data['if_qingjia'][$value['date']] ?? array();
                    $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
                }
                if(isset($waiqin_list[$value['date']]) && !empty($waiqin_list[$value['date']])){
                    $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
                }
                //异常缺勤
                if(!isset($date_record_list[$value['date']]) && time() > $pm_end_after_time){
                    //工作日无打卡记录  记一天缺勤
                    $if_queqin = true;
                    if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                        if($am_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                            $if_queqin = false;
                        }
                    }}
                    if($if_queqin){
                        $queqin_total++;
                    }
                }
            }else if($value['type'] == 1){
                //周末加班时间
                if(isset($jiaban_list[$value['date']]) && !empty($jiaban_list[$value['date']])){
                    $zhoumo_jiaban = array_sum($jiaban_list[$value['date']]);
                    $items[$day]['if_jiaban'] = $view_data['if_jiaban'][$value['date']] ?? array();
                }
                if(isset($waiqin_list[$value['date']]) && !empty($waiqin_list[$value['date']])){
                    $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
                }
            }else if($value['type'] == 2){
                //节假日加班时间
                if(isset($jiaban_list[$value['date']]) && !empty($jiaban_list[$value['date']])){
                    $jiejiari_jiaban = array_sum($jiaban_list[$value['date']]);
                    $items[$day]['if_jiaban'] = $view_data['if_jiaban'][$value['date']] ?? array();
                }
                if(isset($waiqin_list[$value['date']]) && !empty($waiqin_list[$value['date']])){
                    $items[$day]['if_waiqin'] = $view_data['if_waiqin'][$value['date']] ?? array();
                }
            }

            $gongzuori_jiaban_total += $gongzuori_jiaban;
            $zhoumo_jiaban_total += $zhoumo_jiaban;
            $jiejiari_jiaban_total += $jiejiari_jiaban;

            //计算外勤
            if(isset($waiqin_list[$value['date']]) && !empty($waiqin_list[$value['date']])){
                $waiqin_time = array_sum($waiqin_list[$value['date']]);
            }
            $waiqin_time_total += $waiqin_time;

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
    	$data = array();
    	$data['date_list'] = $items;
        $data['chuqin_total'] = !in_array($user_info->id, $free_data) ? $chuqin_total : 0;
        $data['queqin_total'] = !in_array($user_info->id, $free_data) ? $queqin_total : 0;
        $data['chidao_sum'] = !in_array($user_info->id, $free_data) ? $chidao_sum : 0;
        $data['zaotui_sum'] = !in_array($user_info->id, $free_data) ? $zaotui_sum : 0;
        $data['gongzuori_jiaban_total'] = $gongzuori_jiaban_total;
        $data['zhoumo_jiaban_total'] = $zhoumo_jiaban_total;
        $data['jiejiari_jiaban_total'] = $jiejiari_jiaban_total;
        $data['waiqin_time_total'] = $waiqin_time_total;
        $data['jiaqi_total'] = $jiaqi_total;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
    * 报备
    * @author molin
    * @date 2018-09-20
    */
    public function report(){
        //免签人员
    	$user_info = auth()->user();
        $free = new \App\Models\AttendanceFree;
        $free_list = $free->get();
        $free_data = array();
        foreach ($free_list as $key => $value) {
            $free_data[] = $value->user_id;//免签
        }
        if(in_array($user_info->id, $free_data)){
            return response()->json(['code' => 0, 'message'=>'您是免签人员，不需要报备']);
        }
    	//获取前一个月的1号到上一天的数据
    	$pre_date = date("Y-m-01",strtotime("-1 month")).' 00:00:00';
    	$cur_date = date('Y-m-d', strtotime("-1 day")).' 23:59:59';
        $inputs['start_date'] = date('Y-m-d', strtotime($pre_date));
        $inputs['end_date'] = date('Y-m-d', strtotime($cur_date));
    	$attendance_record = new \App\Models\AttendanceRecord;
    	//获取考勤记录
    	$my_record_list = $attendance_record->where('user_id', $user_info->id)->whereBetween('punch_time', [$pre_date, $cur_date])->get();
    	//日期对应的打卡记录
    	$tmp_record = array();
    	foreach ($my_record_list as $key => $value) {
    		$tmp_record[date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
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
    	$attendance_list = $attendance->whereBetween('date', [$inputs['start_date'], $inputs['end_date']])->get();

        //请假、出勤申请--用于抵消迟到、未签到、早退、未签退
        $apply_att = new \App\Models\ApplyAttendance;
        $apply_list = $apply_att->where('status', 1)->where('user_id', $user_info->id)->whereIn('type', [1,3])->get();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            $days = prDates(date('Y-m-d', strtotime($value->start_time)),date('Y-m-d', strtotime($value->end_time)));
            foreach ($days as $d) {
                $apply_data[$d][] = $value;
            }
        }

        $date_work = array();
        foreach ($attendance_list as $key => $value) {
            $date_work[$value->date] = $value->type;
        }

    	$data = array();
        $baobei_list = array();
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
            $baobei_arr = array();
    		if(isset($setting_info['am_start_time']) && !empty($setting_info['am_start_time']) && isset($setting_info['pm_end_time']) && !empty($setting_info['pm_end_time'])){
    			$a_time = $setting_info['am_start_time'];
                $p_time = $setting_info['pm_end_time'];
                $am_start_time = strtotime($value['date'].' '.$a_time.':00');//当天上班时间
                $am_end_time = strtotime($value['date'].' '.$setting_info['am_end_time'].':00');//当天上午下班时间
                $pm_start_time = strtotime($value['date'].' '.$setting_info['pm_start_time'].':00');//当天下午上班时间
                $pm_end_time = strtotime($value['date'].' '.$p_time.':00');//当天下班时间
                $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
                $am_start_after_time = $am_start_time + $setting_info['am_start_after_time'] * 60;//上班后多少分钟
                $pm_end_before_time = $pm_end_time - $setting_info['pm_end_before_time'] * 60;//下班前多少分钟
                $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟
                $zhongwu_time = strtotime($value['date'].' 13:00:00');//用中午时间划分签到签退
	    		if(isset($date_record_list[$value['date']]) && $value['type'] == 0){
	    			//工作日
	    			//首先判断当天是否正常上班和正常下班  
                    $start_zhengchang = false;
                    $end_zhengchang = false;
                    $first_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['first']);//第一次打卡时间
                    if($first_time >= $am_start_before_time && $first_time <= ($am_start_time + 59)){
                        //打卡时间在上班前有效时间内 属于正常打卡
                        $start_zhengchang = true;//正常上班
                    }
                    $last_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['last']);//第一次打卡时间
                    if($last_time >= $pm_end_time && $last_time <= ($pm_end_after_time)){
                        //打卡时间在下班前有效时间内 属于正常打卡
                        $end_zhengchang = true;//正常下班
                    }
                    $first = date('H:i:s', $first_time);
                    $last = date('H:i:s', $last_time);
                    if($first_time == $last_time){
                        //只有一次打卡记录
                        if($am_end_time >= $first_time){
                            $last = '未打卡';
                        }
                        if($am_end_time < $first_time){
                            $first = '未打卡';
                        }
                    }
                    $work_time = $first.'--'.$last;
                    $if_queqin = false;//是否异常缺勤
                    if(!$start_zhengchang && $end_zhengchang){
                        //未正常上班  但是正常下班
                        if($first_time >= $pm_end_before_time && $first_time <= ($pm_end_after_time+59) ){
                            //第一次打卡时间在 16:30:00-00:00:00
                            $if_queqin = true;//视为异常缺勤
                        }
                        if($if_queqin){
                            $baobei_arr['date'] = $value['date'];
                            $baobei_arr['first_time'] = $first;
                            $baobei_arr['last_time'] = $last;
                            $baobei_arr['type'] = 1;
                        }
                    }else if($start_zhengchang && !$end_zhengchang){
                        if($last_time >= $am_start_before_time && $last_time <= ($am_start_after_time+59) ){
                            //最后一次打卡时间在 07:30:00-10:30:00
                            $if_queqin = true;//视为异常缺勤
                        }
                        if($if_queqin){
                            $baobei_arr['date'] = $value['date'];
                            $baobei_arr['first_time'] = $first;
                            $baobei_arr['last_time'] = $last;
                            $baobei_arr['type'] = 2;
                        }
                    }else if(!$start_zhengchang && !$end_zhengchang){
                        if($first_time > $am_start_after_time && $first_time < $pm_end_before_time && $last_time > $am_start_after_time && $last_time < $pm_end_before_time){
                            //所有打卡在 10:30:01-15:59:59
                            $if_queqin = true;//异常缺勤
                            $type = 3;//上班、下班都没打卡
                        }
                        //查看是否有请假、外勤
                        if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                            if($am_start_time >= strtotime($app->start_time) && $am_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除迟到、未签到记录、迟到时间
                                $if_queqin = false;
                            }
                        }}
                        if($first_time > $pm_end_before_time && $first_time < $pm_end_after_time){
                            //第一次打卡在16:30-00:00  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                            $type = 1;//上班没打卡
                        }
                        if($last_time > $am_start_before_time && $last_time < $am_start_after_time){
                            //最后一次打卡在07:30-10:30  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                            $type = 2;//下班没打卡
                        }
                        if($if_queqin){
                            $baobei_arr['date'] = $value['date'];
                            $baobei_arr['first_time'] = $first;
                            $baobei_arr['last_time'] = $last;
                            $baobei_arr['type'] = $type;
                        }
                    }
	    		}else if(!isset($date_record_list[$value['date']]) && $value['type'] == 0){
                    //判断是否请假、外勤
                    $if_queqin = true; 
                    if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                        if($am_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                            $if_queqin = false;
                        }
                    }}
                    if($if_queqin){
                        $baobei_arr['date'] = $value['date'];
                        $baobei_arr['first_time'] = '未打卡';
                        $baobei_arr['last_time'] = '未打卡';
                        $baobei_arr['type'] = 3;//上下班都没打卡
                    }
                }
    		}
            if(!empty($baobei_arr)){
                $baobei_list[] = $baobei_arr;
            }
    	}
        $data['baobei_list'] = $baobei_list;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }
    
    /**
    * 假期-详情
    * @author molin
    * @date 2018-11-20
    */
    public function show(){
        $inputs = request()->all();
        if(isset($inputs['year']) && !empty($inputs['year'])){
            $inputs['year'] = date('Y');
            $inputs['start_time'] = date('Y-01-01');
            $inputs['end_time'] = date('Y-12-31');
        }else{
            $inputs['month'] = date('m');
            $inputs['start_time'] = $cur_month_day = date('Y-m-01');
            $inputs['end_time'] = date('Y-m-d', strtotime("$cur_month_day +1 month -1 day"));
        }
        $inputs['user_id'] = auth()->user()->id;
        $inputs['type'] = 1;
        $holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->where('status', 1)->select(['id', 'name', 'if_nianjia'])->get();
        $type_data = array();
        $nianjia_id = 0;
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['type_id'] = $value->id;
            $type_data[$value->id]['name'] = $value->name;
            $type_data[$value->id]['if_nianjia'] = $value->if_nianjia;
            if($value->if_nianjia == 1){
                $nianjia_id = $value->id;
            }
        }
        $detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $detail->getDetailList($inputs);
        $apply_ids = array();
        $att_sum = array();//统计总数
        foreach ($detail_list as $key => $value) {
            $apply_ids[$value->apply_id] = $value->apply_id;
            $att_sum[$value->leave_type]['time_str'] = $att_sum[$value->leave_type]['time_str'] ?? 0;
            $att_sum[$value->leave_type]['time_str'] += $value->time_str;
            $att_sum[$value->leave_type]['apply_list'][$value->apply_id] = $value->apply_id;
        }
        $data = array();
        $holiday_year = new \App\Models\HolidayYear;
        $my_year_holiday = $holiday_year->getYearHoliday($inputs['user_id'],$inputs['start_time'],$inputs['end_time']);//我应有年假
        if($nianjia_id > 0){
            $my_year_qingjia = $detail->where('type', 1)->where('leave_type', $nianjia_id)->whereBetween('date', [date('Y-01-01',time()), $inputs['end_time']])->where('user_id', $inputs['user_id'])->sum('time_str');//今年已使用年假
        }else{
            $my_year_qingjia = 0;
        }
        $has_nianjia = array();
        foreach ($type_data as $key => $value) {
            $data[$key]['name'] = $value['name'];
            $data[$key]['time_str'] = $att_sum[$value['type_id']]['time_str'] ?? 0;
            $data[$key]['apply_list'] = $att_sum[$value['type_id']]['apply_list'] ?? array();
            if($value['if_nianjia'] == 1){
                $has_nianjia['name'] = '剩余年假';
                $has_nianjia['time_str'] = $my_year_holiday - $my_year_qingjia;
                $has_nianjia['apply_list'] = array();
            }
        }
        $data[] = $has_nianjia;
        //查出关联的申请单
        $apply = new \App\Models\ApplyAttendance;
        $apply_list = $apply->whereIn('id', $apply_ids)->select(['id','start_time','end_time','remarks'])->get()->toArray();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            foreach ($data as $k => $v) {
                if(!empty($v['apply_list']) && in_array($value['id'], $v['apply_list'])){
                    $data[$k]['apply_list'][$value['id']] = $value;
                }
            }
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

    /**
    * 首页-打卡记录
    * @author molin
    * @date 2019-01-25
    */
    public function daka(){
        
        $end_time = date('Y-m-d H:i:s');//当前时间
        $start_time = date('Y-m-d 00:00:00', strtotime("$end_time -1 day"));//昨天0点
        $user_info = auth()->user();
        $attendance_record = new \App\Models\AttendanceRecord;
        //获取今天和昨天的考勤记录
        $my_record_list = $attendance_record->where('user_id', $user_info->id)->whereBetween('punch_time', [$start_time, $end_time])->get();
        // dd($my_record_list);
        //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();//根据签到时间

        //日期对应的打卡记录
        $tmp_record = array();
        foreach ($my_record_list as $key => $value) {
            $tmp_record[date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
        }

        //日期对应的打卡记录
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
            $zhongwu = strtotime($key.' 13:00:00');
            $first = date('H:i:s', min($tmp_arr));//第一次打卡时间
            $last = date('H:i:s', max($tmp_arr));//最后一次打卡时间
            if($first == $last){
                //只有一次打卡记录的时候
                if(min($tmp_arr) >= $zhongwu){
                    $first = '未打卡';
                }else{
                    $last = '未打卡';
                }
            }
            $date_record_list[$key]['first'] = $first;
            $date_record_list[$key]['last'] = $last;

        }
        //节假日、工作日列表
        $attendance =  new \App\Models\Attendance;
        $yesterday_att = $attendance->where('date', date('Y-m-d', strtotime($start_time)))->first();//昨天是否为工作日
        $data = array();
        //今日
        $today_first = $date_record_list[date('Y-m-d', strtotime($end_time))]['first'] ?? '未打卡';
        $today_last = $date_record_list[date('Y-m-d', strtotime($end_time))]['last'] ?? '未打卡';
        $yesterday_first = $date_record_list[date('Y-m-d', strtotime($start_time))]['first'] ?? '未打卡';
        $yesterday_last = $date_record_list[date('Y-m-d', strtotime($start_time))]['last'] ?? '未打卡';
        $data['today']['time'] = $today_first.'——'.$today_last;
        $data['today']['if_report'] = 0;//今天的不能报备
        $data['yesterday']['time'] = $yesterday_first.'——'.$yesterday_last;
         //免签人员
        $free = new \App\Models\AttendanceFree;
        $free_list = $free->get();
        $free_data = array();
        foreach ($free_list as $key => $value) {
            $free_data[] = $value->user_id;//免签
        }
        if($yesterday_att->type == 0 && !in_array($user_info->id, $free_data)){
            //工作日 除免签人员
            $data['yesterday']['if_report'] = $yesterday_first == '未打卡' || $yesterday_last == '未打卡' ? 1 : 0;
        }else{
            //非工作日  不显示报备
            $data['yesterday']['if_report'] = 0;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        
    }

}

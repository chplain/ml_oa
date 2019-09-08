<?php

namespace App\Http\Controllers\Api;

use App\Models\Holiday;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class AttendanceStatController extends Controller
{
    //出勤统计
    public function index($if_return = false, $inputs = []){
    	$inputs = !empty($inputs) ? $inputs : request()->all();
    	$start_time = date('Y-01-01');
    	$end_time = date('Y-m-d', time()-86400);//前一天为止
    	$start_time_cr = date('Y-01-01 00:00:00');
    	$end_time_cr = date('Y-m-d 23:59:59', time()-86400);//前一天为止
    	//根据月份来统计
    	if(isset($inputs['month']) && !empty($inputs['month'])){
    		$inputs['start_time'] = $inputs['month'].'-01';
    		$start_time = $inputs['start_time'];
    		$end_time = $inputs['end_time'] = date('Y-m-d', strtotime("$start_time +1 month -1 day"));//当月最后一天
    		$start_time_cr = $start_time.' 00:00:00';
    		$end_time_cr = $end_time.' 23:59:59';
    	}
    	if(isset($inputs['cur_month']) && $inputs['cur_month'] == 1){
    		//获取本月书数据
    		$start_time = date('Y-m-01');
    		$end_time = date('Y-m-d', strtotime("$start_time +1 month -1 day"));//当月最后一天
    		$start_time_cr = date('Y-m-01 00:00:00');
    		$end_time_cr = date('Y-m-d 23:59:59', time()-86400);//前一天为止
    	}else if(isset($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['start_time']) && !empty($inputs['end_time'])){
    		$start_time = $inputs['start_time'];
    		$end_time = $inputs['end_time'];
    		$start_time_cr = $inputs['start_time'].' 00:00:00';
    		$end_time_cr = $inputs['end_time'].' 23:59:59';
    	}
    	$user = new \App\Models\User;
    	$data = $user->getDataList($inputs);
    	$items = $user_ids = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['realname'] = $value->realname;
    		$items['dept'] = $value->dept->name;
    		$user_ids[] = $value->id;
    		$data['datalist'][$key]=  $items;
    	}
    	$attendance_record = new \App\Models\AttendanceRecord;
    	//获取考勤记录
    	$my_record_list = $attendance_record->whereIn('user_id', $user_ids)->whereBetween('punch_time',[$start_time_cr, $end_time_cr])->get();

    	 //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();

    	//日期对应的打卡记录
        $tmp_record = array();
        foreach ($my_record_list as $key => $value) {
            $tmp_record[$value['user_id']][date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
        }
    	//每个人员对应日期对应的打卡记录
    	$date_record_list = array();
    	foreach ($tmp_record as $uid => $value) {
    		foreach ($value as $d => $val) {
    			//获取上班时间配置
	            $setting_info = array();
	            foreach ($setting_list as $sett) {
	                //取打卡日期大于配置修改的日期
	                if(strtotime($d) > strtotime($sett['punch_time'])){
	                    $setting_info = $sett;
	                }
	            }
	            if(empty($setting_info)){
	                $setting_info = $setting_list[0];
	            }
	            $am_start_time = strtotime($d.' '.$setting_info['am_start_time'].':00');//当天上班时间
	            $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
	            $pm_end_time = strtotime($d.' '.$setting_info['pm_end_time'].':00');//当天下班时间
	            $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟
	            $tmp_arr = array();
	            foreach ($val as $k => $v) {
	                if(strtotime($v['punch_time']) >= $am_start_before_time && strtotime($v['punch_time']) <= $pm_end_after_time){
	                    $tmp_arr[] = strtotime($v['punch_time']); //一天内上班时间内的打卡记录
	                }
	            }
	            $first = min($tmp_arr);//第一次打卡时间
	            $last = max($tmp_arr);//最后一次打卡时间
	            $date_record_list[$uid][$d]['first'] = $first;
	            $date_record_list[$uid][$d]['last'] = $last;
    		}
            
    	}
    	// dd($date_record_list);
    	//假期类型
    	$holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
        }

        //请假、外勤申请--用于抵消迟到、未签到、早退、未签退
        $apply_att = new \App\Models\ApplyAttendance;
        $apply_list = $apply_att->where('status', 1)->whereIn('user_id', $user_ids)->whereIn('type', [1,3])->get();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            $days = prDates(date('Y-m-d', strtotime($value->start_time)), date('Y-m-d', strtotime($value->end_time)));
            foreach ($days as $d) {
                $apply_data[$value->user_id][$d][] = $value;
            }
        }
        //请假、外勤、加班详细信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->getDetailList(['user_ids'=>$user_ids, 'start_time'=>$start_time, 'end_time'=>$end_time]);
        $qingjia_list = $jiaban_list = $waiqin_list = array();
        $a = $b = $c = array();
        foreach ($detail_list as $key => $value) {
            if($value->type == 1){
                //请假
                $qingjia_list[$value->user_id][$value->date][$value->leave_type] = floatval($value->time_str);
                $a[$value->user_id][$value->date][] = date('Y-m-d H:i:s', strtotime($value->hasApply->start_time)).'~'.date('Y-m-d H:i:s', strtotime($value->hasApply->end_time));
            }
            if($value->type == 2){
                //加班
                $jiaban_list[$value->user_id][$value->date][] = floatval($value->time_str);
                if(!empty($value->hasApply->time_data)){
                    $time_data = unserialize($value->hasApply->time_data);
                    foreach ($time_data as $v) {
                        if($value->date == date('Y-m-d', strtotime($v['start_time']))){
                            $b[$value->user_id][$value->date][] = date('Y-m-d H:i:s', strtotime($v['start_time'])).'~'.date('Y-m-d H:i:s', strtotime($v['end_time']));
                        }
                    }
                }else{
	                $b[$value->user_id][$value->date][] = date('Y-m-d H:i:s', strtotime($value->hasApply->start_time)).'~'.date('Y-m-d H:i:s', strtotime($value->hasApply->end_time));
	            }
            }
            if($value->type == 3){
                //外勤
                $waiqin_list[$value->user_id][$value->date][] = floatval($value->time_str);
                $c[$value->user_id][$value->date][] = date('Y-m-d H:i:s', strtotime($value->hasApply->start_time)).'~'.date('Y-m-d H:i:s', strtotime($value->hasApply->end_time));
            }
        }
        $view_data = array();
        $view_data['if_qingjia'] = $a;
        $view_data['if_jiaban'] = $b;
        $view_data['if_waiqin'] = $c;

    	//节假日、工作日列表
    	$attendance =  new \App\Models\Attendance;
    	$attendance_list = $attendance->whereBetween('date',[$start_time, $end_time])->get();
    	//拼装我的考勤数组
    	
        //免签人员
        $free = new \App\Models\AttendanceFree;
        $free_list = $free->get();
        $free_data = array();
        foreach ($free_list as $key => $value) {
        	$free_data[] = $value->user_id;//免签
        }
        
        $items = $export_data = $need_att_user = array();
        //统计每个人的情况
        foreach ($data['datalist'] as $k => $user) {
        	//出勤工时 异常缺勤工时
        	$chuqin_total = $queqin_total = $yingchuqin_total = 0;
        	//迟到、早退、迟到40分钟后的次数
        	$chidao_num = $zaotui_num = $chidao_sum = $zaotui_sum = $ex_time_num = 0;
        	//加班、外勤统计
        	$gongzuori_jiaban_total = $zhoumo_jiaban_total = $jiejiari_jiaban_total = $waiqin_time_total = 0;
        	//假期统计
        	$jiaqi_total = $jiaqi_time = array();
        	//报备
        	$baobei_list = array();
    	
    		$need_att_user[$k]['id'] = $user['id'];
    		$need_att_user[$k]['realname'] = $user['realname'];
    		$need_att_user[$k]['dept'] = $user['dept'];
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
	            $zhongwu_time = strtotime($value['date'].' 13:00:00');//中午时间
	            $chuqin_time = $queqin_num = $yingchuqin_time =0;//缺勤
	            $cur_day_time = $jiaban_day_time = 0;
	            $chidao_time = $zaotui_time = 0;
	            $gongzuori_jiaban =  $zhoumo_jiaban = $jiejiari_jiaban = $waiqin_time =0;//加班、外勤

	            $queqin_list = array();
	    		if(isset($date_record_list[$user['id']][$value['date']]) && $value['type'] == 0){
	    			//工作日
	    			$first_time = $date_record_list[$user['id']][$value['date']]['first'];//第一次打卡时间
	                $last_time = $date_record_list[$user['id']][$value['date']]['last'];;//最后一次打卡时间
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
		                //迟到、早退、
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
		                //首先判断当天是否正常上班和正常下班  
		                $start_zhengchang = false;
		                $end_zhengchang = false;
		                if($first_time >= $am_start_before_time && $first_time <= ($am_start_time + 59)){
		                    //打卡时间在上班前有效时间内 属于正常打卡
		                    $start_zhengchang = true;//正常上班
		                }
		                if($last_time >= $pm_end_time && $last_time <= $pm_end_after_time){
		                    //打卡时间在下班前有效时间内 属于正常打卡
		                    $end_zhengchang = true;//正常下班
		                }
		                $if_chidao = false;//是否迟到
		                $if_zaotui = false;//是否早退
		                $if_queqin = false;//是否缺勤
		                if(!$start_zhengchang && $end_zhengchang){
		                    //未正常上班  但是正常下班
	                        // 迟到
	                        if($first_time < $am_start_after_time && $first_time > ($am_start_time+59)){
	                            //第一个打卡记录大于上班时间 小于有效时间  迟到
	                            $if_chidao = true; 
	                            //计算迟到时间  第一次打卡时间 - 上班时间 = 迟到时间
	                            $chidao_time = floor(($first_time - $am_start_time) / 60);
	                        }
	                        //是否异常缺勤
	                        if($first_time >= $am_start_after_time && $first_time < $pm_end_before_time){
	                            //第一次打卡在10:30-16:30
	                            $if_queqin = true;//判断缺勤
	                            $queqin_list['date'] = $value['date'];
	                            $queqin_list['first_time'] = $first;
	                            $queqin_list['last_time'] = $last;
	                        }
		                    //查看是否有请假、外勤
		                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
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
	                            $queqin_list['date'] = $value['date'];
	                            $queqin_list['first_time'] = $first;
	                            $queqin_list['last_time'] = $last;
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
	                            $queqin_list['date'] = $value['date'];
	                            $queqin_list['first_time'] = $first;
	                            $queqin_list['last_time'] = $last;
	                        }
		                    //查看是否有请假、外勤
		                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
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
	                            $queqin_list['date'] = $value['date'];
	                            $queqin_list['first_time'] = $first;
	                            $queqin_list['last_time'] = $last;
	                        }
		                }else if(!$start_zhengchang && !$end_zhengchang){
		                    //未正常上班  也未正常下班
	                        // 迟到
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
	                            $queqin_list['date'] = $value['date'];
	                            $queqin_list['first_time'] = $first;
	                            $queqin_list['last_time'] = $last;
	                        }
		                    //查看是否有请假、外勤
		                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
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
	                            $queqin_list['date'] = $value['date'];
	                            $queqin_list['first_time'] = $first;
	                            $queqin_list['last_time'] = $last;
	                        }
	                        if($last_time > $am_start_before_time && $last_time < $am_start_after_time){
	                            //最后一次打卡在07:30-10:30  有请假和外勤也不能抵消  只有报备才能抵消
	                            $if_queqin = true;
	                            $queqin_list['date'] = $value['date'];
	                            $queqin_list['first_time'] = $first;
	                            $queqin_list['last_time'] = $last;
	                        }
		                }
		                if($if_chidao && time() > $pm_end_after_time){
		                    $chidao_num ++;
		                }
		                if($if_zaotui && time() > $pm_end_after_time){
		                    $zaotui_num ++;
		                }
		                if($if_queqin && time() > $pm_end_after_time){
		                    $queqin_num ++;
		                    $baobei_list[] = $queqin_list;
		                }
		                $chidao_sum += $chidao_time;
		                $zaotui_sum += $zaotui_time;
		                if($chidao_sum > 40 && $chidao_time){
		                	$ex_time_num ++;//迟到40分钟后的次数
		                }
		    		}
	    		}
	            if(isset($jiaban_list[$user['id']][$value['date']])){
	                $jiaban_day_time = array_sum($jiaban_list[$user['id']][$value['date']]);
	            }
	            
	            $chuqin_time = $cur_day_time;//出勤时长
	            //计算加班时间
	            if($value['type'] == 0){
	                //工作日加班时间
	                if(isset($jiaban_list[$user['id']][$value['date']]) && !empty($jiaban_list[$user['id']][$value['date']])){
	                    $gongzuori_jiaban = array_sum($jiaban_list[$user['id']][$value['date']]);
	                }
	                //异常缺勤
	                if(!isset($date_record_list[$user['id']][$value['date']])){
	                    //工作日无打卡记录 记一天缺勤
	                    //判断是否请假、外勤
	                    $if_queqin = true; 
	                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
	                        if($am_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
	                            $if_queqin = false;
	                        }
	                    }}
	                    if($if_queqin && time() > $pm_end_after_time){
	                        $queqin_num++;
		                    $queqin_list = array();
                            $queqin_list['date'] = $value['date'];
                            $queqin_list['first_time'] = '未打卡';
	                        $queqin_list['last_time'] = '未打卡';
                            $baobei_list[] = $queqin_list;
	                    }
	                    
	                }
	                //应出勤工时
	                $yingchuqin_time = (($pm_end_time - $pm_start_time) + ($am_end_time - $am_start_time)) / 3600;
	            }else if($value['type'] == 1){
	                //周末加班时间
	                if(isset($jiaban_list[$user['id']][$value['date']]) && !empty($jiaban_list[$user['id']][$value['date']])){
	                    $zhoumo_jiaban = array_sum($jiaban_list[$user['id']][$value['date']]); 
	                }
	                
	            }else if($value['type'] == 2){
	                //节假日加班时间
	                if(isset($jiaban_list[$user['id']][$value['date']]) && !empty($jiaban_list[$user['id']][$value['date']])){
	                    $jiejiari_jiaban = array_sum($jiaban_list[$user['id']][$value['date']]);
	                }
	                
	            }
	            $chuqin_total += $chuqin_time;//出勤工时
	            $yingchuqin_total += $yingchuqin_time;//应出勤工时
	            $queqin_total += $queqin_num; //异常缺勤
	            $gongzuori_jiaban_total += $gongzuori_jiaban;
	            $zhoumo_jiaban_total += $zhoumo_jiaban;
	            $jiejiari_jiaban_total += $jiejiari_jiaban;

	            //计算外勤
	            if(isset($waiqin_list[$user['id']][$value['date']]) && !empty($waiqin_list[$user['id']][$value['date']])){
	                $waiqin_time = array_sum($waiqin_list[$user['id']][$value['date']]);
	            }
	            $waiqin_time_total += $waiqin_time;

	            //计算请假
	            if(isset($qingjia_list[$user['id']][$value['date']]) && is_array($qingjia_list[$user['id']][$value['date']])){
	                foreach ($qingjia_list[$user['id']][$value['date']] as $kk => $vv) {
	                    $jiaqi_time[$user['id']][$kk]['num'] = $jiaqi_time[$user['id']][$kk]['num'] ?? 0;
	                    $jiaqi_time[$user['id']][$kk]['num'] += $vv;
	                }
	            }
	            foreach ($type_data as $type_id => $v) {
	                if(isset($jiaqi_time[$user['id']][$type_id])){
	                    $jiaqi_total[$user['id']][$type_id]['num'] = $jiaqi_time[$user['id']][$type_id]['num'];
	                    $jiaqi_total[$user['id']][$type_id]['name'] = $v['name'];
	                }else{
	                    $jiaqi_total[$user['id']][$type_id]['num'] = 0;
	                    $jiaqi_total[$user['id']][$type_id]['name'] = $v['name'];
	                }
	            }
	    	}

        	$items['realname'] = $user['realname'];
        	$items['dept'] = $user['dept'];
        	$items['chidao_sum'] = in_array($user['id'], $free_data) ? 0 : $chidao_sum ;//迟到
        	$items['zaotui_sum'] = in_array($user['id'], $free_data) ? 0 : $zaotui_sum;//早退
        	$items['yingchuqin_total'] = in_array($user['id'], $free_data) ? 0 : $yingchuqin_total;//应出勤工时
        	$items['chuqin_total'] = in_array($user['id'], $free_data) ? 0 : $chuqin_total;//出勤工时
        	$items['queqin_total'] = in_array($user['id'], $free_data) ? 0 : $queqin_total;//缺勤工时
        	$items['baobei_list'] = in_array($user['id'], $free_data) ? [] : $baobei_list;//报备数据列表
        	$items['ex_time_num'] = in_array($user['id'], $free_data) ? 0 : $ex_time_num;//迟到超过40分钟后的次数
        	foreach ($type_data as $type_id => $val) {
        		$items['jiaqi_'.$type_id] = $jiaqi_total[$user['id']][$type_id]['num'] ?? 0;
        	}
        	$items['gongzuori_jiaban_total'] = $gongzuori_jiaban_total;
        	$items['zhoumo_jiaban_total'] = $zhoumo_jiaban_total;
        	$items['jiejiari_jiaban_total'] = $jiejiari_jiaban_total;
        	$items['waiqin_time_total'] = $waiqin_time_total;
        	$fields = array_keys($items);
        	$data['datalist'][$k] = $items;
        	unset($items['baobei_list']);
        	$export_data[] = $items;
        	
        }
        if(!isset($fields)){
        	$fields = array();
        	$fields['realname'] = '';
        	$fields['dept'] = '';
        	$fields['chidao_sum'] = 0;//迟到
        	$fields['zaotui_sum'] = 0;//早退
        	$fields['yingchuqin_total'] = 0;//应出勤工时
        	$fields['chuqin_total'] = 0;//出勤工时
        	$fields['queqin_total'] = 0;//缺勤工时
        	$fields['baobei_list'] = [];//报备数据列表
        	$fields['ex_time_num'] = 0;//迟到超过40分钟后的次数
        	foreach ($type_data as $type_id => $val) {
        		$fields['jiaqi_'.$type_id] = 0;
        	}
        	$fields['gongzuori_jiaban_total'] = 0;
        	$fields['zhoumo_jiaban_total'] = 0;
        	$fields['jiejiari_jiaban_total'] = 0;
        	$fields['waiqin_time_total'] = 0;
        	$fields = array_keys($fields);
        }
        $table_head = $this->table_head($fields,$type_data);
        $data['table_head'] = $table_head;
        if(isset($inputs['export']) && $inputs['export'] == 1){
        	//导出
        	$export_head = array();
        	foreach ($table_head as $key => $value) {
        		if(!isset($value['children'])){
        			$export_head[$key] = $value['label'];
        		}
        		if(isset($value['children']) && is_array($value['children']) && !empty($value['children'])){
        			foreach ($value['children'] as $k => $v) {
        				$export_head[$k] = $v['label'];
        			}
        		}
        	}
        	$days = prDates($start_time, $end_time);
        	$sheet2_head = ['realname'=>'姓名','dept'=>'部门'];
        	foreach ($days as $d) {
        		$sheet2_head[$d] = $d;
        	}
        	$sheet2_body = array();
        	// dd($need_att_user);
        	foreach ($need_att_user as $key => $value) {
        		$sheet2_body[$key]['realname'] = $value['realname'];
        		$sheet2_body[$key]['dept'] = $value['dept'];
        		foreach ($days as $d) {
        			if(isset($date_record_list[$value['id']][$d])){
        				$first = date('H:i:s',$date_record_list[$value['id']][$d]['first']);
        				$last = date('H:i:s',$date_record_list[$value['id']][$d]['last']);
        				if($first == $last){
        					//只有一次打卡记录
        					$zhongwu_time = strtotime($d.' 13:00:00');
        					if($first >= $zhongwu_time){
        						$first = '未打卡';
        					}
        					if($first < $zhongwu_time){
        						$last = '未打卡';
        					}
        				}
        				$sheet2_body[$key][$d] = $first.'——'.$last;
        			}else{
        				$sheet2_body[$key][$d] = '--';
        			}
        			if(isset($view_data['if_qingjia'][$value['id']][$d])){
        				$sheet2_body[$key][$d] .= "\r\n请假：".implode(';', $view_data['if_qingjia'][$value['id']][$d]);
        			}
        			if(isset($view_data['if_jiaban'][$value['id']][$d])){
        				$sheet2_body[$key][$d] .= "\r\n加班：".implode(';', $view_data['if_jiaban'][$value['id']][$d]);
        			}
        			if(isset($view_data['if_waiqin'][$value['id']][$d])){
        				$sheet2_body[$key][$d] .= "\r\n外勤：".implode(';', $view_data['if_waiqin'][$value['id']][$d]);
        			}
        			
        		}
        	}
        	$filedata = pExprot($export_head, $export_data, 'attendance_list', 'Sheet1', 'Sheet2', $sheet2_head, $sheet2_body);
        	$filepath = 'storage/exports/' . $filedata['file'];//下载链接
        	$fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
        	return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        if(isset($if_return) && $if_return){
        	return $data;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    //考勤时间详情
    public function detail()
    {
        $inputs = \request()->all();
        if(isset($inputs['cur_month']) && $inputs['cur_month'] == 1){
            //获取本月书数据
            $start_time = date('Y-m-01');
            $end_time = date('Y-m-d', strtotime("$start_time +1 month -1 day"));//当月最后一天
            $start_time_cr = date('Y-m-01 00:00:00');
            $end_time_cr = date('Y-m-d 23:59:59', time()-86400);//前一天为止
        }else if(isset($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['start_time']) && !empty($inputs['end_time'])){
            $start_time = $inputs['start_time'];
            $end_time = $inputs['end_time'];
            $start_time_cr = $inputs['start_time'].' 00:00:00';
            $end_time_cr = $inputs['end_time'].' 23:59:59';
        }else{
            return response()->json(['code' => 0,'message' => '请选择当月日期或自定义日期未填写']);
        }
        $user = new \App\Models\User;
        $data = $user->getDataList($inputs);//获取用户信息
        //免签人员
        $free = new \App\Models\AttendanceFree;
        $free_list = $free->get();
        $free_data = array();
        foreach ($free_list as $key => $value) {
            $free_data[] = $value->user_id;//免签
        }

        $items = $user_ids = array();
        foreach ($data['datalist'] as $key => $value) {
            if(in_array($value['id'],$free_data)){
                unset($data['datalist'][$key]);//删除免签人员
            }else{
                $items['id'] = $value->id;
                $items['realname'] = $value->realname;
                $items['dept'] = $value->dept->name;
                $user_ids[] = $value->id;
                $data['datalist'][$key]=  $items;
            }
        }

        $attendance_record = new \App\Models\AttendanceRecord;
        //获取考勤记录
        $my_record_list = $attendance_record->whereIn('user_id', $user_ids)->whereBetween('punch_time',[$start_time_cr, $end_time_cr])->get()->toArray();

        //日期对应的打卡记录
        $tmp_record = array();
        foreach ($my_record_list as $key => $value) {
            $tmp_record[$value['user_id']][date('Y-m-d', strtotime($value['punch_time']))][] = $value['punch_time'];//打卡时间
        }
        //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'desc')->get()->toArray();

        //日期对应第一次打卡 和最后一次打卡记录
        $date_record_list = array();
        foreach ($tmp_record as $id => $value) {
            foreach($value as $date => $time){
                //获取上班时间配置
                $setting_info = array();
                foreach ($setting_list as $sett) {
                    //取打卡日期大于配置修改的日期
                    if(strtotime($date) > strtotime($sett['created_at'])){
                        $setting_info = $sett;
                    }
                }
                if(empty($setting_info)){
                    $setting_info = $setting_list[0];
                }
                $am_start_time = strtotime($date.' '.$setting_info['am_start_time'].':00');//当天上班时间
                $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
                $pm_end_time = strtotime($date.' '.$setting_info['pm_end_time'].':00');//当天下班时间
                $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟
                $tmp_arr = array();
                foreach ($time as $k => $v) {
                    if(strtotime($v) >= $am_start_before_time && strtotime($v) <= $pm_end_after_time){
                        $tmp_arr[] = strtotime($v); //一天内上班时间内的打卡记录
                    }
                }
                $first = date('H:i:s', min($tmp_arr));//第一次打卡时间
                $last = date('H:i:s', max($tmp_arr));//最后一次打卡时间
                $date_record_list[$id][$date]['first'] = $first;
                $date_record_list[$id][$date]['last'] = $last;
            }
        }
        //请假、加班、外勤申请--用于抵消迟到、未签到、早退、未签退
        $apply_att_detail = new \App\Models\ApplyAttendanceDetail();
        $apply_list = $apply_att_detail->whereIn('user_id', $user_ids)->get()->toArray();
        $apply_data = array();
        $punch_data = array();
        $every_days = $this->getDate($start_time,$end_time);
        foreach ($every_days as $d) {
            $day = $d['date'];
            foreach ($apply_list as $key => $value) {
                if($day == $value['date']){
                    $apply_data[$value['user_id']][$day] = $value['type'];//获取考勤异常情况类型
                }
                $apply_data[$value['user_id']][$day] = isset($apply_data[$value['user_id']][$day]) ? $apply_data[$value['user_id']][$day] : '正常';//对应用户的请假、加班、外勤情况
            }
            foreach($date_record_list as $key => $value){
                if(isset($value[$day]) && count($value[$day]) >= 2){//有写情况有一天有3次打卡，可以判断是否为重复打卡
                    $punch_data[$key][$day] = date('H:i',strtotime($value[$day]['first'])).'--'.date('H:i',strtotime($value[$day]['last']));
                }elseif (isset($value[$day]) && count($value[$day]) == 1){
                    $punch_data[$key][$day] = date('H:i',strtotime($value[$day][0]));
                }
                else{
                    $punch_data[$key][$day] = '未打卡';
                }
            }
        }
        foreach($data['datalist'] as $key => $val){
            $attendance_datas['content'][$val['id']]['realname'] = $val['realname'];
            $attendance_datas['content'][$val['id']]['dept'] = $val['dept'];
            foreach($apply_data as $key => $value){
                if($val['id'] == $key){
                    $attendance_datas['content'][$val['id']]['everyday']['type'] = $value;
                }
            }
            foreach($punch_data as $key => $value){
                if($val['id'] == $key){
                    $attendance_datas['content'][$val['id']]['everyday']['time'] = $value;
                }
            }
        }
        //表头数据
        $title_dates = '';
        foreach($every_days as $val){
            $title_dates .= ','.$val['date'].'['.$val['week'].']';
            foreach($data['datalist'] as $msg){
                $attendance_datas['content'][$msg['id']]['everyday']['time'] = isset($attendance_datas['content'][$msg['id']]['everyday']['time']) ? $attendance_datas['content'][$msg['id']]['everyday']['time'] : '未打卡';
                $attendance_datas['content'][$msg['id']]['everyday']['type'] = isset($attendance_datas['content'][$msg['id']]['everyday']['type']) ? $attendance_datas['content'][$msg['id']]['everyday']['type'] : '正常';
            }
        }
        $attendance_datas['title'] = explode(',','部门,姓名'.$title_dates);
        $attendance_datas['count'] = $data['records_filtered'];
        return response()->json(['code' => 1,'message' => '获取成功', 'data' => $attendance_datas]);

    }

    //表头
    public function table_head($fields,$type_data){
    	unset($fields['baobei_list']);
    	$table_head = ['realname'=>'姓名','dept'=>'部门','chidao_sum'=> '迟到时间','zaotui_sum'=>'早退时间','yingchuqin_total'=>'应出勤工时','chuqin_total'=>'出勤工时','queqin_total'=>'异常考勤','ex_time_num'=>'迟到累计40分钟后的次数'];
    	$tmp = $jiaqi_th = [];
    	foreach ($fields as $value) {
    		if(substr($value, 0, 6) == 'jiaqi_'){
    			$tmp[$value] = $type_data[substr($value,6)]['name'];
    			$jiaqi_th[$value]['label'] = $type_data[substr($value,6)]['name'];
    		}
    	}
    	$headers = array_merge($table_head, $tmp, ['gongzuori_jiaban_total'=>'工作日加班','zhoumo_jiaban_total'=>'周末加班','jiejiari_jiaban_total'=>'节假日加班'], ['waiqin_total'=>'外勤情况(小时)']);
        $theads_format = [];
        $theads_format['realname'] = ['label' => $headers['realname']];
        $theads_format['dept'] = ['label' => $headers['dept']];
        $theads_format['chuqin'] = [
            'label' => '出勤情况（小时）',
            'children' => [
                'chidao_sum' => ['label' => $headers['chidao_sum']],
                'zaotui_sum' => ['label' => $headers['zaotui_sum']],
                'yingchuqin_total' => ['label' => $headers['yingchuqin_total']],
                'chuqin_total' => ['label' => $headers['chuqin_total']],
                'queqin_total' => ['label' => $headers['queqin_total']],
                'ex_time_num' => ['label' => $headers['ex_time_num']]
             ]
        ];
        $theads_format['qingjia'] = [
            'label' => '请假情况（天）',
            'children' => $jiaqi_th
        ];
        $theads_format['jiaban'] = [
            'label' => '加班情况（小时）',
            'children' => [
                'gongzuori_jiaban_total' => ['label' => $headers['gongzuori_jiaban_total']],
                'zhoumo_jiaban_total' => ['label' => $headers['zhoumo_jiaban_total']],
                'jiejiari_jiaban_total' => ['label' => $headers['jiejiari_jiaban_total']],
            ]
        ];
        $theads_format['waiqin_total'] = ['label' => $headers['waiqin_total']];
    	return $theads_format;
    }

    //按月生成统计报表
    public function create($create_month = '', $uids = []){
    	if(empty($create_month)) return ['code' => 0, 'message' => '请传入月份,如:2019-02'];
    	$salary = new \App\Models\Salary;//已经导入最终结果就不能再生成统计
    	$if_exist = $salary->where('wage_month', $create_month)->first();
    	if(!empty($if_exist)){
    		return ['code' => 0, 'message' => $create_month.'月份已经导入最终工资单就不能再生成统计'];
    	}
    	$start_time = $create_month.'-01';//第一天
    	$end_time = date('Y-m-d', strtotime("$start_time +1 month -1 day"));//最后一天
		$start_time_cr = $start_time.' 00:00:00';
		$end_time_cr = $end_time.' 23:59:59';
    	$year = date('Y', strtotime($start_time));
    	$month = date('m', strtotime($start_time));
    	$user = new \App\Models\User;
    	if(empty($uids)){
    		$data = $user->getDataList(['export'=>1]);
    	}else{
    		$data = $user->getDataList(['export'=>1, 'user_ids' => $uids]);
    	}
    	$items = $user_ids = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['realname'] = $value->realname;
    		$items['dept'] = $value->dept->name;
    		$user_ids[] = $value->id;
    		$data['datalist'][$key]=  $items;
    	}
    	// dd($data);
    	$attendance_record = new \App\Models\AttendanceRecord;
    	//获取考勤记录
    	$my_record_list = $attendance_record->whereIn('user_id', $user_ids)->whereBetween('punch_time',[$start_time_cr, $end_time_cr])->get();

    	//获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();

        //日期对应的打卡记录
        $tmp_record = array();
        foreach ($my_record_list as $key => $value) {
            $tmp_record[$value['user_id']][date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
        }

    	//每个人员对应日期对应的打卡记录
    	$date_record_list = array();
    	foreach ($tmp_record as $uid => $value) {
    		foreach ($value as $d => $val) {
    			//获取上班时间配置
	            $setting_info = array();
	            foreach ($setting_list as $sett) {
	                //取打卡日期大于配置修改的日期
	                if(strtotime($d) > strtotime($sett['punch_time'])){
	                    $setting_info = $sett;
	                }
	            }
	            if(empty($setting_info)){
	                $setting_info = $setting_list[0];
	            }
	            $am_start_time = strtotime($d.' '.$setting_info['am_start_time'].':00');//当天上班时间
	            $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
	            $pm_end_time = strtotime($d.' '.$setting_info['pm_end_time'].':00');//当天下班时间
	            $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟
	            $tmp_arr = array();
	            foreach ($val as $k => $v) {
	                if(strtotime($v['punch_time']) >= $am_start_before_time && strtotime($v['punch_time']) <= $pm_end_after_time){
	                    $tmp_arr[] = strtotime($v['punch_time']); //一天内上班时间内的打卡记录
	                }
	            }
	            $first = min($tmp_arr);//第一次打卡时间
	            $last = max($tmp_arr);//最后一次打卡时间
	            $date_record_list[$uid][$d]['first'] = $first;
	            $date_record_list[$uid][$d]['last'] = $last;
    		}
            
    	}
    	// dd($date_record_list);
    	//假期类型
    	$holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name', 'if_cancel_full_att','if_cancel_salary','condition'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
            $type_data[$value->id]['if_cancel_full_att'] = $value->if_cancel_full_att;
            $type_data[$value->id]['if_cancel_salary'] = $value->if_cancel_salary;
            $type_data[$value->id]['condition'] = $value->condition;
        }

        //请假、外勤申请--用于抵消迟到、未签到、早退、未签退
        $apply_att = new \App\Models\ApplyAttendance;
        $apply_list = $apply_att->where('status', 1)->whereIn('user_id', $user_ids)->whereIn('type', [1,3])->get();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            $days = prDates(date('Y-m-d', strtotime($value->start_time)), date('Y-m-d', strtotime($value->end_time)));
            foreach ($days as $d) {
                $apply_data[$value->user_id][$d][] = $value;
            }
        }
        //请假、外勤、加班详细信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->whereBetween('date',[$start_time, $end_time])->whereIn('user_id', $user_ids)->get();
        $qingjia_list = $jiaban_list = $waiqin_list = array();
        foreach ($detail_list as $key => $value) {
            if($value->type == 1){
                //请假
                $qingjia_list[$value->user_id][$value->date][$value->leave_type] = floatval($value->time_str);
            }
            if($value->type == 2){
                //加班
                $jiaban_list[$value->user_id][$value->date][] = floatval($value->time_str);
            }
            if($value->type == 3){
                //外勤
                $waiqin_list[$value->user_id][$value->date][] = floatval($value->time_str);
            }
        }

        //统计每个人年请假信息
        $sdate = date('Y-01-01', strtotime($start_time));
        $year_detail_list = $apply_detail->select(DB::raw('SUM(time_str) as time_str, user_id,leave_type'))->whereBetween('date',[$sdate, $end_time])->whereIn('user_id', $user_ids)->where('type',1)->groupBy(['user_id','leave_type'])->get();
        $year_jiaqi_total = array();
        foreach ($year_detail_list as $key => $value) {
        	$year_jiaqi_total[$value->user_id][$value->leave_type] = $value->time_str;
        }
        // dd($year_jiaqi_total);

    	//节假日、工作日列表
    	$attendance =  new \App\Models\Attendance;
    	$attendance_list = $attendance->whereBetween('date',[$start_time, $end_time])->get();
    	
        //免签人员
        $free = new \App\Models\AttendanceFree;
        $free_list = $free->get();
        $free_data = array();
        foreach ($free_list as $key => $value) {
        	$free_data[] = $value->user_id;//免签
        }
        
        //拼装数组
        $items = array();
        //统计每个人的情况
        foreach ($data['datalist'] as $k => $user) {
        	//出勤工时 异常缺勤工时
        	$chuqin_total = $queqin_total = $yingchuqin_total = 0;
        	//迟到、早退、未签到、未签退 、超出40分钟后的次数
        	$chidao_num = $zaotui_num = $weiqiandao_num = $weiqiantui_num = $chidao_sum = $zaotui_sum = $ex_time_num = 0;
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

	            $chuqin_time = $queqin_num = $yingchuqin_time =0;//缺勤
	            $cur_day_time = $jiaban_day_time = 0;
	            $chidao_time = $zaotui_time = 0;
	            $gongzuori_jiaban =  $zhoumo_jiaban = $jiejiari_jiaban = $waiqin_time =0;//加班、外勤
	            
	    		if(isset($date_record_list[$user['id']][$value['date']]) && $value['type'] == 0){
	    			//工作日
		    		$first_time = $date_record_list[$user['id']][$value['date']]['first'];//第一次打卡时间
	                $last_time = $date_record_list[$user['id']][$value['date']]['last'];//最后一次打卡时间
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
		                
		                //迟到、早退、
		                //首先判断当天是否正常上班和正常下班  
		                $start_zhengchang = false;
		                $end_zhengchang = false;
		                if($first_time >= $am_start_before_time && $first_time <= ($am_start_time + 59)){
		                    //打卡时间在上班前有效时间内 属于正常打卡
		                    $start_zhengchang = true;//正常上班
		                }
		                if($last_time >= $pm_end_time && $last_time <= $pm_end_after_time){
		                    //打卡时间在下班前有效时间内 属于正常打卡
		                    $end_zhengchang = true;//正常下班
		                }
		                $if_chidao = false;//是否迟到
		                $if_zaotui = false;//是否早退
		                $if_queqin = false;//是否缺勤
		                if(!$start_zhengchang && $end_zhengchang){
		                    //未正常上班  但是正常下班
	                        //迟到
	                        if($first_time < $am_start_after_time && $first_time > ($am_start_time+59)){
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
		                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
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
		                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
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
	                        // 迟到
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
		                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
		                        if($am_start_time >= strtotime($app->start_time) && $am_end_time <= strtotime($app->end_time)){
		                            //如果有请假外勤记录 则清除迟到、未签到记录、迟到时间
		                            $if_chidao = false; 
		                            $chidao_time = 0;
		                            $if_queqin = false;
		                        }
		                        if($pm_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
		                            //如果有请假外勤记录 则清除早退、未签退记录、早退时间
		                            $if_zaotui = false; 
		                            $zaotui_time = 0;
		                            $if_queqin = false;
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
		                if($chidao_time > 0 && $chidao_sum > 40){
		                	$ex_time_num ++;//迟到超过40分钟后的迟到次数
		                }
		    		}
	    		}
	            if(isset($jiaban_list[$user['id']][$value['date']])){
	                $jiaban_day_time = array_sum($jiaban_list[$user['id']][$value['date']]);
	            }
	            
	            $chuqin_time = $cur_day_time;//出勤时长
	            //计算加班时间
	            if($value['type'] == 0){
	                //工作日加班时间
	                if(isset($jiaban_list[$user['id']][$value['date']]) && !empty($jiaban_list[$user['id']][$value['date']])){
	                    $gongzuori_jiaban = array_sum($jiaban_list[$user['id']][$value['date']]);
	                }
	                //异常缺勤
	                if(!isset($date_record_list[$user['id']][$value['date']])){
	                    //工作日无打卡记录   记一天缺勤
	                    $if_queqin = true; 
	                    if(isset($apply_data[$user['id']][$value['date']])){foreach ($apply_data[$user['id']][$value['date']] as $app) {
	                        if($am_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
	                            $if_queqin = false;
	                        }
	                    }}
	                    if($if_queqin){
	                    	$queqin_num++;
	                    }
	                }
	                //应出勤工时
	                $yingchuqin_time = (($pm_end_time - $pm_start_time) + ($am_end_time - $am_start_time)) / 3600;
	            }else if($value['type'] == 1){
	                //周末加班时间
	                if(isset($jiaban_list[$user['id']][$value['date']]) && !empty($jiaban_list[$user['id']][$value['date']])){
	                    $zhoumo_jiaban = array_sum($jiaban_list[$user['id']][$value['date']]); 
	                }
	                
	            }else if($value['type'] == 2){
	                //节假日加班时间
	                if(isset($jiaban_list[$user['id']][$value['date']]) && !empty($jiaban_list[$user['id']][$value['date']])){
	                    $jiejiari_jiaban = array_sum($jiaban_list[$user['id']][$value['date']]);
	                }
	                
	            }
	            $chuqin_total += $chuqin_time;//出勤工时
	            $yingchuqin_total += $yingchuqin_time;//应出勤工时
	            $queqin_total += $queqin_num; //异常缺勤
	            $gongzuori_jiaban_total += $gongzuori_jiaban;
	            $zhoumo_jiaban_total += $zhoumo_jiaban;
	            $jiejiari_jiaban_total += $jiejiari_jiaban;

	            //计算外勤
	            if(isset($waiqin_list[$user['id']][$value['date']]) && !empty($waiqin_list[$user['id']][$value['date']])){
	                $waiqin_time = array_sum($waiqin_list[$user['id']][$value['date']]);
	            }
	            $waiqin_time_total += $waiqin_time;
	            $if_cancel_full_att = true;
	            //计算请假(工作日)
	            if($value['type'] == 0 && isset($qingjia_list[$user['id']][$value['date']]) && is_array($qingjia_list[$user['id']][$value['date']])){
	                foreach ($qingjia_list[$user['id']][$value['date']] as $kk => $vv) {
	                    $jiaqi_time[$user['id']][$kk]['num'] = $jiaqi_time[$user['id']][$kk]['num'] ?? 0;
	                    $jiaqi_time[$user['id']][$kk]['num'] += $vv;
	                }
	            }
	            foreach ($type_data as $type_id => $v) {
	                if(isset($jiaqi_time[$user['id']][$type_id])){
	                    $jiaqi_total[$user['id']][$type_id]['num'] = $jiaqi_time[$user['id']][$type_id]['num'];
	                    $jiaqi_total[$user['id']][$type_id]['name'] = $v['name'];
	                    //是否取消全勤
	                    if($v['if_cancel_full_att'] == 1 && $jiaqi_time[$user['id']][$type_id]['num'] > 0){
	                    	$if_cancel_full_att = false;
	                    }
	                }else{
	                    $jiaqi_total[$user['id']][$type_id]['num'] = 0;
	                    $jiaqi_total[$user['id']][$type_id]['name'] = $v['name'];
	                }
	            }
	    	}
        	
	    	$tmp = array();
        	$tmp['year'] = $year;
        	$tmp['month'] = $month;
        	$tmp['year_month'] = $year.$month;
        	$tmp['user_id'] = $user['id'];
        	$tmp['chidao_num'] = !in_array($user['id'], $free_data) ? $chidao_num : 0;
        	$tmp['chidao_sum'] = !in_array($user['id'], $free_data) ? $chidao_sum : 0;//迟到
        	$tmp['zaotui_num'] = !in_array($user['id'], $free_data) ? $zaotui_num : 0;
        	$tmp['zaotui_sum'] = !in_array($user['id'], $free_data) ? $zaotui_sum : 0;//早退
        	$tmp['yingchuqin_total'] = !in_array($user['id'], $free_data) ? $yingchuqin_total : 0;//应出勤工时
        	$tmp['chuqin_total'] = !in_array($user['id'], $free_data) ? $chuqin_total : 0;//出勤工时
        	$tmp['queqin_total'] = !in_array($user['id'], $free_data) ? $queqin_total : 0;//缺勤工时
        	$qingjia_total = $qingjia_tmp = array();
        	foreach ($type_data as $type_id => $val) {
        		$qingjia_tmp['type_id'] = $type_id;
        		$qingjia_tmp['time_str'] = $jiaqi_total[$user['id']][$type_id]['num'] ?? 0;
        		$qingjia_total[] = $qingjia_tmp;
        	}
        	$tmp['qingjia_total'] = serialize($qingjia_total);
        	$year_qingjia_total = $year_qingjia_tmp = array();
        	foreach ($type_data as $type_id => $val) {
        		$year_qingjia_tmp['type_id'] = $type_id;
        		$year_qingjia_tmp['time_str'] = $year_jiaqi_total[$user['id']][$type_id] ?? 0;
        		$year_qingjia_total[] = $year_qingjia_tmp;
        	}
        	$tmp['year_qingjia_total'] = serialize($year_qingjia_total);
        	$tmp['gongzuori_jiaban_total'] = $gongzuori_jiaban_total;
        	$tmp['zhoumo_jiaban_total'] = $zhoumo_jiaban_total;
        	$tmp['jiejiari_jiaban_total'] = $jiejiari_jiaban_total;
        	$tmp['waiqin_time_total'] = $waiqin_time_total;
        	//判断是否全勤
        	$tmp['if_full_att'] = 0;
        	if($chidao_num == 0 && $chidao_sum == 0 && $zaotui_num == 0 && $zaotui_sum == 0 && $queqin_total == 0 && $if_cancel_full_att && $yingchuqin_total != 0 && $chuqin_total != 0){
        		$tmp['if_full_att'] = 1;//全勤
        	}
        	if(in_array($user['id'], $free_data)){
        		$tmp['if_full_att'] = 0;//免签取消全勤
        	}
        	$tmp['ex_time_num'] = $ex_time_num;
        	$tmp['created_at'] = date('Y-m-d H:i:s');
        	$tmp['updated_at'] = date('Y-m-d H:i:s');
        	$items[] = $tmp;
        	
        }
        /*$qingjia_total = unserialize($items[12]['qingjia_total']);
        $aa = array();
        foreach ($qingjia_total as $key => $value) {
        	$aa[$value['type_id']] = $value['time_str'];
        }
        $year_qingjia_total = unserialize($items[12]['year_qingjia_total']);
        $bb = array();
        foreach ($year_qingjia_total as $key => $value) {
        	$bb[$value['type_id']] = $value['time_str'];
        }
        $jiben = 11000;
        $koufei = 0;
        foreach ($type_data as $key => $value) {
        	if($value['if_cancel_salary'] == 3){
        		//根据条件判断扣工资情况
        		$pre_num = $bb[$key]-$aa[$key]+0.5;//请假最低按半天算
        		for ($i=$pre_num; $i <= $bb[$key]; $i+=0.5) {
        			foreach (unserialize($value['condition']) as $k => $v) {
	        			if($i >= $v['start_day'] && $i <= $v['end_day']){
	        				if($v['type'] == 1){
	        					$koufei += ($jiben/22) * ($v['percent'] / 100) * 0.5;
	        				}else{
	        					$koufei += ($jiben/22) * 0.5;
	        				}
	        			}
	        		}
        		}
        	}
        	if($value['if_cancel_salary'] == 1){

        	}
        }*/
        $stat = new \App\Models\AttendanceStatistic;
        //重复生成 则先删除之前的数据
        $stat->where('year_month', $year.$month)->whereIn('user_id', $user_ids)->delete();
        $res = $stat->insert($items);
        if($res){
        	return ['code' => 1, 'message' => '生成成功'];
        }
        return ['code' => 0, 'message' => '生成失败'];
    }

    //获取指定期间的每一天
    public function getDate($start_time,$end_time)
    {
        $dates = Holiday::whereBetween('date',[$start_time,$end_time])->get(['date']);
        $days = [];
        $week_array = ['日', '一', '二', '三', '四', '五', '六'];
        foreach ($dates as $key => $val) {
            $days[$key] = ['date' => $val['date'], 'week' => '周' . $week_array[date('w', strtotime($val['date']))]];
        }
        return $days;
    }

    //获取历史统计
    public function getHistoryList($inputs = []){
    	if(empty($inputs['month'])) return ['code' => 0, 'message' => '请传入月份,如:2019-02'];
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$stat = new \App\Models\AttendanceStatistic;
    	$inputs['year_month'] = date('Ym', strtotime($inputs['month'].'-01'));
    	$data = $stat->getStatisticList($inputs);
    	//假期类型
    	$holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
        }
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$tmp = [];
    		$tmp['realname'] = $user_data['id_realname'][$value->user_id] ?? '--';
        	$tmp['dept'] = $user_data['id_dept'][$value->user_id] ?? '--';
        	$tmp['chidao_sum'] = $value->chidao_sum;//迟到
        	$tmp['zaotui_sum'] = $value->zaotui_sum;//早退
        	$tmp['yingchuqin_total'] = $value->yingchuqin_total;//应出勤工时
        	$tmp['chuqin_total'] = $value->chuqin_total;//出勤工时
        	$tmp['queqin_total'] = $value->queqin_total;//缺勤工时
        	$tmp['ex_time_num'] = $value->ex_time_num;//迟到超过40分钟后的次数
        	$qingjia_total = unserialize($value->qingjia_total);
        	foreach ($qingjia_total as $k => $val) {
        		$tmp['jiaqi_'.$val['type_id']] = $val['time_str'];
        	}
        	$tmp['gongzuori_jiaban_total'] = $value->gongzuori_jiaban_total;
        	$tmp['zhoumo_jiaban_total'] = $value->zhoumo_jiaban_total;
        	$tmp['jiejiari_jiaban_total'] = $value->jiejiari_jiaban_total;
        	$tmp['waiqin_time_total'] = $value->waiqin_time_total;
        	$fields = array_keys($tmp);
        	$data['datalist'][$k] = $tmp;
        	$items[] = $tmp;
    	}
    	if(!isset($fields)){
        	$fields = array();
        	$fields['realname'] = '';
        	$fields['dept'] = '';
        	$fields['chidao_sum'] = 0;//迟到
        	$fields['zaotui_sum'] = 0;//早退
        	$fields['yingchuqin_total'] = 0;//应出勤工时
        	$fields['chuqin_total'] = 0;//出勤工时
        	$fields['queqin_total'] = 0;//缺勤工时
        	$fields['baobei_list'] = [];//报备数据列表
        	$fields['ex_time_num'] = 0;//迟到超过40分钟后的次数
        	foreach ($type_data as $type_id => $val) {
        		$fields['jiaqi_'.$type_id] = 0;
        	}
        	$fields['gongzuori_jiaban_total'] = 0;
        	$fields['zhoumo_jiaban_total'] = 0;
        	$fields['jiejiari_jiaban_total'] = 0;
        	$fields['waiqin_time_total'] = 0;
        	$fields = array_keys($fields);
        }
    	$data['datalist'] = $items;
    	$table_head = $this->table_head($fields,$type_data);
        $data['table_head'] = $table_head;
    	return $data;
    }
}

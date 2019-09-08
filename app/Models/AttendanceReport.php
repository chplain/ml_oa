<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class AttendanceReport extends Model
{
    //
    protected $table = 'attendance_reports';
    
    /**
    * 根据日期报备记录
    * @author molin
    * @date 2018-11-26
    */

    public function createReport($applyReport){
        $content = unserialize($applyReport->content);
        $insert = $record_insert  = array();
        $user = new \App\Models\User;
        $user_info = $user->where('id', $applyReport->user_id)->select(['username','realname','number'])->first();
        //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();//根据签到时间

        foreach ($content as $key => $value) {
            $tmp = array();
            $tmp['apply_id'] = $applyReport->id; 
            $tmp['user_id'] = $applyReport->user_id; 
            $tmp['date'] = $value['date']; 
            $tmp['type'] = $value['type'];
            $tmp['created_at'] = date('Y-m-d H:i:s');
            $tmp['updated_at'] = date('Y-m-d H:i:s');
            $insert[] = $tmp;
            //生成报备考勤记录
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
            $am_start_time = $setting_info['am_start_time'].':00';//当天上班时间
            $pm_end_time = $setting_info['pm_end_time'].':00';//当天下班时间
            $temp = array();
            if($value['type'] == 1){
                $temp['number'] = $user_info->number;
                $temp['user_id'] = $applyReport->user_id;
                $temp['username'] = $user_info->username;
                $temp['realname'] = $user_info->realname;
                $temp['type'] = 1;//1为报备记录  0为考勤记录
                $temp['apply_id'] = $applyReport->id;//申请id
                $temp['year'] = date('Y', strtotime($value['date']));
                $temp['month'] = date('m', strtotime($value['date']));
                $temp['punch_time'] = $value['date'].' '.$am_start_time;//未签到
                $temp['created_at'] = date('Y-m-d H:i:s');
                $temp['updated_at'] = date('Y-m-d H:i:s');
            }else if($value['type'] == 2){
                $temp['number'] = $user_info->number;
                $temp['user_id'] = $applyReport->user_id;
                $temp['username'] = $user_info->username;
                $temp['realname'] = $user_info->realname;
                $temp['type'] = 1;//1为报备记录  0为考勤记录
                $temp['apply_id'] = $applyReport->id;//申请id
                $temp['year'] = date('Y', strtotime($value['date']));
                $temp['month'] = date('m', strtotime($value['date']));
                $temp['punch_time'] = $value['date'].' '.$pm_end_time;//未签退
                $temp['created_at'] = date('Y-m-d H:i:s');
                $temp['updated_at'] = date('Y-m-d H:i:s');
            }else if($value['type'] == 3){
                //未签到和未签退  生成两条记录
                $a = array();
                for ($i=0; $i < 2; $i++) { 
                    $a[$i]['number'] = $user_info->number;
                    $a[$i]['user_id'] = $applyReport->user_id;
                    $a[$i]['username'] = $user_info->username;
                    $a[$i]['realname'] = $user_info->realname;
                    $a[$i]['type'] = 1;//1为报备记录  0为考勤记录
                    $a[$i]['apply_id'] = $applyReport->id;//申请id
                    $a[$i]['year'] = date('Y', strtotime($value['date']));
                    $a[$i]['month'] = date('m', strtotime($value['date']));
                    if($i == 0){
                        $a[$i]['punch_time'] = $value['date'].' '.$am_start_time;//上班时间
                    }else{
                        $a[$i]['punch_time'] = $value['date'].' '.$pm_end_time;//下班时间
                    }
                    $a[$i]['created_at'] = date('Y-m-d H:i:s');
                    $a[$i]['updated_at'] = date('Y-m-d H:i:s');
                }
            }
            if(!empty($temp)){
                $record_insert[] = $temp;
            }
        }
        if(!empty($a) && empty($record_insert)){
            $record_insert = $a;
        }else if(!empty($a) && !empty($record_insert)){
            $record_insert = array_merge($record_insert, $a);
        }
        DB::transaction(function () use ($insert, $record_insert) {
            $report = new AttendanceReport;
            $report->insert($insert);
            $record = new \App\Models\AttendanceRecord;
            $record->insert($record_insert);
        }, 5);
        return true;

    }

}

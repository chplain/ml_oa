<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplyAttendanceDetail extends Model
{
    //
    protected $table = 'apply_attendance_details';

    // 关联申请信息
    public function hasApply()
    {
        return $this->belongsTo('App\Models\ApplyAttendance', 'apply_id', 'id');
    }

    //获取数据
    public function getDetailList($inputs){
    	$where_query = $this->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs) {
    					return $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
    				})
    				->when(isset($inputs['type']) && is_numeric($inputs['type']), function ($query) use ($inputs) {
    					return $query->where('type', 1);
    				})
    				->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
    					return $query->where('user_id', $inputs['user_id']);
    				})
                    ->when(isset($inputs['user_ids']) && is_array($inputs['user_ids']), function ($query) use ($inputs) {
                        return $query->whereIn('user_id', $inputs['user_ids']);
                    })
                    ->when(isset($inputs['year']) && !empty($inputs['year']), function ($query) use ($inputs) {
                        return $query->where('year', $inputs['year']);
                    })
                    ->when(isset($inputs['month']) && !empty($inputs['month']), function ($query) use ($inputs) {
                        return $query->where('month', $inputs['month']);
                    })
                    ->with(['hasApply' => function ($query) {
                        return $query->select(['id','type','start_time','end_time','time_data']);
                    }]);
        
        $list = $where_query->get();
        return $list;
    }

    //请假详情  按天生成
    public function createDetail($applyAtt){

        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();//根据签到时间
        if(empty($setting_list)){
            return;
        }
        $detail = new \App\Models\ApplyAttendanceDetail;
        $if_exist = $detail->where('apply_id', $applyAtt->id)->first();
        if(!empty($if_exist)){
            return;//防止重复
        }
        $items = array();
        if($applyAtt->type == 1){
            //请假
            $holiday = new \App\Models\HolidayType;
            $type_info = $holiday->where('id', $applyAtt->leave_type)->first();
            //节假日、工作日列表
            $attendance =  new \App\Models\Attendance;
            $attendance_list = $attendance->whereBetween('date',[date('Y-m-d', strtotime($applyAtt->start_time)), date('Y-m-d', strtotime($applyAtt->end_time))])->get();
            $work_date = array();
            foreach ($attendance_list as $key => $value) {
                $work_date[$value->date] = $value->type;
            }
            $days = prDates($applyAtt->start_time, $applyAtt->end_time, true);
            foreach ($days as $key => $day) {
                if($type_info->if_lianxiu == 0 && $work_date[$day] != 0){
                    //如果是非连休类型 跳过非工作日
                    continue;
                }
                //获取上班时间配置
                $setting_info = array();
                foreach ($setting_list as $sett) {
                    //取打卡日期大于配置修改的日期
                    if(strtotime($day) > strtotime($sett['created_at'])){
                        $setting_info = $sett;
                    }
                }
                if(empty($setting_info)){
                    $setting_info = $setting_list[0];
                }
                if(date('Y-m-d', strtotime($applyAtt->start_time)) == $day && strtotime($applyAtt->start_time) >= strtotime($day.' '.$setting_info['pm_end_time'].':00')){
                    continue;//开始请假的时间大于下班时间则不算
                }
                if(date('Y-m-d', strtotime($applyAtt->end_time)) == $day && strtotime($applyAtt->end_time) <= strtotime($day.' '.$setting_info['am_start_time'].':00')){
                    continue;//开始请假的时间小于上班时间则不算
                }
                $items[$key]['apply_id'] = $applyAtt->id;
                $items[$key]['user_id'] = $applyAtt->user_id;
                $items[$key]['type'] = $applyAtt->type;
                $items[$key]['leave_type'] = $applyAtt->leave_type;
                $items[$key]['year'] = date('Y', strtotime($day));//年
                $items[$key]['month'] = date('m', strtotime($day));//月
                $items[$key]['date'] = $day;//日期
                //请假
                $items[$key]['time_str'] = 1;//默认一天
                if(date('Y-m-d', strtotime($applyAtt->start_time)) == $day){
                    if(strtotime($applyAtt->start_time) >= strtotime($day.' '.$setting_info['am_end_time'].':00')){
                        //12点后请假算一天
                        $items[$key]['time_str'] = 0.5;//半天
                    }else{
                        $items[$key]['time_str'] = 1;//1天
                    }
                }
                if(date('Y-m-d', strtotime($applyAtt->end_time)) == $day && date('Y-m-d', strtotime($applyAtt->start_time)) == $day){
                    if(strtotime($applyAtt->end_time) > strtotime($day.' '.$setting_info['pm_start_time'].':00') && strtotime($applyAtt->start_time) < strtotime($day.' '.$setting_info['am_end_time'].':00')){
                        //上午开始请假 结束时间是下午  则算请假一天
                        $items[$key]['time_str'] = 1;//1天
                    }else{
                        $items[$key]['time_str'] = 0.5;//半天
                    }
                }
                if(date('Y-m-d', strtotime($applyAtt->end_time)) == $day && date('Y-m-d', strtotime($applyAtt->start_time)) != $day){
                    if(strtotime($applyAtt->end_time) > strtotime($day.' '.$setting_info['pm_start_time'].':00')){
                        //结束时间在下午上班后  则算请假一天
                        $items[$key]['time_str'] = 1;//1天
                    }else{
                        $items[$key]['time_str'] = 0.5;//半天
                    }
                }
                $items[$key]['created_at'] = date('Y-m-d H:i:s');
                $items[$key]['updated_at'] = date('Y-m-d H:i:s');
            }
        }else{
            //加班、外勤时效都记在开始那天
            if(!empty($applyAtt->time_data)){
                $time_data = unserialize($applyAtt->time_data);
                //提交多条加班记录
                foreach ($time_data as $key => $value) {
                    $items[$key]['apply_id'] = $applyAtt->id;
                    $items[$key]['user_id'] = $applyAtt->user_id;
                    $items[$key]['type'] = $applyAtt->type;
                    $items[$key]['leave_type'] = 0;
                    $items[$key]['year'] = date('Y', strtotime($value['start_time']));//年
                    $items[$key]['month'] = date('m', strtotime($value['start_time']));//月
                    $items[$key]['date'] = date('Y-m-d', strtotime($value['start_time']));//日期
                    $items[$key]['time_str'] = $value['time_str']; //加班时间
                    $items[$key]['created_at'] = date('Y-m-d H:i:s');
                    $items[$key]['updated_at'] = date('Y-m-d H:i:s');
                }
            }else{
                //兼容上一个版本的加班记录和外勤
                $items[0]['apply_id'] = $applyAtt->id;
                $items[0]['user_id'] = $applyAtt->user_id;
                $items[0]['type'] = $applyAtt->type;
                $items[0]['leave_type'] = 0;
                $items[0]['year'] = date('Y', strtotime($applyAtt->start_time));//年
                $items[0]['month'] = date('m', strtotime($applyAtt->start_time));//月
                $items[0]['date'] = date('Y-m-d', strtotime($applyAtt->start_time));//日期
                //加班、外勤
                $items[0]['time_str'] = $applyAtt->time_str; //加班 、外勤时间
                $items[0]['created_at'] = date('Y-m-d H:i:s');
                $items[0]['updated_at'] = date('Y-m-d H:i:s');
            }
            
        }
        
        $detail->insert($items);
    }
}

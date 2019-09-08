<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    //
    protected $table = 'attendance_settings';

    //保存数据
    public function storeData($inputs){
    	$attendance = new AttendanceSetting;
        $attendance->am_start_time = $inputs['am_start_time'];
        $attendance->am_end_time = $inputs['am_end_time'];
        $attendance->am_start_before_time = $inputs['am_start_before_time'];
    	$attendance->am_start_after_time = $inputs['am_start_after_time'];
        $attendance->pm_start_time = $inputs['pm_start_time'];
        $attendance->pm_end_time = $inputs['pm_end_time'];
        $attendance->pm_end_before_time = $inputs['pm_end_before_time'];
        $attendance->pm_end_after_time = $inputs['pm_end_after_time'];
    	return $attendance->save();
    }
}

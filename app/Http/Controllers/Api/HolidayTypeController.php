<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HolidayTypeController extends Controller
{
    //假期类型管理
    //列表
	public function index(){
		$type = new \App\Models\HolidayType;
		$type_list = $type->select(['id','name','way','status'])->get();
		$items = array();
		foreach ($type_list as $key => $value) {
			$items[$key]['id'] = $value->id;
			$items[$key]['name'] = $value->name;
			if($value->way == 1){
				$items[$key]['way'] = '天';
			}else{
				$items[$key]['way'] = '小时';
			}
			$items[$key]['status'] = $value->status;
		}
		$data['datalist'] = $items;
		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
	}

	//添加
	public function store(){
		$inputs = request()->all();
		$type = new \App\Models\HolidayType;
		$rules = [
            'name' => 'required|max:5',
            'if_lianxiu' => 'required|integer',
            'if_nianjia' => 'required|integer',
            'lianxiu_date' => 'required|integer',
            'if_cancel_full_att' => 'required|integer',
            'if_cancel_salary' => 'required|integer',
            'salary_percent' => 'required|integer',
            'suit' => 'required|integer',
            'suit_sex' => 'required|integer'
    	];
    	$attributes = [
            'name' => '名称',
            'if_lianxiu' => 'if_lianxiu 一次性休假天数，是否启用',
            'if_nianjia' => 'if_nianjia 是否为年假',
            'lianxiu_date' => 'lianxiu_date 连休天数',
            'if_cancel_full_att' => 'if_cancel_full_att 请假后是否取消全勤',
            'if_cancel_salary' => 'if_cancel_salary 请假后是否取消请假时间内工资',
            'salary_percent' => 'salary_percent 百分比',
            'suit' => 'suit 适用员工',
            'suit_sex' => 'suit_sex 性别'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if($inputs['if_nianjia'] == 1){
            $if_ok = $type->where('if_nianjia', 1)->first();
            if($if_ok){
                return response()->json(['code' => 0, 'message' => '已经设置一个年假了，不能设置多个']);
            }
        }
        if($inputs['if_lianxiu'] == 1 && (!is_numeric($inputs['lianxiu_date']) || $inputs['lianxiu_date'] == 0)){
        	return response()->json(['code' => -1, 'message' => '休假天数必须大于0']);
        }
        if($inputs['if_cancel_salary'] == 2 && (!is_numeric($inputs['salary_percent']) || $inputs['salary_percent'] == 0)){
        	return response()->json(['code' => -1, 'message' => '请填写百分比']);
        }
        if($inputs['if_cancel_salary'] == 3){
            if(!is_array($inputs['condition'])){
                return response()->json(['code' => -1, 'message' => 'condition请填写条件']);
            }
            foreach ($inputs['condition'] as $key => $value) {
                if(!isset($value['start_day']) || !is_numeric($value['start_day'])){
                    return response()->json(['code' => -1, 'message' => '请填写开始天数']);
                }
                if(!isset($value['end_day']) || !is_numeric($value['end_day'])){
                    return response()->json(['code' => -1, 'message' => '请填写结束天数']);
                }
                if(!isset($value['type']) || !is_numeric($value['type'])){
                    return response()->json(['code' => -1, 'message' => '请选择算法类型']);
                }
                if(!isset($value['percent']) || !is_numeric($value['percent'])){
                    return response()->json(['code' => -1, 'message' => '请填写百分比']);
                }
            }
        }
        if($inputs['suit'] == 2 && (!is_numeric($inputs['suit_sex']) || $inputs['suit_sex'] == 0)){
        	return response()->json(['code' => -1, 'message' => '请选中性别']);
        }
        $result = $type->storeData($inputs);
        if($result){
            systemLog('考勤管理', '添加了假期类型['.$inputs['name'].']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
	}

    //编辑
	public function edit(){
		$inputs = request()->all();
		$type = new \App\Models\HolidayType;
		if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
			if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
				return response()->json(['code' => -1, 'message' => '缺少参数id']);
			}
			$type_info = $type->where('id', $inputs['id'])->first();
            $type_info->condition = unserialize($type_info->condition);
			return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $type_info]);
		}
		$rules = [
    		'id' => 'required|integer',
            'name' => 'required|max:5',
            'if_lianxiu' => 'required|integer',
            'if_nianjia' => 'required|integer',
            'lianxiu_date' => 'required|integer',
            'if_cancel_full_att' => 'required|integer',
            'if_cancel_salary' => 'required|integer',
            'salary_percent' => 'required|integer',
            'suit' => 'required|integer',
            'suit_sex' => 'required|integer'
    	];
    	$attributes = [
            'id' => 'id',
            'name' => '名称',
            'if_lianxiu' => 'if_lianxiu 一次性休假天数，是否启用',
            'if_nianjia' => 'if_nianjia 是否为年假',
            'lianxiu_date' => 'lianxiu_date 连休天数',
            'if_cancel_full_att' => 'if_cancel_full_att 请假后是否取消全勤',
            'if_cancel_salary' => 'if_cancel_salary 请假后是否取消请假时间内工资',
            'salary_percent' => 'salary_percent 百分比',
            'suit' => 'suit 适用员工',
            'suit_sex' => 'suit_sex 性别'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if($inputs['if_nianjia'] == 1){
            $if_ok = $type->where('if_nianjia', 1)->where('id', '<>', $inputs['id'])->first();
            if($if_ok){
                return response()->json(['code' => 0, 'message' => '已经设置一个年假了，不能设置多个']);
            }
        }
        if($inputs['if_lianxiu'] == 1 && (!is_numeric($inputs['lianxiu_date']) || $inputs['lianxiu_date'] == 0)){
        	return response()->json(['code' => -1, 'message' => '休假天数必须大于0']);
        }
        if($inputs['if_cancel_salary'] == 2 && (!is_numeric($inputs['salary_percent']) || $inputs['salary_percent'] == 0)){
        	return response()->json(['code' => -1, 'message' => '请填写百分比']);
        }
        if($inputs['if_cancel_salary'] == 3){
            if(!is_array($inputs['condition'])){
                return response()->json(['code' => -1, 'message' => 'condition请填写条件']);
            }
            foreach ($inputs['condition'] as $key => $value) {
                if(!isset($value['start_day']) || !is_numeric($value['start_day'])){
                    return response()->json(['code' => -1, 'message' => '请填写开始天数']);
                }
                if(!isset($value['end_day']) || !is_numeric($value['end_day'])){
                    return response()->json(['code' => -1, 'message' => '请填写结束天数']);
                }
                if(!isset($value['type']) || !is_numeric($value['type'])){
                    return response()->json(['code' => -1, 'message' => '请选择算法类型']);
                }
                if(!isset($value['percent']) || !is_numeric($value['percent'])){
                    return response()->json(['code' => -1, 'message' => '请填写百分比']);
                }
            }
        }
        if($inputs['suit'] == 2 && (!is_numeric($inputs['suit_sex']) || $inputs['suit_sex'] == 0)){
        	return response()->json(['code' => -1, 'message' => '请选中性别']);
        }
        $result = $type->storeData($inputs);
        if($result){
            systemLog('考勤管理', '编辑了假期类型['.$inputs['id'].'-'.$inputs['name'].']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
	}

	//启用、禁用
	public function using(){
		$inputs = request()->all();
		$rules = [
    		'id' => 'required|integer',
            'status' => 'required|integer'
    	];
    	$attributes = [
            'id' => 'id',
            'status' => 'status'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if(!in_array($inputs['status'], [0,1])){
        	return response()->json(['code' => -1, 'message' => 'status只能是0或者1']);
        }
		$type = new \App\Models\HolidayType;
		$type_info = $type->where('id', $inputs['id'])->first();
		$type_info->status = $inputs['status'];
		$res = $type_info->save();
        $log_txt = $inputs['status'] == 1 ? '启用':'禁用';
		if($res){
            systemLog('考勤管理', $log_txt.'了假期类型['.$inputs['id'].'-'.$type_info->name.']');
			return response()->json(['code' => 1, 'message' => '操作成功']);
		}
		return response()->json(['code' => 0, 'message' => '操作失败']);
	}

    /**
    * 假期明细
    * @author molin 
    * @date 2018-11-23
    **/
    public function detail(){
        $inputs = request()->all();
        $start_time = date('Y-01-01');
        $end_time = date('Y-m-d', time()-86400);//前一天为止
        if(isset($inputs['cur_month']) && $inputs['cur_month'] == 1){
            //获取本月书数据
            $start_time = date('Y-m-01');
            $end_time = date('Y-m-d', strtotime("$start_time +1 month -1 day"));
        }else if(isset($inputs['cur_year']) && $inputs['cur_year'] == 1){
            //获取本年书数据
            $start_time = date('Y-01-01');
            $end_time = date('Y-12-31');
        }else if(isset($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['start_time']) && !empty($inputs['end_time'])){
            $start_time = $inputs['start_time'];
            $end_time = $inputs['end_time'];
        }
        $user = new \App\Models\User;
        if(isset($inputs['keywords'])){
            $inputs['keyword'] = $inputs['keywords'];
        }
        $data = $user->queryUserList($inputs);
        // dd($data);
        $items = $user_ids = array();
        foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value['id'];
            $items['realname'] = $value['realname'];
            $items['dept'] = $value['dept']['name'];
            $items['formal_date'] = $value['contracts']['positive_date'] ? $value['contracts']['positive_date'] : '';
            $user_ids[] = $value['id'];
            $data['datalist'][$key]=  $items;
        }
        //假期类型
        $holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name', 'if_nianjia'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
            $type_data[$value->id]['if_nianjia'] = $value->if_nianjia;
        }

        //请假详细信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->whereBetween('date',[$start_time, $end_time])->whereIn('user_id', $user_ids)->where('type', 1)->get();
        $qingjia_list = array();
        foreach ($detail_list as $key => $value) {
            $qingjia_list[$value->user_id][$value->date][$value->leave_type] = $value->time_str;
        }
        //节假日、工作日列表
        $attendance =  new \App\Models\Attendance;
        $attendance_list = $attendance->whereBetween('date',[$start_time, $end_time])->get();
        
        //计算年假
        $year_set = new \App\Models\HolidayYear;
        $set_info = $year_set->first();
        $nianjia_total = array();
        if(!empty($set_info) && $set_info['if_use'] == 1){
            $days_setting = unserialize($set_info['days_setting']);
            foreach ($data['datalist'] as $key => $value) {
                $nianjia_total[$value['id']] = 0;
                if(!empty($value['formal_date']) && !empty($set_info['redate'])){
                    $d = date('d', strtotime($value['formal_date']));
                    $positive_date = $value['formal_date'];
                    if($d <= 15){
                        $positive_date = date('Y-m-01', strtotime($positive_date));//当月1号
                    }else{
                        $positive_date = date('Y-m-01', strtotime("$positive_date +1 month"));//下个月的第一天
                    }
                    $formal_year_date = date('Y', strtotime($positive_date)).'-'.$set_info['redate'];//转正日期当年的结算日期
                    if(strtotime($positive_date) >= strtotime($formal_year_date)){
                        //当转正日期大于当年结算日期时 结算日期往后推一年
                        $formal_year_date = date('Y-m-d', strtotime("$formal_year_date +1 year"));
                    }
                    $start_year = date('Y', strtotime($start_time));//搜索开始年份
                    $end_year = date('Y', strtotime($end_time));//搜索结束年份
                    $nianjia = 0;
                    if(strtotime($end_time) < strtotime($formal_year_date)){
                        //搜索时间小于转正时间
                        $nianjia_total[$value['id']] = 0;
                    }else if(strtotime($end_time) > strtotime($positive_date) && strtotime($end_time) < strtotime($formal_year_date)){
                        //搜索结束时间大于转正时间 小于转正当年结算时间
                        $nianjia_total[$value['id']] = 0;
                    }else if(strtotime($start_time) < strtotime($positive_date) && strtotime($end_time) > strtotime($formal_year_date)){
                        //开始时间小于转正时间并且结束时间大于当年结算时间
                        if(strtotime($end_time) < strtotime($end_year.'-'.$set_info['redate'])){
                            //结束时间小于结束时间那年的结算时间 
                            //不满一年的年假天数
                            $month = 0;
                            $formal_date = $positive_date;
                            for($i = 1; $i < 9999; $i++){
                                if(strtotime($formal_year_date) >= strtotime("$formal_date +".$i." month")){
                                    $month++;
                                }else{
                                    break;
                                }
                            }
                            $nianjia1 = $set_info['days'] / 12 * $month;
                            $num1 = intval($nianjia1);
                            if(($nianjia1-$num1) < 0.5){
                                $num2 = 0;
                            }else if(($nianjia1-$num1) >= 0.5 && ($nianjia1-$num1) < 1){
                                $num2 = 0.5;
                            }
                            $nianjia1 = $num1 + $num2;
                            $year = 0;
                            for($i = 1; $i < 9999; $i++){
                                if(strtotime("$formal_year_date +".$i." year") <= strtotime($end_time)){
                                    $year++;
                                }else{
                                    break;
                                }
                            }
                            $nianjia2 = 0;
                            if($year > 0){
                                foreach ($days_setting as $kk => $vv) {
                                    if($vv['min'] == $year){
                                        $nianjia2 += $vv['days'];
                                    }else if($vv['max'] == $year){
                                        $nianjia2 += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                    }else if($vv['max'] > $year && $vv['min'] < $year){
                                        $nianjia2 += ($year - $vv['min'] +1 ) * $vv['days'];
                                    }else if($year > $vv['max']){
                                        $nianjia2 += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                    }
                                }
                            }
                            $nianjia = $nianjia1 + $nianjia2;
                            $nianjia_total[$value['id']] = $nianjia;
                        }
                    }else if(strtotime($start_time) > strtotime($positive_date) && strtotime($start_time) < strtotime($formal_year_date) && strtotime($end_time) > strtotime($formal_year_date)){
                        //开始时间大于转正时间并且小于当年转正结算时间 并且结束时间大于当年转正结算时间
                        $month = 0;
                        $formal_date = $positive_date;
                        for($i = 1; $i < 9999; $i++){
                            if(strtotime($formal_year_date) >= strtotime("$formal_date +".$i." month")){
                                $month++;
                            }else{
                                break;
                            }
                        }
                        $nianjia1 = $set_info['days'] / 12 * $month;
                        $num1 = intval($nianjia1);
                        if(($nianjia1-$num1) < 0.5){
                            $num2 = 0;
                        }else if(($nianjia1-$num1) >= 0.5 && ($nianjia1-$num1) < 1){
                            $num2 = 0.5;
                        }
                        $nianjia1 = $num1 + $num2;
                        $year = 0;
                        for($i = 1; $i < 9999; $i++){
                            if(strtotime("$formal_year_date +".$i." year") <= strtotime($end_time)){
                                $year++;
                            }else{
                                break;
                            }
                        }
                        $nianjia2 = 0;
                        if($year > 0){
                            foreach ($days_setting as $kk => $vv) {
                                if($vv['min'] == $year){
                                    $nianjia2 += $vv['days'];
                                }else if($vv['max'] == $year){
                                    $nianjia2 += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                }else if($vv['max'] > $year && $vv['min'] < $year){
                                    $nianjia2 += ($year - $vv['min'] +1 ) * $vv['days'];
                                }else if($year > $vv['max']){
                                    $nianjia2 += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                }
                            }
                        }
                        $nianjia = $nianjia1 + $nianjia2;
                        $nianjia_total[$value['id']] = $nianjia;
                    }else if(strtotime($start_time) >= strtotime($formal_year_date)){
                        //开始时间大于当年转正结算时间
                        $nianjia1 = 0;
                        if(strtotime($start_time) < strtotime("$formal_year_date +1 year")){
                            $month = 0;
                            $formal_date = $positive_date;
                            for($i = 1; $i < 9999; $i++){
                                if(strtotime($formal_year_date) >= strtotime("$formal_date +".$i." month")){
                                    $month++;
                                }else{
                                    break;
                                }
                            }
                            $nianjia1 = $set_info['days'] / 12 * $month;
                            $num1 = intval($nianjia1);
                            if(($nianjia1-$num1) < 0.5){
                                $num2 = 0;
                            }else if(($nianjia1-$num1) >= 0.5 && ($nianjia1-$num1) < 1){
                                $num2 = 0.5;
                            }
                            $nianjia1 = $num1 + $num2;
                            $year = 0;
                            for($i = 1; $i < 9999; $i++){
                                if(strtotime("$formal_year_date +".$i." year") <= strtotime($end_time)){
                                    $year++;
                                }else{
                                    break;
                                }
                            }
                            $nianjia2 = 0;
                            if($year > 0){
                                foreach ($days_setting as $kk => $vv) {
                                    if($vv['min'] == $year){
                                        $nianjia2 += $vv['days'];
                                    }else if($vv['max'] == $year){
                                        $nianjia2 += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                    }else if($vv['max'] > $year && $vv['min'] < $year){
                                        $nianjia2 += ($year - $vv['min'] +1 ) * $vv['days'];
                                    }else if($year > $vv['max']){
                                        $nianjia2 += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                    }
                                }
                            }
                            $nianjia = $nianjia1 + $nianjia2;
                            $nianjia_total[$value['id']] = $nianjia;
                        }else{
                            $year1 = 0;
                            for($i = 1; $i < 99; $i++){
                                if(strtotime("$formal_year_date +".$i." year") <= strtotime($start_time)){
                                    $year1++;
                                }else{
                                    break;
                                }
                            }
                            $year2 = 0;
                            for($i = 1; $i < 99; $i++){
                                if(strtotime("$formal_year_date +".$i." year") <= strtotime($end_time)){
                                    $year2++;
                                }else{
                                    break;
                                }
                            }
                            $nianjia = 0;
                            //判断条件 y1为搜索开始日期距离转正日期的年限  y2为搜索结束日期距离转正日期的年限
                            /*y1 == min && y2==max   (max-min+1)*days
                            y1 == min && y2 == min   days
                            y1 == min && y2 < max   (y2-min+1)*days
                            y1 == min && y2 > max  (max-min+1)*days
                            y1 > min && y1 < max && y2 == max  (max-y1+1)*days
                            y1 > min && y1 < max && y2 < max (y2-y1+1)*days
                            y1 > min && y1 < max && y2 > max (max-y1+1)*days
                            y1 == max && y2 == max   days
                            y1 == max && y2 > max  days
                            y1 < min && y2 == min  days
                            y1 < min && y2 > min && y2 == max  (max-min+1)*days
                            y1 < min && y2 > min && y2 < max   (y2-min+1)*days
                            y1 < min && y2 > min && y2 > max   (max-min+1)*days*/
                            foreach ($days_setting as $kk => $vv) {
                                if($year1 == $vv['min'] && $year2 == $vv['max']){
                                    $nianjia += ($year2 - $year1 + 1 ) * $vv['days'];
                                }else if($year1 == $vv['min'] && $year2 == $vv['min']){
                                    $nianjia += $vv['days'];
                                }else if($year1 == $vv['min'] && $year2 < $vv['max']){
                                    $nianjia += ($year2 - $year1 + 1 ) * $vv['days'];
                                }else if($year1 == $vv['min'] && $year2 > $vv['max']){
                                    $nianjia += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                }else if($year1 > $vv['min'] && $year1 < $vv['max'] && $year2 == $vv['max']){
                                    $nianjia += ($year2 - $year1 + 1 ) * $vv['days'];
                                }else if($year1 > $vv['min'] && $year1 < $vv['max'] && $year2 < $vv['max']){
                                    $nianjia += ($year2 - $year1 + 1 ) * $vv['days'];
                                }else if($year1 > $vv['min'] && $year1 < $vv['max'] && $year2 > $vv['max']){
                                    $nianjia += ($vv['max'] - $year1 + 1 ) * $vv['days'];
                                }else if($year1 == $vv['max'] && $year2 == $vv['max']){
                                    $nianjia += $vv['days'];
                                }else if($year1 == $vv['max'] && $year2 > $vv['max']){
                                    $nianjia += $vv['days'];
                                }else if($year1 < $vv['min'] && $year2 == $vv['min']){
                                    $nianjia += $vv['days'];
                                }else if($year1 < $vv['min'] && $year2 > $vv['min'] && $year2 == $vv['max']){
                                    $nianjia += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                }else if($year1 < $vv['min'] && $year2 > $vv['min'] && $year2 < $vv['max']){
                                    $nianjia += ($year2 - $vv['min'] + 1 ) * $vv['days'];
                                }else if($year1 < $vv['min'] && $year2 > $vv['min'] && $year2 > $vv['max']){
                                    $nianjia += ($vv['max'] - $vv['min'] + 1 ) * $vv['days'];
                                }
                            }
                            $nianjia_total[$value['id']] = $nianjia;
                        }
                        
                    }
                    

                }
            }
        }
        //是否有奖励年假
        $days = prDates($start_time, $end_time);
        $year_in = array();
        foreach ($days as $d) {
            $year_in[date('Y', strtotime($d))] = date('Y', strtotime($d));
        }
        $reward = new \App\Models\Reward;
        $reward_nianjia_data = $reward->getRewardData(['year_in'=>$year_in]);//奖励
        $deduct_nianjia_data = $reward->getDeductData(['year_in'=>$year_in]);//扣减
        // dd($reward_nianjia_data);
        //拼接数组
        $items = array();
        foreach ($data['datalist'] as $k => $user) {
            $jiaqi_total = $jiaqi_time = array();
            foreach ($attendance_list as $key => $value) {
                if($value['type'] == 0 && isset($qingjia_list[$user['id']][$value['date']]) && is_array($qingjia_list[$user['id']][$value['date']])){
                    //工作日
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
            //统计每个人到现在的年假

            $items['user_id'] = $user['id'];
            $items['realname'] = $user['realname'];
            $items['dept'] = $user['dept'];
            $reward = $reward_nianjia_data[$user['id']] ?? 0;
            $deduct = $deduct_nianjia_data[$user['id']] ?? 0;
            $nianjia_sum = ($nianjia_total[$user['id']] ?? 0) + ($reward - $deduct);
            $items['nianjia'] = $nianjia_sum;
            $items['has_nianjia'] = $nianjia_sum;//剩余年假
            foreach ($type_data as $type_id => $val) {
                $items['jiaqi_'.$type_id] = $jiaqi_total[$user['id']][$type_id]['num'] ?? 0;
                if($val['if_nianjia'] == 1){
                    $items['has_nianjia'] = $items['has_nianjia'] - $items['jiaqi_'.$type_id];
                }
            }
            $fields = array_keys($items);
            $data['datalist'][$k] = $items;
        }
        if(!isset($fields)){
            $fields = array();
            $fields['user_id'] = 0;
            $fields['realname'] = '';
            $fields['dept'] = '';
            $fields['nianjia'] = 0;
            $fields['has_nianjia'] = 0;//剩余年假
            foreach ($type_data as $type_id => $val) {
                $fields['jiaqi_'.$type_id] = 0;
            }
            $fields = array_keys($fields);
        }
        $table_head = $this->table_head($fields,$type_data);
        $data['table_head'] = $table_head;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    //表头
    public function table_head($fields,$type_data){
        $table_head = ['realname'=>'姓名','dept'=>'部门','nianjia'=>'应有年假','has_nianjia'=>'剩余年假'];
        $tmp = [];
        foreach ($fields as $value) {
            if(substr($value, 0, 6) == 'jiaqi_'){
                $tmp[$value] = $type_data[substr($value,6)]['name'].'(天)';
            }
        }
        return array_merge($table_head, $tmp);
    }

    /**
    * 查看详情
    * @author molin 
    * @date 2018-11-26
    **/
    public function show(){
        $inputs = request()->all();
        $start_time = date('Y-01-01');
        $end_time = date('Y-m-d', time()-86400);//前一天为止
        if(isset($inputs['cur_month']) && $inputs['cur_month'] == 1){
            //获取本月书数据
            $start_time = date('Y-m-01');
            $end_time = date('Y-m-d', time()-86400);//前一天为止
        }else if(isset($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['start_time']) && !empty($inputs['end_time'])){
            $start_time = $inputs['start_time'];
            $end_time = $inputs['end_time'];
        }
        if(isset($inputs['user_id']) && !is_numeric($inputs['user_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数user_id']);
        }
        //请假详细信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $apply_ids = $apply_detail->where('type', 1)->whereBetween('date',[$start_time, $end_time])->where('user_id', $inputs['user_id'])
                    ->when(isset($inputs['type_id']) && !empty($inputs['type_id']), function ($query) use ($inputs){
                        return $query->where('leave_type', $inputs['type_id']);
                    })
                    ->when(isset($inputs['date']) && !empty($inputs['date']), function ($query) use ($inputs){
                        return $query->where('date', $inputs['date']);
                    })
                    ->pluck ('apply_id')->toArray();
        $apply = new \App\Models\ApplyAttendance;
        $apply_list = $apply->whereIn('id', $apply_ids)->select(['leave_type','start_time','end_time','remarks'])->get();
        $records_total = $apply->where('user_id', $inputs['user_id'])->count();
        $count = $apply->whereIn('id', $apply_ids)->select(['leave_type','start_time','end_time','remarks'])->count();
        //假期类型
        $holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
        }
        $data = array();
        $items = array();
        foreach ($apply_list as $key => $value) {
            $items[$key]['type'] = $type_data[$value->leave_type]['name'];
            $items[$key]['time'] = $value->start_time.'——'.$value->end_time;
            $items[$key]['remarks'] = $value->remarks;
        }
        $data['records_total'] = $records_total;
        $data['records_filtered'] = $count;
        $data['datalist'] = $items;
        $data['type_list'] = $type_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

}

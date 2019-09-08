<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HolidayYear extends Model
{
    //
    protected $table = 'holiday_years';

    public function storeData($inputs){
    	$set = new HolidayYear;
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$set = $set->where('id', $inputs['id'])->first();
    	}
    	$set->if_use = $inputs['if_use'];
    	if(!isset($inputs['id'])){
            $set->redate = $inputs['redate'];//不允许修改
        }
    	$set->days = $inputs['days'];
    	$set->days_setting = serialize($inputs['days_setting']);
    	return $set->save();
    }

    //获取个人应有年假
    public function getYearHoliday($user_id = 0, $start_time = '', $end_time = '', $deduct_id = 0){
        //假期类型
        $start_time = $start_time ? $start_time : date('Y-01-01');
        $end_time = $end_time ? $end_time : date('Y-m-d', time()-86400);//前一天为止
        $user = new \App\Models\User;
        $user_id = $user_id > 0 ? $user_id : auth()->user()->id;
        $user_info = $user->queryUserInfo(['user_id'=>$user_id]);

        $year_set = new \App\Models\HolidayYear;
        $set_info = $year_set->first();
        $nianjia_total = 0;
        if(!empty($set_info) && $set_info['if_use'] == 1){
            $days_setting = unserialize($set_info['days_setting']);
            if(isset($user_info->contracts->positive_date) && !empty($user_info->contracts->positive_date) && !empty($set_info['redate'])){
                $d = date('d', strtotime($user_info->contracts->positive_date));
                $positive_date = $user_info->contracts->positive_date;
                if($d <= 15){
                    $positive_date = date('Y-m-01', strtotime($positive_date));//当月的1号
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
                    $nianjia_total = 0;
                }else if(strtotime($end_time) > strtotime($positive_date) && strtotime($end_time) < strtotime($formal_year_date)){
                    //搜索结束时间大于转正时间 小于转正当年结算时间
                    $nianjia_total = 0;
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
                        $nianjia_total = $nianjia;
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
                    $nianjia_total = $nianjia;
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
                        $nianjia_total = $nianjia;
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
                        $nianjia_total = $nianjia;
                    }
                    
                }
            }
        }
        //是否有奖励/扣减年假
        $reward = new \App\Models\Reward;
        $reward_nianjia_num = $reward->where('year', date('Y'))->where('user_id', $user_id)->where('type',1)->sum('days');
        $deduct_nianjia_num = $reward->where('year', date('Y'))->where('user_id', $user_id)->where('type',2)
                            ->when($deduct_id > 0,function($query)use($deduct_id){
                                $query->where('id', '!=', $deduct_id);
                            })
                            ->sum('days');
        $nianjia_total = ($reward_nianjia_num - $deduct_nianjia_num) + $nianjia_total;
        return $nianjia_total;
    }
}

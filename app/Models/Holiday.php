<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    //节假日
    protected $table = 'holidays';

    //字段白名单
    protected $fillable = ['date', 'type', 'year'];


    //添加节假日 获取当年节假日
    public function addHolidays(){
    	set_time_limit(0);
    	$year = date('Y');//
    	// $pre_date = date('Y-m-d');
    	$pre_date = $year.'-01-01';
		$end_date = $year.'-12-31';//年底
		$days = prDates($pre_date, $end_date);
		$h_arr = $attend = array();
		foreach ($days as $key => $value) {
			$url = 'http://api.goseek.cn/Tools/holiday?date='.date('Ymd', strtotime($value));
			$resp = file_get_contents($url);
			$resp = json_decode($resp,true);
			if(!empty($resp)){
				$h_arr[$key]['date'] = $value;
				$h_arr[$key]['type'] = $resp['data'] ?? 0;
				$h_arr[$key]['year'] = $year;

				//考勤
				$attend[$key]['date'] = $value;
				$attend[$key]['year'] = $year;
				$attend[$key]['month'] = date('m', strtotime($value));
				$attend[$key]['day'] = date('d', strtotime($value));
				$attend[$key]['type'] = $resp['data'] ?? 0;
				$attend[$key]['created_at'] = date('Y-m-d H:i:s');
				$attend[$key]['updated_at'] = date('Y-m-d H:i:s');
			}
			
		}

		$att = new \App\Models\Attendance;
        $if_exits2 = $att->where('year', $year)->first();
        if(empty($if_exits2)){
    		//设置节假日  第一次保存时添加  把一年的节假日都存到数据库
    		$att->addHolidays($attend);//添加节假日
    	}
		return $this->insert($h_arr);

    }
}

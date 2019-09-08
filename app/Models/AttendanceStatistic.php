<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceStatistic extends Model
{
    //
    protected $table = 'attendance_statistics';

    // 获取用户
    public function hasUser()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    public function getStatisticList($inputs){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query =  $this->when(isset($inputs['year_month']) && !empty($inputs['year_month']), function ($query) use ($inputs){
                            $query->where('year_month', $inputs['year_month']);
                        })
        				->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function ($query) use ($inputs){
                            $query->whereHas('hasUser', function($query)use($inputs){
                            	$query->where('realname', 'like', '%'.$inputs['keywords'].'%');
                            });
                            
                        });
        $count = $where_query->count();
        $list = $where_query->orderBy('user_id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }
}

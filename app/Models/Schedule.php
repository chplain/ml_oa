<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    //日程表
    protected $table = 'schedules';


    public function storeData($inputs = array()){
    	$schedule = new Schedule;
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$schedule = $schedule->where('id', $inputs['id'])->first();
    	}else{
    		$schedule->user_id = auth()->user()->id;
    	}
    	$schedule->title = $inputs['title'];
    	$schedule->date = $inputs['date'];
    	$schedule->start_time = $inputs['start_time'];
    	$schedule->end_time = $inputs['end_time'];
    	$schedule->type = $inputs['type'];
    	$schedule->project_id = $inputs['project_id'] ?? 0;
    	$schedule->level = $inputs['level'];
    	$schedule->user_ids = isset($inputs['user_ids']) ? implode(',', $inputs['user_ids']) : '';
    	$schedule->content = $inputs['content'];
    	$schedule->if_ok = $inputs['if_ok'] ?? 0;
    	return $schedule->save();
    }

    public function getDataList($inputs = array()){
    	$query_where = $this->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query)use($inputs){
			    		$query->where('user_id', $inputs['user_id']);
			    	})
    				->when(isset($inputs['start_date']) && isset($inputs['end_date']), function($query)use($inputs){
    					$query->whereBetween('date', [$inputs['start_date'], $inputs['end_date']]);
    				});
    	return $query_where->get();
    }
}

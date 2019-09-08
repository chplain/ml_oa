<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPlan extends Model
{
    //日投递计划
    protected $table = 'project_plans';

    // 关联项目
    public function hasProject()
    {
        return $this->belongsTo('App\Models\BusinessProject', 'project_id', 'id');
    }

    public function storeData($inputs){
    	$plan = new ProjectPlan;
    	$plan->project_id = $inputs['project_id'];
    	$plan->date = $inputs['date'];
    	$plan->amount = $inputs['amount'];
    	return $plan->save();
    }

    public function getQueryData($inputs){
    	$query_where = $this->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query)use($inputs){
			    		$query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
			    	});
    	$list = $query_where->get();
    	return $list;
    }
}

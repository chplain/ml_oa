<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectGroup extends Model
{
    //组段
    protected $table = 'project_groups';

    public function storeData($inputs){
    	$group = new ProjectGroup;
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$group = $group->where('id', $inputs['id'])->first();
    	}else{
    		$group->user_id = auth()->user()->id;
    	}
    	$group->name = $inputs['name'];
    	$group->amount = $inputs['amount'];
    	return $group->save();
    }

    public function getDataList($inputs = array()){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
    	$where_query = $this->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function ($query) use ($inputs){
	    				$query->where('name', 'like', '%'.$inputs['keywords'].'%');
	    			})
    				->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs){
	    				$query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
	    			});
    	$count = $where_query->count();
    	$list = $where_query->orderBy('id', 'ASC')->skip($start)->take($length)->get();
    	return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class Reward extends Model
{
    //奖励表
    protected $table = 'rewards';

    //保存、更新
    public function storeData($inputs){
    	$reward = new Reward;
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$reward = $reward->where('id', $inputs['id'])->first();
    	}
    	$reward->user_id = $inputs['user_id'];
    	$reward->realname = $inputs['realname'];
    	$reward->dept_id = $inputs['dept_id'];
    	$reward->dept_name = $inputs['dept_name'];
    	$reward->year = $inputs['year'];
    	$reward->days = $inputs['days'];
        $reward->type = $inputs['type'];
        $reward->remarks = $inputs['remarks'] ?? '';
    	return $reward->save();
    }

    //获取列表数据
    public function getRewardList($inputs = []){
    	$where_query = $this->when(!empty($inputs['user_id']), function($query) use ($inputs){
                        return $query->where('user_id', $inputs['user_id']);
                    })
                    ->when(isset($inputs['type']) && is_numeric($inputs['type']), function($query) use ($inputs){
                        return $query->where('type', $inputs['type']);
                    })
    				->when(!empty($inputs['realname']), function($query) use ($inputs){
                        return $query->where('realname', 'like', '%'.$inputs['realname'].'%');
                    })
                    ->when(isset($inputs['year']) && is_numeric($inputs['year']), function($query) use ($inputs){
                        return $query->where('year', $inputs['year']);
                    });
    	$list = $where_query->orderBy('id', 'desc')->get();
        return $list;
    }

    //获取人员奖励
    public function getRewardData($inputs=[]){
    	$reward = new Reward;
    	$list = $reward->select(DB::raw('`user_id`, SUM(`days`) as days'))
                ->where('type', 1)
                ->when(isset($inputs['year_in']) && is_array($inputs['year_in']), function($query)use($inputs){
                    return $query->whereIn('year', $inputs['year_in']);
                })->when(isset($inputs['year_elt']) && !empty($inputs['year_elt']), function($query)use($inputs){
                    return $query->where('year', '<=', $inputs['year_elt']);
                })->groupBy('user_id')->get();
    	$data = array();
    	foreach ($list as $key => $value) {
    		$data[$value->user_id] = $value->days;
    	}
    	return $data;
    }

    //获取人员扣减
    public function getDeductData($inputs=[]){
        $reward = new Reward;
        $list = $reward->select(DB::raw('`user_id`, SUM(`days`) as days'))
                ->where('type', 2)
                ->when(isset($inputs['year_in']) && is_array($inputs['year_in']), function($query)use($inputs){
                    return $query->whereIn('year', $inputs['year_in']);
                })->when(isset($inputs['year_elt']) && !empty($inputs['year_elt']), function($query)use($inputs){
                    return $query->where('year', '<=', $inputs['year_elt']);
                })->groupBy('user_id')->get();
        $data = array();
        foreach ($list as $key => $value) {
            $data[$value->user_id] = $value->days;
        }
        return $data;
    }
}

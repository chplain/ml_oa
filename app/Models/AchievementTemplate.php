<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AchievementTemplate extends Model
{
    //
    protected $table = 'achievement_templates';
    
    //保存
    public function storeData($inputs){
    	$achie = new AchievementTemplate;
        if(isset($inputs['id']) && is_numeric($inputs['id'])){
            $achie = $achie->where('id', $inputs['id'])->first();
        }
    	$achie->name = $inputs['name'];
    	$achie->user_ids = implode(',', $inputs['user_ids']);
    	$achie->th = implode(',', $inputs['th']);
    	$achie->tbody = serialize($inputs['tbody']);
    	$achie->score_user_ids = serialize($inputs['score_user_ids']);
    	$achie->verify_user_ids = serialize($inputs['verify_user_ids']);
    	return $achie->save();
    }

    //删除
    public function destroyTpl($id){
    	return $this->destroy($id);
    }

    //列表
    public function getDataList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                    return $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                })
                ->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query) use ($inputs){
                    return $query->where('name', 'like', '%'.$inputs['keywords'].'%');
                });
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

}

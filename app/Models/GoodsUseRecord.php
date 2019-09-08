<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsUseRecord extends Model
{
    //
	protected $table = "goods_use_records";

    // 关联物品信息
    public function hasGoods()
    {
        return $this->belongsTo('App\Models\Goods', 'goods_id', 'id');
    }

	public function storeData($inputs, $detail_info){
        $user = new \App\Models\User;
        $user_info = $user->where('id', $inputs['user_id'])->select(['realname','dept_id'])->first();
    	$record = new GoodsUseRecord;
    	$record->number = $detail_info->number;
    	$record->name = $detail_info->name;
    	$record->goods_id = $detail_info->goods_id;
        $record->dept_id = $user_info->dept_id;
        $record->user_id = $inputs['user_id'];
    	$record->realname = $user_info->realname;
    	$record->start_time = date('Y-m-d H:i:s');
    	$record->add_user = auth()->user()->realname;
    	return $record->save();
	}

    public function getDataList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['realname']) && !empty($inputs['realname']), function($query) use ($inputs){
                    return $query->where('realname', 'like', '%'.$inputs['realname'].'%');
                })
                ->when(isset($inputs['keyword']) && !empty($inputs['keyword']), function($query) use ($inputs){
                    return $query->where('name', 'like', '%'.$inputs['keyword'].'%');
                })
                ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                    return $query->whereBetween('start_time', [$inputs['start_time'].' 00:00:00', $inputs['end_time']. ' 23:59:59']);
                })
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                    return $query->where('user_id', $inputs['user_id']);
                })
                ->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
                    return $query->where('status', $inputs['status']);
                })
                ->with(['hasGoods' => function($query){
                    return $query->select(['id','cate_id','name']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    public function getDataInfo($inputs){
        $where_query = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function($query) use ($inputs){
                    return $query->where('id', $inputs['id']);
                })
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                    return $query->where('user_id', $inputs['user_id']);
                })
                ->with(['hasGoods' => function($query){
                    return $query->select(['id','cate_id','name']);
                }]);
        return $where_query->first();
    }
}

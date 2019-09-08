<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Goods extends Model
{
    //物资管理
    protected $table = 'goods';

    // 获取部门信息
    public function hasCategory()
    {
        return $this->belongsTo('App\Models\GoodsCategory', 'cate_id', 'id');
    }

    // 获取资产物品信息
    public function hasDetail()
    {
        return $this->hasMany('App\Models\GoodsDetail', 'goods_id', 'id');
    }

    // 获取使用信息
    public function hasUses()
    {
        return $this->hasMany('App\Models\GoodsUseRecord', 'goods_id', 'id');
    }

    //保存数据
    public function storeData($inputs){
    	$goods = new Goods;
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$goods = $goods->where('id', $inputs['id'])->first();
    	}
    	$goods->cate_id = $inputs['cate_id'];
    	$goods->name = $inputs['name'];
    	$goods->type = $inputs['type'];
    	$goods->unit = $inputs['unit'];
    	if(!isset($inputs['id'])){
    		$goods->add_user = auth()->user()->id;
    	}
    	return $goods->save();
    }

    //获取数据列表
    public function getGoodsList($inputs){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['cate_id']) && is_numeric($inputs['cate_id']), function($query) use ($inputs){
                    return $query->where('cate_id', $inputs['cate_id']);
                })
                ->when(isset($inputs['type']) && is_numeric($inputs['type']), function($query) use ($inputs){
                    return $query->where('type', $inputs['type']);
                })
                ->when(isset($inputs['id']) && is_numeric($inputs['id']), function($query) use ($inputs){
                    return $query->where('id', $inputs['id']);
                })
                ->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
                    return $query->where('status', $inputs['status']);
                })
                ->when(isset($inputs['keyword']) && !empty($inputs['keyword']), function($query) use ($inputs){
                    return $query->where('name', 'like', '%'.$inputs['keyword'].'%');
                })
                ->with(['hasCategory' => function($query){
                    return $query->select(['id','name']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取数据列表
    public function getGoodsInfo($inputs){
        $where_query = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function($query) use ($inputs){
                    return $query->where('id', $inputs['id']);
                })
                ->with(['hasCategory' => function($query){
                    return $query->select(['id','name']);
                }])
                ->with(['hasDetail' => function($query){
                    return $query->select(['id','number','goods_id','name','status','created_at']);
                }])
                ->with(['hasUses' => function($query){
                    return $query->select(['id','number','goods_id','name','dept_id','user_id','start_time','end_time','add_user','status','remarks','created_at']);
                }]);
        $info = $where_query->first();
        return $info;
    }

    //
    public function getIdToData(){
        $list = $this->get();
        $id_name = $id_storage = $id_unit = array();
        foreach ($list as $key => $value) {
            $id_name[$value->id] = $value->name;
            $id_storage[$value->id] = $value->storage;
            $id_unit[$value->id] = $value->unit;
        }
        return ['id_name' => $id_name, 'id_storage' => $id_storage, 'id_unit' => $id_unit];

    }


}

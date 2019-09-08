<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsCategory extends Model
{
    //物品分类
    protected $table = 'goods_categorys';

    public function storeData($inputs){
    	$cate = new GoodsCategory;
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$cate = $cate->where('id', $inputs['id'])->first();
    	}
    	$cate->name = $inputs['name'];
    	$cate->parent_id = $inputs['parent_id'] ?? 0;
        $cate->status = $inputs['status'] ?? 1;
    	$cate->type = $inputs['type'];
    	return $cate->save();

    }

    //获取数据列表-含分页
    public function getQueryList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
            return $query->where('status', $inputs['status']);
        })
        ->when(isset($inputs['type']) && is_numeric($inputs['type']), function($query) use ($inputs){
            return $query->where('type', $inputs['type']);
        });
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取分类列表
    public function getCateList($inputs){
    	$query_where = $this->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
    		return $query->where('status', $inputs['status']);
    	})
    	->when(isset($inputs['type']) && is_numeric($inputs['type']), function($query) use ($inputs){
    		return $query->where('type', $inputs['type']);
    	});
    	$list = $query_where->select(['id','name'])->get();
    	return $list;
    }

    //获取分类列表
    public function getIdToData(){
        $list = $this->get();
        $items = array();
        foreach ($list as $key => $value) {
            $items[$value->id] = $value->name;
        }
        return $items;

    }

}

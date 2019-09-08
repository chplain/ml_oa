<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trade extends Model
{
    use SoftDeletes;
    //
    protected $table = 'trades';

    /*
    * 获取所有列表数据
    */
    public function getDataList($condition = array()){
    	$trade = new Trade;
    	$list = $trade->where($condition)->orderBy('id', 'ASC')->orderBy('sort', 'ASC')->get();
    	return $list;
    }
    /*
    * 保存数据
    */
    public function storeData($inputs){
    	$trade = new Trade;
        if (!empty($inputs['id']) && is_numeric($inputs['id'])) {
            $trade = $trade->where('id', $inputs['id'])->first();
        }
        $trade->name 		= $inputs['name'];
        $trade->parent_id 	= isset($inputs['parent_id']) ? $inputs['parent_id'] : 0;
        $trade->sort     	= isset($inputs['sort']) ? $inputs['sort'] : 0;
        return $trade->save();
    }	

    /*
    * 根据id更新字段
    */
    public function updateFields($id, $update){
        if (empty($id) || !is_numeric($id)) return false;
        $trade = $this->where('id', $id)->first();
        if(empty($trade)) return false;
        if(isset($update['parent_id']) && is_numeric($update['parent_id'])){
            $trade->parent_id = $update['parent_id'];//设置为一级行业
        }
        if(isset($update['ifuse']) && is_numeric($update['ifuse'])){
            $trade->ifuse = $update['ifuse'];//是否启用
        }
        if(isset($update['sort']) && is_numeric($update['sort'])){
            $trade->sort = $update['sort'];//排序
        }
        return $trade->save();
    }
    
}

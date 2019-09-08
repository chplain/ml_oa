<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GoodsCategoryController extends Controller
{
    //物资分类
    /**
    * 添加类型
    * @author molin 
    * @date 2018-10-17
    **/
    public function store(){
    	$inputs = request()->all();
    	$cate = new \App\Models\GoodsCategory;
    	$inputs['status'] = 1;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
    		$data = $cate->getCateList($inputs);
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'top'){
    		//保存数据--添加大类
	    	$rules = [
	            'name' => 'required|unique:goods_categorys',
	            'type' => 'required|integer'
	        ];
	        $attributes = [
	            'name' => '分类名称',
	            'type' => '资产类别'
	        ];
	        $validator = validator($inputs, $rules, [], $attributes);
	        if ($validator->fails()) {
	            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
	        }
	        $result = $cate->storeData($inputs);
	        if($result){
	        	$data = $cate->getCateList($inputs);
	        	systemLog('物资管理', '添加了资产类型['.$inputs['name'].']');
	        	return response()->json(['code' => 1, 'message' => '添加成功', 'data' => $data]);
	        }
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'second'){
    		//保存数据--添加小类
	    	$rules = [
	            'cate_id' => 'required|integer',
	            'name' => 'required|unique:goods_categorys|max:20',
	            'unit' => 'required|max:3'
	        ];
	        $attributes = [
	            'cate_id' => '分类id',
	            'name' => '分类名称',
	            'unit' => '单位'
	        ];
	        $validator = validator($inputs, $rules, [], $attributes);
	        if ($validator->fails()) {
	            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
	        }
	        $cate_info = $cate->where('id', $inputs['cate_id'])->select(['type'])->first();
	        $inputs['type'] = $cate_info->type;
	        $goods = new \App\Models\Goods;
	        $result = $goods->storeData($inputs);
	        if($result){
	        	systemLog('物资管理', '添加了资产类型['.$inputs['name'].']');
	        	return response()->json(['code' => 1, 'message' => '添加成功']);
	        }
    	}
    	
        return response()->json(['code' => 0, 'message' => '添加失败']);
    }

    /**
    * 资产类别-列表
    * @author molin 
    * @date 2018-10-17
    **/
    public function index(){
    	$inputs = request()->all();
    	$goods = new \App\Models\Goods;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'get'){
    		if(!isset($inputs['cate_id']) || !is_numeric($inputs['cate_id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数分类id=>cate_id']);
    		}
    		$goods_list = $goods->where('cate_id', $inputs['cate_id'])->select(['id', 'name'])->get();
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => ['goods_list' => $goods_list]]);
    	}
    	$inputs['status'] = 1;
    	$data = $goods->getGoodsList($inputs);
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		if($value->type == 1){
    			$items['type'] = '固定资产';
    		}else{
    			$items['type'] = '消耗品';
    		}
    		$items['cate'] = $value->hasCategory->name;
    		$items['name'] = $value->name;
    		$items['unit'] = $value->unit;
    		$data['datalist'][$key] = $items;
    	}
    	$cate = new \App\Models\GoodsCategory;
    	$data['cate_list'] = $cate->getCateList($inputs);
    	$data['type_list'] = [['id'=>1, 'name'=>'固定资产'],['id'=>2, 'name'=>'消耗品']];
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    public function delete(){
		$inputs = request()->all();
		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
			return response()->json(['code' => -1, 'message' => '缺少参数id']);
		}    	
		$goods = new \App\Models\Goods;
		$detail = new \App\Models\GoodsDetail;
		$storage = new \App\Models\GoodsStorageRecord;
		$record = new \App\Models\GoodsUseRecord;
		$apply = new \App\Models\ApplyPurchase;
		$if_use1 = $detail->where('goods_id', $inputs['id'])->first();
		$if_use2 = $storage->where('goods_id', $inputs['id'])->first();
		$if_use3 = $record->where('goods_id', $inputs['id'])->first();
		$if_use4 = $apply->where('goods_id', $inputs['id'])->first();
		if($if_use1 || $if_use2 || $if_use3 || $if_use4){
			return response()->json(['code' => 0, 'message' => '此类已使用，此类不能删除！']);
		}
		$goods_info = $goods->where('id', $inputs['id'])->select(['name'])->first();
		if(empty($goods_info)){
			return response()->json(['code' => 0, 'message' => '数据不存在']);
		}
		$res = $goods->where('id', $inputs['id'])->delete();
		if($res){
			unset($inputs['id']);
			$inputs['status'] = 1;
			$data = $goods->getGoodsList($inputs);
	    	$items = array();
	    	foreach ($data['datalist'] as $key => $value) {
	    		$items['id'] = $value->id;
	    		if($value->type == 1){
	    			$items['type'] = '固定资产';
	    		}else{
	    			$items['type'] = '消耗品';
	    		}
	    		$items['cate'] = $value->hasCategory->name;
	    		$items['name'] = $value->name;
	    		$items['unit'] = $value->unit;
	    		$data['datalist'][$key] = $items;
	    	}
	    	systemLog('物资管理', '删除了['.$goods_info->name.']');
			return response()->json(['code' => 1, 'message' => '删除成功', 'data' => $data]);
		}
		return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
    * 大类管理
    * @author molin 
    * @date 2019-01-09
    **/
    public function category(){
    	$inputs = request()->all();
    	$cate = new \App\Models\GoodsCategory;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'delete'){
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $cate->where('id', $inputs['id'])->first();
			if(empty($info)){
				return response()->json(['code' => 0, 'message' => '数据不存在']);
			}
			$goods = new \App\Models\Goods;
			$apply = new \App\Models\ApplyPurchase;
			$if_use1 = $goods->where('cate_id', $inputs['id'])->first();
			$if_use2 = $apply->where('cate_id', $inputs['id'])->first();
			if($if_use1 || $if_use2){
				return response()->json(['code' => 0, 'message' => '此类已使用，此类不能删除！']);
			}
			$res = $cate->where('id', $inputs['id'])->delete();
			if($res){
				systemLog('物资管理', '删除了['.$info->name.']');
				return response()->json(['code' => 1, 'message' => '删除成功']);
			}
    	}
    	$data = $cate->getQueryList($inputs);
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items[$key]['id'] = $value->id;
    		$items[$key]['type'] = $value->type == 1 ? '固定资产' : '消耗品';
    		$items[$key]['name'] = $value->name;
    		$items[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
    	}
    	$data['datalist'] = $items;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class GoodsController extends Controller
{
    //物资管理

    /**
    * 公司库存-消耗品
    * @author molin 
    * @date 2018-10-17
    **/
    public function consumable(){
    	$inputs = request()->all();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$goods = new \App\Models\Goods;
    		$goods_info = $goods->getGoodsInfo($inputs);
    		$data = $tmp = array();
    		$tmp['id'] = $goods_info->id;
    		$tmp['type'] = $goods_info->hasCategory->name;
    		$tmp['name'] = $goods_info->name;
    		$tmp['unit'] = $goods_info->unit;
    		$tmp['storage'] = $goods_info->storage;
    		$tmp['updated_at'] = $goods_info->updated_at->format('Y-m-d H:i:s');
    		$data['goods_info'] = $tmp;

            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $record = new \App\Models\GoodsStorageRecord;
            $record_list = $record->where('goods_id', $goods_info->id)->get();
            $tmp = array();
            foreach ($record_list as $key => $value) {
                if($value->type == 1){
                    $tmp[$key]['type'] = '普通修改';
                }else{
                    $tmp[$key]['type'] = '入库';
                }
                $tmp[$key]['realname'] = $user_data['id_realname'][$value->user_id];
                $tmp[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
                $tmp[$key]['pre_storage'] = $value->pre_storage;
                $tmp[$key]['cur_storage'] = $value->cur_storage;

            }
            $data['record_list'] = $tmp;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	$inputs['status'] = 1;//状态正常
    	$inputs['type'] = 2;//消耗品
    	$cate = new \App\Models\GoodsCategory;
    	$cate_list = $cate->getCateList($inputs); 
    	$goods = new \App\Models\Goods;
    	$data = $goods->getGoodsList($inputs);
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['type'] = $value->hasCategory->name;
    		$items['name'] = $value->name;
    		$items['unit'] = $value->unit;
    		$items['storage'] = $value->storage;
    		$data['datalist'][$key] = $items;
    	}
    	$data['cate_list'] = $cate_list;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
    * 公司库存-固定资产
    * @author molin 
    * @date 2018-10-17
    **/
    public function fixed(){
    	$inputs = request()->all();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$goods = new \App\Models\Goods;
    		$goods_info = $goods->getGoodsInfo($inputs);
    		$data = $tmp = array();
    		$tmp['id'] = $goods_info->id;
    		$tmp['type'] = $goods_info->hasCategory->name;
    		$tmp['name'] = $goods_info->name;
    		$tmp['unit'] = $goods_info->unit;
    		// $tmp['storage'] = $goods_info->storage;
    		$tmp['updated_at'] = $goods_info->updated_at->format('Y-m-d H:i:s');
    		$data['goods_info'] = $tmp;
            $dept = new \App\Models\Dept;
            $dept_data = $dept->getIdToData();
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $put_time = array();//可用
            if(!empty($goods_info->hasDetail)){
                foreach ($goods_info->hasDetail as $key => $value) {
                    $put_time[$value->number] = $value->created_at->format('Y-m-d H:i:s');
                }
            }

            $curr_lingyong = array();//当前领用
            $has_arr = array();
            if(!empty($goods_info->hasUses)){
                foreach ($goods_info->hasUses as $key => $value) {
                    if($value->status == 1 || $value->status == 2 || $value->status == 3){
                        //1正在使用、2申请归还中、3申请更换中
                        $tmp = array();
                        $tmp['id'] = $value->id;
                        $tmp['number'] = $value->number;
                        $tmp['dept'] = $dept_data['id_name'][$value->dept_id];
                        $tmp['user'] = $user_data['id_realname'][$value->user_id];
                        $tmp['start_time'] = $value->start_time;
                        $tmp['put_time'] = $put_time[$value->number] ?? '--';//入库时间
                        $has_arr[] = $value->number;
                        $curr_lingyong[] = $tmp;
                    }
                }
            }
            $data['curr_lingyong'] = $curr_lingyong;
            $keyong = array();//可用
            $keyong_num = 0;
            if(!empty($goods_info->hasDetail)){
                foreach ($goods_info->hasDetail as $key => $value) {
                    if(!in_array($value->number, $has_arr)){
                        //未使用
                        $tmp = array();
                        $tmp['id'] = $value->id;
                        $tmp['number'] = $value->number;
                        $tmp['created_at'] = $value->created_at->format('Y-m-d H:i:s');//入库时间
                        $keyong_num++;
                        $keyong[] = $tmp;
                    }
                }
            }
            $data['goods_info']['storage'] = $keyong_num;
            $data['keyong'] = $keyong;

            //人员选择
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select('id', 'realname')->get();
            $data['user_list'] = $user_list;

    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'fenpei'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            if(!isset($inputs['user_id']) || !is_numeric($inputs['user_id'])){
                return response()->json(['code' => -1, 'message' => '缺少使用人user_id']);
            }
            $detail = new \App\Models\GoodsDetail;
            $detail_info = $detail->where('id', $inputs['id'])->first();
            $record = new \App\Models\GoodsUseRecord;
            $if_exist = $record->where('number', $detail_info->number)->whereIn('status', [1,2,3])->select(['id'])->first();
            if($if_exist){
                return response()->json(['code' => 0, 'message' => '该物品已有人在使用']);
            }
            $result = $record->storeData($inputs, $detail_info);
            if($result){
                //分配之后减少库存
                $goods = new \App\Models\Goods;
                $goods_info = $goods->getGoodsInfo(['id'=>$detail_info->goods_id]);
                $goods_info->storage = $goods_info->storage - 1;
                $goods_info->save();
                $detail_info->use_status = 1;//使用中
                $detail_info->save();
                $user = new \App\Models\User;
                $user_data = $user->getIdToData();
                systemLog('物资管理', '分配了['.$detail_info->number.']['.$goods_info->name.']给'.$user_data['id_realname'][$inputs['user_id']]);
                return response()->json(['code' => 1, 'message' => '分配成功']);
            }
            return response()->json(['code' => 0, 'message' => '分配失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'delete'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $detail = new \App\Models\GoodsDetail;
            $detail_info = $detail->where('id', $inputs['id'])->first();
            $record = new \App\Models\GoodsUseRecord;
            $if_exist = $record->where('number', $detail_info->number)->select(['id'])->first();
            if($if_exist){
                return response()->json(['code' => 0, 'message' => '分配过的物资不能删除，会导致以前数据报错的哦(*^__^*)']);
            }
            $result = $detail->where('id', $inputs['id'])->delete();//删除
            if($result){
                //删除之后减少库存
                $goods = new \App\Models\Goods;
                $goods_info = $goods->getGoodsInfo(['id'=>$detail_info->goods_id]);
                $goods_info->storage = $goods_info->storage - 1;
                $goods_info->save();
                systemLog('物资管理', '删除了物资['.$detail_info->number.']['.$goods_info->name.']');
                return response()->json(['code' => 1, 'message' => '删除成功']);
            }
            return response()->json(['code' => 0, 'message' => '删除失败']);
        }
    	$inputs['status'] = 1;//状态正常
    	$inputs['type'] = 1;//固定资产
    	$cate = new \App\Models\GoodsCategory;
    	$cate_list = $cate->getCateList($inputs); 
    	$goods = new \App\Models\Goods;
    	$data = $goods->getGoodsList($inputs);
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['type'] = $value->hasCategory->name;
    		$items['name'] = $value->name;
    		$items['unit'] = $value->unit;
    		$items['storage'] = $value->storage;
    		$data['datalist'][$key] = $items;
    	}
    	$data['cate_list'] = $cate_list;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
    * 消耗品库存修改
    * @author molin 
    * @date 2018-10-30
    **/
    public function edit(){
        $inputs = request()->all();
        $rules = [
            'id' => 'required|integer',
            'number' => 'required|integer'
        ];
        $attributes = [
            'id' => '缺少id',
            'number' => '剩余库存number'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $goods = new \App\Models\Goods;
        $record = new \App\Models\GoodsStorageRecord;//修改记录
        $goods_info = $goods->where('id', $inputs['id'])->first();
        $record->pre_storage = $goods_info->storage;//修改前的库存
        $record->cur_storage = $inputs['number'];//修改后的库存
        $record->user_id = auth()->user()->id;//修改人
        $record->goods_id = $inputs['id'];//修改的物品
        $record->type = 1;//1为普通修改 2为入库

        $goods_info->storage = $inputs['number'];//修改
        $res = false;
        DB::transaction(function () use ($goods_info, $record) {
            $goods_info->save();
            $record->save();
        }, 5);
        $res = true;
        if($res){
            systemLog('物资管理', '修改了['.$goods_info->name.']库存');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '修改失败']);
    }


}

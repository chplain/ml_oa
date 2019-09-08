<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class GoodsUseRecordController extends Controller
{
    /**
    * 固定资产分配
    * @author molin 
    * @date 2018-10-30
    **/
    public function index(){
    	$inputs = request()->all();
    	$record = new \App\Models\GoodsUseRecord;
    	$data = $record->getDataList($inputs);
        $cate = new \App\Models\GoodsCategory;
        $cate_data = $cate->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'confirm'){
            //确认归还
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $record_info = $record->getDataInfo($inputs);
            if($record_info->status != 2){
                return response()->json(['code' => -1, 'message' => '此物品并未在申请归还中']);
            }
            $record_info->status = 4; //归还
            $record_info->end_time = date('Y-m-d H:i:s'); //归还时间
            
            $detail = new \App\Models\GoodsDetail;
            $detail_info = $detail->where('number', $record_info->number)->first();
            $detail_info->use_status = 0; //已归还
            
            $change = new \App\Models\GoodsUseChange;
            $change_info = $change->where('use_id', $record_info->id)->first();
            $change_info->status = 1;//已确认
            $change_info->verify_id = auth()->user()->id;//确认人
            $goods = new \App\Models\Goods;
            $goods_info = $goods->getGoodsInfo(['id'=>$detail_info->goods_id]);
            $goods_info->storage = $goods_info->storage + 1;
            DB::transaction(function () use($record_info,$detail_info,$change_info,$goods_info){
                $record_info->save();
                $detail_info->save();
                $change_info->save();
                $goods_info->save();
            }, 5);
            systemLog('物资管理', '确认归还物资['.$record_info->number.']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            //确认更换
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $record_info = $record->where('id', $inputs['id'])->select(['id','user_id','goods_id','status'])->first();
            if($record_info->status != 3){
                return response()->json(['code' => -1, 'message' => '此资产并未在申请更换中']);
            }
            $goods = new \App\Models\Goods;
            $goods_info = $goods->getGoodsInfo(['id'=>$record_info->goods_id]);
            $data = $tmp = array();
            $data['user_id'] = $record_info->user_id;
            $data['curr_lingyong_id'] = $inputs['id'];
            $tmp['id'] = $goods_info->id;
            $tmp['type'] = $goods_info->hasCategory->name;
            $tmp['name'] = $goods_info->name;
            $tmp['unit'] = $goods_info->unit;
            $tmp['storage'] = $goods_info->storage;
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
            if(!empty($goods_info->hasDetail)){
                foreach ($goods_info->hasDetail as $key => $value) {
                    if(!in_array($value->number, $has_arr)){
                        //未使用
                        $tmp = array();
                        $tmp['id'] = $value->id;
                        $tmp['number'] = $value->number;
                        $tmp['created_at'] = $value->created_at->format('Y-m-d H:i:s');//入库时间
                        $keyong[] = $tmp;
                    }
                }
            }
            $data['keyong'] = $keyong;
            
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'change'){
            //确认更换
            if(!isset($inputs['user_id']) || !is_numeric($inputs['user_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数user_id']);
            }
            if(!isset($inputs['curr_lingyong_id']) || !is_numeric($inputs['curr_lingyong_id'])){
                return response()->json(['code' => -1, 'message' => '当前使用物品id']);
            }
            if(!isset($inputs['keyong_id']) || !is_numeric($inputs['keyong_id'])){
                return response()->json(['code' => -1, 'message' => '更换物品id']);
            }
            $inputs['id'] = $inputs['curr_lingyong_id'];
            $record_info = $record->getDataInfo($inputs);
            if($record_info->status != 3){
                return response()->json(['code' => -1, 'message' => '此物品并未在申请更换中']);
            }
            $record_info->status = 5; //更换
            $record_info->end_time = date('Y-m-d H:i:s'); //更换时间
            
            $detail = new \App\Models\GoodsDetail;
            $detail_info = $detail->where('number', $record_info->number)->first();
            $detail_info->use_status = 0; //已归还
            
            $change = new \App\Models\GoodsUseChange;
            $change_info = $change->where('use_id', $record_info->id)->first();
            $change_info->status = 1;//已确认
            $change_info->verify_id = auth()->user()->id;//确认人

            //重新分配
            $redetail_info = $detail->where('id', $inputs['keyong_id'])->first();
            $if_exist = $record->where('number', $redetail_info->number)->whereIn('status', [1,2,3])->select(['id'])->first();
            if($if_exist){
                return response()->json(['code' => 0, 'message' => '该物品已有人在使用']);
            }

            DB::transaction(function () use($record_info,$detail_info,$change_info,$record,$inputs,$redetail_info){
                $record_info->save();
                $detail_info->save();
                $change_info->save();
                $record->storeData($inputs, $redetail_info);
            }, 5);
            systemLog('物资管理', '更换物资['.$record_info->number.'->'.$redetail_info->number.']');
            return response()->json(['code' => 1, 'message' => '更换成功']);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'cancel'){
            //取消【驳回】
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $record_info = $record->getDataInfo($inputs);
            DB::transaction(function () use($record_info){
                $record_info->status = 1; //改成正在使用
                $record_info->save();
                $change = new \App\Models\GoodsUseChange;
                $change->where('use_id', $record_info->id)->delete();//删除申请记录
                $detail = new \App\Models\GoodsDetail;
                $detail_info = $detail->where('number', $record_info->number)->first();
                $detail_info->use_status = 1; //正常使用
                $detail_info->save();
            }, 5);
            systemLog('物资管理', '驳回申请['.$record_info->number.']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'recall'){
            //撤回  把使用记录也一起消除
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $record_info = $record->getDataInfo($inputs);
            if($record_info->status != 1){
                return response()->json(['code' => -1, 'message' => '此物品并未在使用中']);
            }
            $result = $record->where('id', $inputs['id'])->delete();
            if($result){
                $goods = new \App\Models\Goods;
                $goods_info = $goods->getGoodsInfo(['id'=>$record_info->goods_id]);
                $goods_info->storage = $goods_info->storage + 1;
                $goods_info->save();
                $detail = new \App\Models\GoodsDetail;
                $detail_info = $detail->where('number', $record_info->number)->first();
                $detail_info->use_status = 0;
                $detail_info->save();
                systemLog('物资管理', '撤回了分配给'.$record_info->realname.'的物资['.$record_info->number.']['.$goods_info->name.']');
                return response()->json(['code' => 1, 'message' => '撤回成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
            
        }

    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            $items['realname'] = $value->realname;
            $items['cate'] = $cate_data[$value->hasGoods->cate_id];
            $items['name'] = $value->hasGoods->name;
    		$items['number'] = $value->number;
            $items['start_time'] = $value->start_time;
            $items['status'] = $value->status;
            if($value->status == 1){
                $items['if_recall'] = 1;
            }else{
                $items['if_recall'] = 0;
            }
    		$data['datalist'][$key] = $items;
    	}
        $data['status_list'] = [['id'=>1, 'name'=> '使用中'],['id'=>2, 'name'=> '申请归还中'],['id'=>3, 'name'=> '申请更换中'],['id'=>4, 'name'=> '已归还'],['id'=>5, 'name'=> '已更换']];//状态 1正在使用 2申请归还中 3申请更换中 4已归还 5已更换
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }


    /**
    * 我的物资
    * @author molin 
    * @date 2018-10-30
    **/
    public function mylist(){
        $inputs = request()->all();
        $inputs['user_id'] = auth()->user()->id;
        $record = new \App\Models\GoodsUseRecord;
        $cate = new \App\Models\GoodsCategory;
        $cate_data = $cate->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $record_info = $record->getDataInfo($inputs);
            $items = array();
            $items['id'] = $record_info->id;
            $items['type'] = '固定资产';
            $items['cate'] = $cate_data[$record_info->hasGoods->cate_id];
            $items['name'] = $record_info->name;
            $items['number'] = $record_info->number;
            $data['record_info'] = $items;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'change_submit'){
            //更换提交
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            if(!isset($inputs['remarks']) || empty($inputs['remarks'])){
                return response()->json(['code' => -1, 'message' => '请填写更换原因']);
            }
            $record_info = $record->getDataInfo($inputs);
            if($record_info->status != 1){
                return response()->json(['code' => 0, 'message' => '您已经申请过了']);
            }
            $inputs['number'] = $record_info->number;
            $inputs['type'] = 2; // 申请更换
            $change = new \App\Models\GoodsUseChange;
            $result = $change->storeData($inputs);
            if($result){
                //申请成功  修改物资使用状态
                $record_info->status = 3; //申请更换中
                $record_info->save();
                $detail = new \App\Models\GoodsDetail;
                $detail_info = $detail->where('number', $record_info->number)->first();
                $detail_info->use_status = 2; //申请归还中
                $detail_info->save();
                systemLog('物资管理', '提交更换申请['.$record_info->number.']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'return_submit'){
            //归还提交
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $record_info = $record->getDataInfo($inputs);
            if($record_info->status != 1){
                return response()->json(['code' => 0, 'message' => '您已经申请过了']);
            }
            $inputs['number'] = $record_info->number;
            $inputs['type'] = 1; // 申请归还
            $change = new \App\Models\GoodsUseChange;
            $result = $change->storeData($inputs);
            if($result){
                //申请成功  修改物资使用状态
                $record_info->status = 2; //申请归还中
                $record_info->save();
                $detail = new \App\Models\GoodsDetail;
                $detail_info = $detail->where('number', $record_info->number)->first();
                $detail_info->use_status = 2; //申请归还中
                $detail_info->save();
                systemLog('物资管理', '提交归还申请['.$record_info->number.']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        $data = $record->getDataList($inputs);
        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            $items['name'] = $value->hasGoods->name;
            $items['number'] = $value->number;
            $items['start_time'] = $value->start_time;
            $items['status'] = $value->status;
            $data['datalist'][$key] = $items;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

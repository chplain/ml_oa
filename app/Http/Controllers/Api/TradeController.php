<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Trade;

class TradeController extends Controller
{
    
    /**
     * EDM-行业列表
     * @Author: molin
     * @Date:   2018-09-17
     */
    public function index()
    {
    	$trade = new Trade;
    	$data = getTree($trade->getDataList(),0);
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * EDM-行业添加
     * @Author: molin
     * @Date:   2018-09-17
     */
    public function store()
    {
    	$inputs = request()->all();
    	$trade = new Trade;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
    		$trade_list = $trade->where('parent_id',0)->select(['id','name','parent_id'])->get();
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $trade_list]);
    	}
    	$messages = [
    		'name' => '行业名称',
    		'parent_id' => '父级id',
    		'sort' => '排序'
    	];
        $rules = [
            'name' => 'required|max:20|unique:trades',
            'parent_id' => 'numeric',
            'sort' => 'numeric',
        ];
    	$validator = validator($inputs, $rules, [], $messages);
    	if($validator->fails()){
            return response()->json(['code' => -1, 'message' => $validator->errors()->first(), 'data' => null]);
        }
    	
    	$result = $trade->storeData($inputs);
    	if ($result) {
            systemLog('EDM->行业设置', '添加了行业-'.$inputs['name']);
    		$list = getTree($trade->getDataList(),0);
            return response()->json(['code' => 1, 'message' => '操作成功', 'data' => $list]);
        }
    }

    /**
     * EDM-行业编辑
     * @Author: molin
     * @Date:   2018-09-17
     */
    public function update()
    {
    	$inputs = request()->all();
    	$trade = new Trade;
    	if(!isset($inputs['id']) || empty($inputs['id'])){
    		return response()->json(['code' => -1, 'message' => '缺少行业id', 'data' => null]);
    	}
    	$data = array();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//加载数据
    		$data['trade_list'] = $trade->where('parent_id', 0)->where('id', '<>', $inputs['id'])->select(['id','name','parent_id'])->get();
    		$data['trade_info'] = $trade->where('id', $inputs['id'])->select(['id','name','parent_id','sort'])->first();
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}

    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'setone'){
    		//设为一级
            if(!is_array($inputs['id'])){
                return response()->json(['code' => -1, 'message' => 'id必须为数组']);
            }
    		$res = $trade->whereIn('id', $inputs['id'])->update(['parent_id' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    		if($res){
                systemLog('EDM->行业设置', '设置行业-'.implode(',', $inputs['id']).'为一级行业');
    			$list = getTree($trade->getDataList(),0);
    			return response()->json(['code' => 1, 'message' => '设置成功', 'data' => $list]);
    		}
    		return response()->json(['code' => 0, 'message' => '设置失败', 'data' => null]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'move'){
    		//移动到。。。
            if(!is_array($inputs['id'])){
                return response()->json(['code' => -1, 'message' => 'id必须为数组']);
            }
    		if(!isset($inputs['parent_id']) || $inputs['parent_id'] == 0){
    			return response()->json(['code' => -1, 'message' => '缺少参数parent_id', 'data' => null]);
    		}
    		//先检查有没有子级
    		$if_child = $trade->whereIn('parent_id', $inputs['id'])->get()->toArray();
    		if(!empty($if_child)){
    			return response()->json(['code' => -1, 'message' => '该行业下面存在子级，不能移动', 'data' => null]);
    		}
    		$res = $trade->whereIn('id', $inputs['id'])->update(['parent_id' => $inputs['parent_id'], 'updated_at' => date('Y-m-d H:i:s')]);
    		if($res){
                systemLog('EDM->行业设置', '移动了行业-'.implode(',', $inputs['id']));
    			$list = getTree($trade->getDataList(),0);
    			return response()->json(['code' => 1, 'message' => '设置成功', 'data' => $list]);
    		}
    		return response()->json(['code' => 0, 'message' => '设置失败', 'data' => null]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'del'){
    		//是否启用
            if(!is_array($inputs['id'])){
                return response()->json(['code' => -1, 'message' => 'id必须为数组']);
            }
            
            //禁用时判断商务单是否已使用该行业
            $order = new \App\Models\BusinessOrder;
            $order_info = $order->whereIn('trade_id', $inputs['id'])->first();
            if(!empty($order_info)){
                return response()->json(['code' => 0, 'message' => '项目中已使用该行业，不能删除', 'data' => null]);
            }
    		$res = $trade->destroy($inputs['id']);
    		if($res){
                systemLog('EDM->行业设置', '删除了行业-'.implode(',', $inputs['id']));
    			$list = getTree($trade->getDataList(),0);
    			return response()->json(['code' => 1, 'message' => '设置成功', 'data' => $list]);
    		}
    		return response()->json(['code' => 0, 'message' => '设置失败', 'data' => null]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'sort'){
    		//排序
    		if(!isset($inputs['sort']) ||  !is_numeric($inputs['sort'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数sort', 'data' => null]);
    		}
    		$res = $trade->updateFields($inputs['id'], array('sort' => $inputs['sort']));
    		if($res){
    			$list = getTree($trade->getDataList(),0);
    			return response()->json(['code' => 1, 'message' => '设置成功', 'data' => $list]);
    		}
    		return response()->json(['code' => 0, 'message' => '设置失败', 'data' => null]);
    	}
    	$messages = [
    		'name' => '行业名称',
    		'parent_id' => '父级id',
    		'sort' => '排序'
    	];
        $rules = [
            'name' => 'required|max:20|unique:trades,name,'.$inputs['id'],
            'parent_id' => 'required|numeric',
            'sort' => 'numeric',
        ];
    	$validator = validator($inputs, $rules, [], $messages);
    	if($validator->fails()){
            return response()->json(['code' => -1, 'message' => $validator->errors()->first(), 'data' => null]);
        }
        if($inputs['id'] == $inputs['parent_id']){
        	return response()->json(['code' => -1, 'message' => '参数错误', 'data' => null]);
        }
    	$result = $trade->storeData($inputs);
    	if ($result) {
            systemLog('EDM->行业设置', '修改了行业-'.$inputs['id']);
    		$list = getTree($trade->getDataList(),0);
            return response()->json(['code' => 1, 'message' => '操作成功', 'data' => $list]);
        }


    }
}

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyAccessController extends Controller
{
    /*
    * 物品领用申请
    * @author molin
    * @date 2018-10-22
    */
    public function store(){
    	$inputs = request()->all();
        $access = new \App\Models\ApplyAccess;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'apply_access'){
    		$data = $tmp = array();
    		$user = new \App\Models\User;
    		$inputs['user_id'] = auth()->user()->id;
    		$user_info = $user->queryUserInfo($inputs);
    		$tmp['realname'] = $user_info->realname;
            $tmp['dept'] = $user_info->dept->name;
            $tmp['rank'] = $user_info->rank->name;
    		$data['user_info'] = $tmp;
    		$goods = new \App\Models\Goods;
    		$goods_list = $goods->where('status', 1)->where('type', 1)->select(['id', 'name', 'unit'])->get();
    		$data['goods_list'] = $goods_list;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['user_list'] = $user_list;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $access::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
		//保存数据
    	$rules = [
            'content' => 'required|array',
            'uses' => 'required|max:250'
        ];
        $attributes = [
            'content' => '领用物品',
            'uses' => '用途'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $access::type)->orderBy('id', 'desc')->first();//获取最新的配置
    	if(empty($setting_info)){
    		return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
    	}
        $steps = new \App\Models\AuditProcessStep;
        $step1 = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();
        if(empty($step1)){
            return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
        }
    	$setting_info['setting_content'] = unserialize($setting_info['setting_content']);
        $inputs['user_id'] = auth()->user()->id;
        $inputs['dept_id'] = auth()->user()->dept_id;
        $keywords = array();
        $goods = new \App\Models\Goods;
        $goods_data = $goods->getIdToData();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        foreach ($inputs['content'] as $key => $value) {
        	$response = $this->inputsChecked($value);
			if($response['code'] != 1){
				return response()->json($response);
			}
            $keywords[] = $goods_data['id_name'][$value['goods_id']] ?? $value['goods_id'];//可以是goods_id 可以是手动填写的文字
			$keywords[] = $user_data['id_realname'][$value['user_id']];
            $inputs['content'][$key]['unit'] = $goods_data['id_unit'][$value['goods_id']] ?? '--';
            $inputs['content'][$key]['status'] = '';
        }
        $inputs['keywords'] = implode(',', $keywords);
        $result = $access->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('物品领用申请', '提交了物品领用');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);

    }

    /*
    * 物品领用申请--人事预备
    * @author molin
    * @date 2018-10-23
    */
    public function personnel_store(){
    	$inputs = request()->all();

    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'apply_access'){
    		$data = $tmp = array();
    		$user = new \App\Models\User;
    		$inputs['user_id'] = auth()->user()->id;
    		$user_info = $user->queryUserInfo($inputs);
    		$tmp['realname'] = $user_info->realname;
            $tmp['dept'] = $user_info->dept->name;
            $tmp['rank'] = $user_info->rank->name;
    		$data['user_info'] = $tmp;
    		$dept = new \App\Models\Dept;
    		$dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
			$data['dept_list'] = $dept_list;
    		$goods = new \App\Models\Goods;
    		$goods_list = $goods->where('status', 1)->where('type', 1)->select(['id', 'name', 'unit'])->get();//固定资产
    		$data['goods_list'] = $goods_list;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['user_list'] = $user_list;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', 2)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
		//保存数据
    	$rules = [
            'content' => 'required|array',
            'dept_id' => 'required|integer',
            'uses' => 'required|max:250'
        ];
        $attributes = [
            'content' => '领用物品',
            'dept_id' => '部门',
            'uses' => '用途'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', 2)->orderBy('id', 'desc')->first();//获取最新的配置
    	if(empty($setting_info)){
    		return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
    	}
        $steps = new \App\Models\AuditProcessStep;
        $step1 = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();
        if(empty($step1)){
            return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
        }
    	$setting_info['setting_content'] = unserialize($setting_info['setting_content']);
        $inputs['user_id'] = auth()->user()->id;
        $keywords = array();
        $goods = new \App\Models\Goods;
        $goods_data = $goods->getIdToData();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        foreach ($inputs['content'] as $key => $value) {
        	$response = $this->inputsChecked($value);
			if($response['code'] != 1){
				return response()->json($response);
			}
			$keywords[] = $goods_data['id_name'][$value['goods_id']] ?? $value['goods_id'];
            $keywords[] = $user_data['id_realname'][$value['user_id']];
            $inputs['content'][$key]['unit'] = $goods_data['id_unit'][$value['goods_id']] ?? '--';
            $inputs['content'][$key]['status'] = '';
        }
        $inputs['keywords'] = implode(',', $keywords);
        $inputs['if_personnel'] = 1;//人事预备
        $access = new \App\Models\ApplyAccess;
        $result = $access->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('物品领用申请', '提交了物品领用');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);

    }

    /** 
    *  领用物品内容
    *  @author molin
    *	@date 2018-10-22
    */
    public function inputsChecked($value){
		//
		if(!isset($value['goods_id']) || empty($value['goods_id'])){
    		return $response = ['code' => -1, 'message' => '缺少物品goods_id'];
    	}
    	if(!isset($value['num']) || !is_numeric($value['num'])){
    		return $response = ['code' => -1, 'message' => '缺少数量num'];
    	}
        if(!isset($value['user_id']) || !is_numeric($value['user_id'])){
            return $response = ['code' => -1, 'message' => '缺少使用人user_id'];
        }
    	return $response = ['code' => 1, 'message' => '验证通过'];
    }

    /*
    * 领用申请-详情
    * @author molin
    * @date 2018-10-19
    */
    public function show(){
    	$inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $data = array();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            //保存数据
            $rules = [
                'key' => 'required|integer'
            ];
            $attributes = [
                'key' => 'key'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $apply_access = new \App\Models\ApplyAccess;
            $access_info = $apply_access->where('id', $inputs['id'])->first();
            $content = unserialize($access_info->content);
            $goods_id = $content[$inputs['key']]['goods_id'];
            $detail = new \App\Models\GoodsDetail;
            $detail_list = $detail->where('goods_id', $goods_id)->where('use_status', 0)->select(['id','number'])->get();
            if(empty($detail_list->toArray())){
                return response()->json(['code' => 0, 'message' => '没有可以分配的物资，请先采购']);
            }
            $data['id'] = $inputs['id'];
            $data['detail_list'] = $detail_list;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
        
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'fenpei'){
            //分配
            if(!isset($inputs['key']) || !is_numeric($inputs['key'])){
                return response()->json(['code' => -1, 'message' => '缺少参数key']);
            }
            //保存数据
            $rules = [
                'key' => 'required|integer',
                'number_ids' => 'required|array'
            ];
            $attributes = [
                'key' => 'key',
                'number_ids' => '物品编号id'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $detail = new \App\Models\GoodsDetail;
            $detail_arr = $detail->whereIn('id', $inputs['number_ids'])->select(['id','number','use_status','name'])->get();

            $apply_access = new \App\Models\ApplyAccess;
            $access_info = $apply_access->where('id', $inputs['id'])->first();
            $content = unserialize($access_info->content);
            $user_id = $content[$inputs['key']]['user_id'];
            $goods_id = $content[$inputs['key']]['goods_id'];
            $num = $content[$inputs['key']]['num'];//数量
            if(count($inputs['number_ids']) > $num){
                return response()->json(['code' => 0, 'message' => '分配数量不能大于申请数量']);
            }
            $goods = new \App\Models\Goods;
            $goods_data = $goods->getIdToData();
            $user = new \App\Models\User;
            $user_info = $user->where('id', $user_id)->select(['realname','dept_id'])->first();
            $record = new \App\Models\GoodsUseRecord;
            $items = $log = array();
            $num = 0;
            foreach ($detail_arr as $key => $value) {
                if($value->use_status != 0){
                    return response()->json(['code' => 0, 'message' => '该物资已被占用，请选择其它物资']);
                }
                $tmp = array();
                $tmp['number'] = $value->number;
                $tmp['name'] = $goods_data['id_name'][$goods_id] ?? $goods_id;
                $tmp['goods_id'] = $goods_id;
                $tmp['dept_id'] = $user_info->dept_id;
                $tmp['user_id'] = $user_id;
                $tmp['realname'] = $user_info->realname;
                $tmp['start_time'] = date('Y-m-d H:i:s');
                $tmp['add_user'] = auth()->user()->realname;
                $tmp['created_at'] = date('Y-m-d H:i:s');
                $tmp['updated_at'] = date('Y-m-d H:i:s');
                $num++;//分配数量
                $items[] = $tmp;
                $log[] = $value->number.'['.$value->name.']';
            }
            // dd($items);
            $res = $record->insert($items);
            if($res){
                $content[$inputs['key']]['status'] = 1; //分配成功
                $access_info->content = serialize($content);
                $access_info->save();//更新状态
                $detail = $detail->whereIn('id', $inputs['number_ids'])->update(['use_status'=>1, 'updated_at'=>date('Y-m-d H:i:s')]);
                //减去库存
                $goods = new \App\Models\Goods;
                $goods_info = $goods->getGoodsInfo(['id'=>$goods_id]);
                $goods_info->storage = $goods_info->storage - $num;
                $goods_info->save();
                systemLog('物资管理', '分配了物资['.implode(',', $log).']给'.$user_info->realname);
                return response()->json(['code' => 1, 'message' => '分配成功']);
            }
            return response()->json(['code' => 0, 'message' => '分配失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'reject'){
            //驳回
            if(!isset($inputs['key']) || !is_numeric($inputs['key'])){
                return response()->json(['code' => -1, 'message' => '缺少参数key']);
            }
            //保存数据
            $rules = [
                'key' => 'required|integer'
            ];
            $attributes = [
                'key' => 'key'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $apply_access = new \App\Models\ApplyAccess;
            $access_info = $apply_access->where('id', $inputs['id'])->first();
            $content = unserialize($access_info->content);
            $content[$inputs['key']]['status'] = 2; //驳回
            $access_info->content = serialize($content);
            $res = $access_info->save();//更新状态
            if($res){
                $user = new \App\Models\User;
                $user_data = $user->getIdToData();
                systemLog('物资管理', '驳回了物资分配['.$user_data['id_realname'][$content[$inputs['key']]['user_id']].']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作成功']);

        }


    	$inputs['apply_id'] = $inputs['id'];
    	unset($inputs['id']);
    	$audit_proces =new \App\Models\AuditProces;
    	$proces_info = $audit_proces->getAccessInfo($inputs);
    	if(empty($proces_info)){
    		return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
    	}
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();

    	$cate = new \App\Models\GoodsCategory;
    	$cate_data = $cate->getIdToData();

    	$goods = new \App\Models\Goods;
    	$goods_data = $goods->getIdToData();

    	//拼接数据
		$data = $user_info = $goods_info = array();
		//领用部门、申请人信息
    	$user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
		$user_info['dept'] = $proces_info->hasDept->name;
		$user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
		$data['user_info'] = $user_info;
// dd($proces_info);
		$goods_info['created_at'] = $proces_info->applyAccess->created_at->format('Y-m-d H:i:s');
		if($proces_info->applyAccess->if_personnel == 1){
			$goods_info['type'] = '人事预备申请';
		}else{
			$goods_info['type'] = '普通申请';
		}
        $goods_info['status'] = $proces_info->applyAccess->status;
		$goods_info['status_txt'] = $proces_info->applyAccess->status_txt;
		$content = unserialize($proces_info->applyAccess->content);
		$tmp = array();
		foreach ($content as $k => $val) {
            $tmp[$k]['id'] = $proces_info->applyAccess->id;
            $tmp[$k]['key'] = $k;
            $tmp[$k]['goods_id'] = $val['goods_id'];
			$tmp[$k]['goods_name'] = $goods_data['id_name'][$val['goods_id']] ?? $val['goods_id'];
			$tmp[$k]['unit'] = $val['unit'];
            $tmp[$k]['num'] = $val['num'];
            $tmp[$k]['user_id'] = $val['user_id'];
            $tmp[$k]['realname'] = $user_data['id_realname'][$val['user_id']];
            $tmp[$k]['status'] = $val['status'] == '' ? 0 : $val['status'];
            if($val['status'] == 1){
                $tmp[$k]['status_txt'] = '已分配';
            }else if($val['status'] == 2){
                $tmp[$k]['status_txt'] = '已驳回';
            }else{
                $tmp[$k]['status_txt'] = '未分配';
            }
		}
		$goods_info['goods'] = $tmp;
		$goods_info['uses'] = $proces_info->applyAccess->uses;
		$data['goods_info'] = $goods_info;

		//加载已经审核的人的评价
    	$pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), 2);
    	$data['pre_audit_opinion'] = $audit_opinions = array();
    	if(!empty($pre_verify_users_data)){
    		foreach ($pre_verify_users_data as $key => $value) {
    			$audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
    			$audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
    		}
    		$data['pre_audit_opinion'] = $audit_opinions;
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /*
    * 物品领用申请-汇总
    * @author molin
    * @date 2018-10-23
    */
    public function index(){
    	$inputs = request()->all();

    	$applyAccess = new \App\Models\ApplyAccess;
        $access_list = $applyAccess->getDataList($inputs);
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$cate = new \App\Models\GoodsCategory;
    	$cate_data = $cate->getIdToData();
    	$goods = new \App\Models\Goods;
    	$goods_data = $goods->getIdToData();

    	$data = array();
    	foreach ($access_list['datalist'] as $key => $value) {
            $items = array();
    		$items['id'] = $value->id;
    		if($value->if_personnel == 1){
    			$items['type'] = '人事预备申请';
    		}else{
    			$items['type'] = '普通申请';
    		}
    		$items['realname'] = $user_data['id_realname'][$value->user_id];
    		$items['dept'] = $value->hasDept->name;
    		$content = unserialize($value->content);
    		$tmp = array();
    		foreach ($content as $val) {
                if(isset($goods_data['id_name'][$val['goods_id']])){
                    $tmp[] = $goods_data['id_name'][$val['goods_id']].'x'.$val['num'];
                }else{
                    $tmp[] = $val['goods_id'].'x'.$val['num'];
                }
    			
    		}
    		$items['content'] = implode(',', $tmp);
    		$items['uses'] = $value->uses;
    		$items['created_at'] = $value->created_at->format('Y-m-d H:i:s'); 
    		$items['status_txt'] = $value->status_txt;
    		$access_list['datalist'][$key]  = $items;
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $access_list]);
    }

    /*
    * 我的物品领用申请-
    * @author molin
    * @date 2018-10-22
    */
    public function mylist(){
    	$inputs = request()->all();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//查看详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$inputs['apply_id'] = $inputs['id'];
	    	unset($inputs['id']);
	    	$audit_proces =new \App\Models\AuditProces;
	    	$proces_info = $audit_proces->getAccessInfo($inputs);
	    	if(empty($proces_info)){
	    		return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
	    	}
	    	$user = new \App\Models\User;
	    	$user_data = $user->getIdToData();

	    	$cate = new \App\Models\GoodsCategory;
	    	$cate_data = $cate->getIdToData();

	    	$goods = new \App\Models\Goods;
	    	$goods_data = $goods->getIdToData();

	    	//拼接数据
			$data = $user_info = $goods_info = array();
			//领用部门、申请人信息
	    	$user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
			$user_info['dept'] = $proces_info->hasDept->name;
			$user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
			$data['user_info'] = $user_info;
			$goods_info['created_at'] = $proces_info->applyAccess->created_at->format('Y-m-d H:i:s');
			$goods_info['status_txt'] = $proces_info->applyAccess->status_txt;
			$content = unserialize($proces_info->applyAccess->content);
			$tmp = array();
			foreach ($content as $k => $val) {
				$tmp[$k]['goods_name'] = $goods_data['id_name'][$val['goods_id']] ?? $val['goods_id'];
				$tmp[$k]['unit'] = $val['unit'];
                $tmp[$k]['num'] = $val['num'];
                $tmp[$k]['realname'] = $user_data['id_realname'][$val['user_id']];
                if($val['status'] == 1){
                    $tmp[$k]['status'] = '已分配';
                }else{
                    $tmp[$k]['status'] = '未分配';
                }
			}
			$goods_info['goods'] = $tmp;
			$goods_info['uses'] = $proces_info->applyAccess->uses;
			$data['goods_info'] = $goods_info;

			//加载已经审核的人的评价
	    	$pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), 2);
	    	$data['pre_audit_opinion'] = $audit_opinions = array();
	    	if(!empty($pre_verify_users_data)){
	    		foreach ($pre_verify_users_data as $key => $value) {
	    			$audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
	    			$audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
	    		}
	    		$data['pre_audit_opinion'] = $audit_opinions;
	    	}
	    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit'){
    		//修改 加载
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$access = new \App\Models\ApplyAccess;
    		$apply_info = $access->where('id', $inputs['id'])->first();
    		$data = $tmp = array();
    		$data['id'] = $apply_info->id;
    		$user = new \App\Models\User;
    		$inputs['user_id'] = auth()->user()->id;
    		$user_info = $user->queryUserInfo($inputs);
    		$tmp['realname'] = $user_info->realname;
            $tmp['dept'] = $user_info->dept->name;
            $tmp['rank'] = $user_info->rank->name;
    		$data['user_info'] = $tmp;
    		$goods = new \App\Models\Goods;
    		$goods_data = $goods->getIdToData();
    		$goods_list = $goods->where('status', 1)->where('type', 1)->select(['id', 'name', 'unit'])->get();
    		$data['goods_list'] = $goods_list;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['user_list'] = $user_list;
    		$content = unserialize($apply_info->content);
    		foreach ($content as $key => $value) {
    			$content[$key]['unit'] = $value['unit'];
                unset($content[$key]['status']);
    		}
    		$data['goods'] = $content;
    		$data['uses'] = $apply_info->uses;
	    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'submit'){
    		//修改提交  
	    	$rules = [
	    		'id' => 'required|integer',
	            'content' => 'required|array',
	            'uses' => 'required|max:250'
	        ];
	        $attributes = [
	        	'id' => '缺少id',
	            'content' => '领用物品',
	            'uses' => '用途'
	        ];
	    	$validator = validator($inputs, $rules, [], $attributes);
	        if ($validator->fails()) {
	            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
	        }
	        $access = new \App\Models\ApplyAccess;
    		$apply_info = $access->where('id', $inputs['id'])->first();
	        $keywords = array();
	        $goods = new \App\Models\Goods;
    		$goods_data = $goods->getIdToData();
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
    		foreach ($inputs['content'] as $key => $value) {
	        	$response = $this->inputsChecked($value);
				if($response['code'] != 1){
					return response()->json($response);
				}
				$keywords[] = $goods_data['id_name'][$value['goods_id']] ?? $value['goods_id'];
                $keywords[] = $user_data['id_realname'][$value['user_id']];
                $inputs['content'][$key]['unit'] = $goods_data['id_unit'][$value['goods_id']] ?? '--';
                $inputs['content'][$key]['status'] = '';

	        }
	        $apply_info->keywords = implode(',', $keywords);
	        $apply_info->content = serialize($inputs['content']);
	        $apply_info->uses = $inputs['uses'];
	        $res = $apply_info->save();
    		if($res){
                systemLog('物资管理', '编辑了领用申请');
    			return response()->json(['code' => 1, 'message' => '操作成功']);
    		}
    		return response()->json(['code' => 0, 'message' => '操作失败']);

    	}
    	$inputs['user_id'] = auth()->user()->id;
    	$inputs['if_personnel'] = 0;
    	$applyAccess = new \App\Models\ApplyAccess;
        $data = $applyAccess->getDataList($inputs);
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$cate = new \App\Models\GoodsCategory;
    	$cate_data = $cate->getIdToData();
    	$goods = new \App\Models\Goods;
    	$goods_data = $goods->getIdToData();

    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['realname'] = $user_data['id_realname'][$value->user_id];
    		$items['dept'] = $value->hasDept->name;
    		$content = unserialize($value->content);
    		$tmp = array();
    		foreach ($content as $val) {
                if(isset($goods_data['id_name'][$val['goods_id']])){
                    $tmp[] = $goods_data['id_name'][$val['goods_id']].'x'.$val['num'];
                }else{
                    $tmp[] = $val['goods_id'].'x'.$val['num'];
                }
    		}
    		$items['content'] = implode(',', $tmp);
    		$items['uses'] = $value->uses;
    		$items['created_at'] = $value->created_at->format('Y-m-d H:i:s'); 
    		$items['status_txt'] = $value->status_txt;
    		$items['if_edit'] = $value->if_edit;//是否可以修改  当有人审核之后 该字段改为1
    		$data['datalist'][$key]  = $items;
    	}

		$status = [[1=>'审核中'],[2=>'已通过'],[3=>'已驳回']];
		$data['search_status'] = $status;
    	
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProjectGroupController extends Controller
{
    //组段
    /**
    *  组段列表
    *  @author molin
    *   @date 2019-02-12
    **/
    public function index(){
    	$inputs = request()->all();
    	$group = new \App\Models\ProjectGroup;
    	$data = $group->getDataList($inputs);
    	$items = array();
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	foreach ($data['datalist'] as $key => $value) {
    		$items[$key]['id'] = $value->id;
    		$items[$key]['name'] = $value->name;
    		$items[$key]['amount'] = $value->amount;
    		$items[$key]['adduser'] = $user_data['id_realname'][$value->user_id];
    		$items[$key]['addtime'] = $value->created_at->format('Y-m-d');
    	}
    	$data['datalist'] = $items;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
    *  组段添加
    *  @author molin
    *   @date 2019-02-12
    **/
    public function store(){
    	$inputs = request()->all();
    	$group = new \App\Models\ProjectGroup;
    	//保存数据
        $rules = [
            'name' => 'required|max:50|unique:project_groups,name',
            'amount' => 'required|numeric'

        ];
        $attributes = [
            'name' => '组段名称',
            'amount' => '组段量'
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $result = $group->storeData($inputs);
        if($result){
            systemLog('项目汇总', '添加了组段'.$inputs['name']);
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
    *  组段编辑
    *  @author molin
    *   @date 2019-02-12
    **/
    public function update(){
    	$inputs = request()->all();
    	$group = new \App\Models\ProjectGroup;
    	$data = array();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$info = $group->where('id', $inputs['id'])->select(['id','name','amount'])->first();
    		$data['group_info'] = $info;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	//保存数据
        $rules = [
            'id' => 'required|integer',
            'name' => 'required|max:50|unique:project_groups,name,'.$inputs['id'],
            'amount' => 'required|numeric'

        ];
        $attributes = [
            'id' => 'id',
            'name' => '组段名称',
            'amount' => '组段量'
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $result = $group->storeData($inputs);
        if($result){
            systemLog('项目汇总', '编辑了组段['.$inputs['id'].'-'.$inputs['name'].']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
    *  组段删除
    *  @author molin
    *   @date 2019-02-12
    **/
    public function delete(){
    	$inputs = request()->all();
    	$group = new \App\Models\ProjectGroup;
    	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数id']);
    	}
    	$project = new \App\Models\BusinessProject;
    	$if_use = $project->where('group_id', $inputs['id'])->first();
    	if(!empty($if_use)){
    		return response()->json(['code' => 0, 'message' => '非法操作,该组段下存在项目']);
    	}
    	$result = $group->where('id', $inputs['id'])->delete();
        if($result){
            systemLog('项目汇总', '编辑了组段['.$inputs['id'].'-'.$inputs['name'].']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

}

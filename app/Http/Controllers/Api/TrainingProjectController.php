<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class TrainingProjectController extends Controller
{
    //培训项目管理
    /** 
    *  培训项目列表
    *  @author molin
    *	@date 2018-10-09
    */
    public function index(){
    	$inputs = request()->all();
    	$training = new \App\Models\TrainingProject;
        $inputs['status'] = 1;
    	$list = $training->getDataList($inputs);
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	foreach ($list['datalist'] as $key => $value) {
    		$list['datalist'][$key]['explain_people'] = $user_data['id_realname'][$value->explain_people];
    		$list['datalist'][$key]['supervision_people'] = $user_data['id_realname'][$value->supervision_people];
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

    /** 
    *  培训项目-添加
    *  @author molin
    *	@date 2018-10-09
    */
    public function store(){
    	$inputs = request()->all();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'training'){
    		//加载
    		$user = new \App\Models\User;
    		$user_list = $user->where('status', 1)->select(['id','username','realname'])->get();
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => ['user_list' => $user_list]]);

    	}
    	//保存
    	$rules = [
            'name' => 'required',
            'explain_people' => 'required|integer',
            'supervision_people' => 'required|integer',
            'time' => 'required|integer',
        ];
        $attributes = [
            'name' => '培训项目名称',
            'explain_people' => '讲解人',
            'supervision_people' => '监督人',
            'time' => '预计时间'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }

    	$training = new \App\Models\TrainingProject;
    	$result = $training->storeData($inputs);
    	if($result){
            systemLog('培训管理', '添加了培训项目['.$inputs['name'].']');
    		return response()->json(['code' => 1, 'message' => '添加成功']);
    	}
    	return response()->json(['code' => 0, 'message' => '添加失败']);
    }

    /** 
    *  培训项目-修改
    *  @author molin
    *	@date 2018-10-09
    */
    public function update(){
    	$inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'training'){
            //加载
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $user_list = $user->select(['id','username','realname'])->get();
            $data = array();
            $data['user_list'] = $user_list;
            $training = new \App\Models\TrainingProject;
            $training_info = $training->where('id', $inputs['id'])->first();
            $training_info->training_doc_path = !empty($training_info->training_doc) ? asset($training_info->training_doc) : '';
            $training_info->test_doc_path = !empty($training_info->test_doc) ? asset($training_info->test_doc) : '';
            $data['training_info'] = $training_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
    	$rules = [
            'id' => 'required|integer',
            'name' => 'required',
            'explain_people' => 'required|integer',
            'supervision_people' => 'required|integer',
            'time' => 'required|integer',
        ];
        $attributes = [
            'id' => '缺少参数id',
            'name' => '培训项目名称',
            'explain_people' => '讲解人',
            'supervision_people' => '监督人',
            'time' => '预计时间'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }

    	$training = new \App\Models\TrainingProject;
    	$result = $training->storeData($inputs);
    	if($result){
    		return response()->json(['code' => 1, 'message' => '修改成功']);
    	}
    	return response()->json(['code' => 0, 'message' => '修改成功']);
    }


    /**
     * 上传
     * @Author: molin
     * @Date:   2018-10-09
     */
    public function upload()
    {
        $upload_file = \request()->file('file');
        if(!empty($upload_file)){
            $ext = $upload_file->getClientOriginalExtension();//扩展名
            $type_arr = ['jpg','png','gif','jpeg','doc','docx','xls','xlsx','ppt','pptx','csv','pdf'];
            if(!in_array($ext, $type_arr)){
                return response()->json(['code' => -1, 'message' => '只能上传office文件、图片或pdf文件！！', 'data' => ['type' => $ext]]);
            }
            $size = $upload_file->getSize();//文件大小  限制大小10M
            if($size > 10485760){
                return response()->json(['code' => -1, 'message' => '只能上传10M以内的文件！！', 'data' => null]);
            }
            //重命名
            $fileName = date('YmdHis').uniqid().'.'.$ext;
            $upload_file->move(storage_path('app/public/uploads/training/'), $fileName);
            $data = array();
            $data['file'] = '/storage/uploads/training/'.$fileName;
            $data['file_path'] = asset('/storage/uploads/training/'.$fileName);

            return response()->json(['code' => 1, 'message' => '上传成功', 'data' => $data]);
        }else{
            return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
        }
    }

    /** 
    *  培训项目-删除
    *  @author molin
    *   @date 2018-12-19
    */
    public function delete(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        
        $training = new \App\Models\TrainingProject;
        $training = $training->where('id', $inputs['id'])->first();
        $training->status = 0;//禁用
        $result = $training->save();
        if($result){
            return response()->json(['code' => 1, 'message' => '删除成功']);
        }
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyRecruitController extends Controller
{
    
    /*
    * 招聘申请
    * @author molin
    * @date 2018-09-30
    */

    public function store(){
    	$inputs = request()->all();
        $applyRecruit = new \App\Models\ApplyRecruit;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'apply_recruit'){
    		$data = array();
            $dept = new \App\Models\Dept;
            $dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
            $data['dept_list'] = $dept_list;
            $positions = new \App\Models\Position;
            $positions_list = $positions->select(['id', 'name'])->get();
            $data['positions_list'] = $positions_list;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $applyRecruit::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
		//保存数据
    	$rules = [
            'number' => 'required|integer',
            'positions_id' => 'required|integer',
            'dept_id' => 'required|integer',
            'reason_ids' => 'required|array',
            'type' => 'required|integer',
            'duty' => 'required',
            'demand' => 'required',
            'salary1' => 'required|numeric',
            'salary2' => 'required|numeric',
        ];
        $attributes = [
            'number' => '人数',
            'positions_id' => '岗位',
            'dept_id' => '部门id',
            'reason_ids' => '原因',
            'type' => '紧急程度',
            'duty' => '职责说明',
            'demand' => '岗位要求',
            'salary1' => '预期薪酬',
            'salary2' => '预期薪酬'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if(in_array(5, $inputs['reason_ids']) && empty($inputs['reason'])){
            return response()->json(['code' => -1, '请填写其它原因']);
        }
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $applyRecruit::type)->orderBy('id', 'desc')->first();//获取最新的配置
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
        $inputs['dept_id'] = $inputs['dept_id'];
        $positions = new \App\Models\Position;
        $positions_info = $positions->where('id', $inputs['positions_id'])->select(['name'])->first();
        $inputs['post'] = $positions_info->name;//岗位名称
        
        $result = $applyRecruit->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('招聘申请', '提交了招聘申请');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);


    }

    /** 
    *  招聘汇总-查看详情
    *  @author molin
    *   @date 2018-11-12
    */
    public function show(){
        $inputs = request()->all();
        $user_info = auth()->user();
        $audit_proces = new \App\Models\AuditProces;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $inputs['apply_id'] = $inputs['id'];
        unset($inputs['id']);
        $proces_info = $audit_proces->getRecruitInfo($inputs);
        //拼接数据
        $data = $recruit_detail  = array();
        $data['id'] = $proces_info['id'];
        //招聘信息
        $proces_info->applyRecruit->reason_ids = explode(',', $proces_info->applyRecruit->reason_ids);
        $data['recruit_detail'] = $proces_info->applyRecruit;

        //加载已经审核的人的评价
        $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyRecruit)::type);
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

    
    

    /** 
    *  招聘申请汇总
    *  @author molin
    *   @date 2018-11-12
    */
    public function index(){
        $inputs = request()->all();
        $apply = new \App\Models\ApplyRecruit;
        
        //已招聘人数
        $recruit = new \App\Models\RecruitList;
        $recruit_list = $recruit->select(['has_number', 'apply_id'])->get()->toArray();
        $recruit_data = array();
        foreach ($recruit_list as $key => $value) {
            $recruit_data[$value['apply_id']] = $value['has_number'];
        }

        $data = $apply->getDataList($inputs);
        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            $items['dept'] = $value->hasDept->name;
            $items['post'] = $value->post;
            $items['number'] = $value->number;
            $items['has_number'] = $recruit_data[$value->id] ?? 0;
            $items['status'] = $value->status;
            if($value->type == 1){
                $items['type'] = '高度紧急';
            }else if($value->type == 2){
                $items['type'] = '紧急';
            }else{
                $items['type'] = '缓慢';
            }
            $items['apply_time'] = $value->created_at->format('Y-m-d H:i:s');
            if($value->status == 0){
                $items['status_txt'] = '审核中';
            }else if($value->status == 1){
                $items['status_txt'] = '已通过';
            }else if($value->status ==2){
                $items['status_txt'] = '已驳回';
            }else if($value->status ==3){
                $items['status_txt'] = '已撤回';
            }
            $data['datalist'][$key] = $items;
        }
        $dept = new \App\Models\Dept;
        $data['dept_list'] = $dept->where('status', 1)->select(['id', 'name'])->get();
        $data['type'] = [['id' => 1, 'name' => '高度紧急'], ['id' => 2, 'name' => '紧急'], ['id' => 3, 'name' => '缓慢']];
        $data['search_status'] = [['id' => 0, 'name' => '审核中'], ['id' => 1, 'name' => '已通过'], ['id' => 2, 'name' => '已驳回'], ['id' => 3, 'name' => '已撤回']];
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  正在招聘表
    *  @author molin
    *   @date 2018-11-12
    */
    public function list(){
        $inputs = request()->all();
        $apply = new \App\Models\RecruitList;

        //已招聘人数
        $recruit = new \App\Models\RecruitList;
        $recruit_list = $recruit->select(['has_number', 'apply_id'])->get()->toArray();
        $recruit_data = array();
        foreach ($recruit_list as $key => $value) {
            $recruit_data[$value['apply_id']] = $value['has_number'];
        }

        $data = $apply->getDataList($inputs);
        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            $items['apply_id'] = $value->apply_id;
            $items['dept'] = $value->hasDept->name;
            $items['post'] = $value->post;
            $items['number'] = $value->number;
            $items['has_number'] = $recruit_data[$value->id] ?? 0;
            $items['status'] = $value->status;
            if($value->type == 1){
                $items['type'] = '高度紧急';
            }else if($value->type == 2){
                $items['type'] = '紧急';
            }else{
                $items['type'] = '缓慢';
            }
            $items['apply_time'] = $value->created_at->format('Y-m-d H:i:s');
            if($value->status == 0){
                $items['status_txt'] = '未开始';
            }else if($value->status == 1){
                $items['status_txt'] = '招聘中';
            }else if($value->status ==2){
                $items['status_txt'] = '已完成';
            }else if($value->status ==3){
                $items['status_txt'] = '已终止';
            }
            $data['datalist'][$key] = $items;
        }
        $dept = new \App\Models\Dept;
        $data['dept_list'] = $dept->where('status', 1)->select(['id', 'name'])->get();
        $data['type'] = [['id' => 1, 'name' => '高度紧急'], ['id' => 2, 'name' => '紧急'], ['id' => 3, 'name' => '缓慢']];
        $data['search_status'] = [['id' => 0, 'name' => '未开始'], ['id' => 1, 'name' => '招聘中'], ['id' => 2, 'name' => '已完成'], ['id' => 3, 'name' => '已终止']];
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  正在招聘表-查看详情
    *  @author molin
    *   @date 2018-11-12
    */
    public function detail(){
        $inputs = request()->all();
        $audit_proces = new \App\Models\AuditProces;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(!isset($inputs['apply_id']) || !is_numeric($inputs['apply_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数apply_id']);
        }
        $inputs['apply_id'] = $inputs['apply_id'];
        $proces_info = $audit_proces->getRecruitInfo($inputs);
        //拼接数据
        $data = $recruit_detail  = array();
        $data['id'] = $proces_info['id'];
        //招聘信息
        $proces_info->applyRecruit->reason_ids = explode(',', $proces_info->applyRecruit->reason_ids);
        $data['recruit_detail'] = $proces_info->applyRecruit;

        //加载已经审核的人的评价
        $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyRecruit)::type);
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

    /** 
    *  开始招聘/完成、终止
    *  @author molin
    *   @date 2018-11-12
    */    
    public function update(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        if(!isset($inputs['request_type']) || empty($inputs['request_type'])){
            return response()->json(['code' => -1, 'message' => '缺少参数request_type']);
        }
        $status = 0;
        $log_txt = '';
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'start'){
            $log_txt = '开始招聘';
            $status = 1;
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'end'){
            $log_txt = '结束招聘';
            $status = 2;
            if(!isset($inputs['has_number']) || !is_numeric($inputs['has_number'])){
                return response()->json(['code' => -1, 'message' => '缺少参数has_number']);
            }
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'stop'){
            $log_txt = '终止招聘';
            $status = 3;
            if(!isset($inputs['has_number']) || !is_numeric($inputs['has_number'])){
                return response()->json(['code' => -1, 'message' => '缺少参数has_number']);
            }
        }
        $recruit = new \App\Models\RecruitList;
        $info = $recruit->where('id', $inputs['id'])->first();
        if(empty($info)){
            return response()->json(['code' => 0, 'message' => '没有找到相关信息']);
        }
        $info->status = $status;
        if(isset($inputs['has_number']) && is_numeric($inputs['has_number'])){
            $info->has_number = $inputs['has_number'];//已招聘人数
        }
        $result = $info->save();
        if($result){
            systemLog('招聘管理', '操作了'.$log_txt);
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }   
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
    * 招聘统计
    * @molin
    * @date 2019-01-08
    */
    public function statistics(){
        $inputs = request()->all();
        $data = array();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'step1'){
            $dept = new \App\Models\Dept;
            $dept_list = $dept->where('status', 1)->select(['id', 'name'])->get()->toArray(); 
            array_unshift($dept_list, ['id' => 0, 'name' => '全部部门']);
            $data['dept_list'] = $dept_list;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'step2'){
            //保存数据
            $rules = [
                'dept_id' => 'required|integer',
                'start_time' => 'required|date_format:Y-m-d',
                'end_time' => 'required|date_format:Y-m-d'
            ];
            $attributes = [
                'dept_id' => '部门id',
                'start_time' => '开始时间',
                'end_time' => '结束时间'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $data['dept_id'] = $inputs['dept_id'];
            if($inputs['dept_id'] == 0){
                //查全部
                unset($inputs['dept_id']);
            }
            $inputs['export'] = 1;//查出全部
            $inputs['search_status'] = 1;//审核通过
            $recruit = new \App\Models\ApplyRecruit;//审核通过就开始统计
            $recruit_list = $recruit->getDataList($inputs);
            $items = array();
            $number = 0;
            foreach ($recruit_list['datalist'] as $key => $value) {
                $items[$key]['post'] = $value->post;
                $items[$key]['number'] = $value->number;
                $items[$key]['dept'] = $value->hasDept->name;
                $items[$key]['remarks'] = '';
                $number += $value->number;
            }
            if(!empty($items)){
                array_push($items, ['post' => '合计', 'number' => $number, 'dept' => '', 'remarks' => '']);
            }
            $data['start_time'] = $inputs['start_time'];
            $data['end_time'] = $inputs['end_time'];
            $data['recruit_list'] = $items;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

        //导出
        $rules = [
            'content' => 'required|array'
        ];
        $attributes = [
            'content' => '导出数据'
        ];
        // dd($inputs);
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        foreach ($inputs['content'] as $key => $value) {
            foreach ($value as $k => $v) {
                if(!in_array($k, ['post','number','dept','remarks'])){
                    return response()->json(['code' => -1, 'message' => '数据结构错误，应包含post、number、dept、remarks']);
                }
            }
        }
        // return response()->json(['code' => 1, 'data' => $inputs['content']]);
        $export_head = ['职位','需求人数','需求部门','备注'];
        $filedata = pExprot($export_head, $inputs['content'], 'recruit_list');
        $filepath = 'storage/exports/' . $filedata['file'];//下载链接
        $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
        return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);


    }

}

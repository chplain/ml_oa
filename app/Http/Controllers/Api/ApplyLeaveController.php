<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyLeaveController extends Controller
{
    /*
    * 离职申请
    * @author molin
    * @date 2018-10-08
    */
    public function store(){
    	$inputs = request()->all();
        $applyLeave = new \App\Models\ApplyLeave;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'apply_leave'){
    		$data = array();
    		$user = new \App\Models\User;
            $inputs['user_id'] = auth()->user()->id;
            $user_info = $user->queryUserInfo($inputs);
            $tmp = array();
    		$tmp['realname'] = $user_info->realname;
            $tmp['dept'] = $user_info->dept->name;
            $tmp['rank'] = $user_info->rank->name;
            $tmp['entry_date'] = $user_info->contracts->entry_date;
    		$data['user_info'] = $tmp;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $applyLeave::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
		//保存数据
    	$rules = [
            'leave_reason' => 'required',
            'leave_date' => 'required'
        ];
        $attributes = [
            'leave_reason' => '离职原因说明',
            'leave_date' => '离职日期'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $inputs['leave_date'] = date('Y-m-d', strtotime($inputs['leave_date']));
        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $applyLeave::type)->orderBy('id', 'desc')->first();//获取最新的配置
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
        
        $result = $applyLeave->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('离职申请', '提交了离职申请');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);

    }

    /*
    * 离职申请-列表
    * @author molin
    * @date 2018-10-08
    */
    public function index(){
    	$inputs = request()->all();

    	$applyLeave = new \App\Models\ApplyLeave;
        $data = $applyLeave->getDataList($inputs);
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$items = $export_data = array();
    	foreach ($data['datalist'] as $key => $value) {
            $items[$key]['id'] = $value->id;
    		$items[$key]['realname'] = $user_data['id_realname'][$value->user_id];
            $items[$key]['created_at'] = $value['created_at']->format('Y-m-d H:i:s'); 
            $items[$key]['leave_date'] = $value->leave_date; 
    		$items[$key]['leave_reason'] = $value->leave_reason; 
    		$items[$key]['status_txt'] = $value->status_txt;
    		
    	}
    	if(isset($inputs['export']) && !empty($inputs['export'])){
    		//导出
    		$header = array('ID','姓名','提交时间','离职日期','原因','状态');
    		$filedata = pExprot($header, $items,'leave_list');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);

    	}
        $data['datalist'] = $items;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /*
    * 离职申请-详情
    * @author molin
    * @date 2018-10-08
    */
    public function show(){
    	$inputs = request()->all();
    	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数id']);
    	}
    	$inputs['apply_id'] = $inputs['id'];
    	unset($inputs['id']);
    	$audit_proces =new \App\Models\AuditProces;
    	$proces_info = $audit_proces->getLeaveInfo($inputs);
    	if(empty($proces_info)){
    		return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
    	}
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();

    	//拼接数据
		$data = $user_info = $ruzhi_info = $salary_info = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        //员工信息
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $user_data['id_dept'][$proces_info->user_id];
        $user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
        $user_info['created_at'] = $proces_info->applyLeave->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyLeave->status_txt;
        $data['user_info'] = $user_info;
        //入职信息
        $ruzhi_info['entry_date'] = $proces_info->contracts->entry_date;
        $ruzhi_info['positive_date'] = $proces_info->contracts->positive_date ? $proces_info->contracts->positive_date : '--';
        $ruzhi_info['leave_time'] = $proces_info->applyLeave->leave_date;//离职日期
        $data['ruzhi_info'] = $ruzhi_info;
        //工资信息
        $salary_info['regular_employee_salary'] = $proces_info->contracts->regular_employee_salary;
        $salary_info['performance'] = $proces_info->contracts->performance;
        $salary_info['total'] = number_format($salary_info['regular_employee_salary'] + $salary_info['performance'],2);
        $data['salary'] = $salary_info;
        $data['leave_reason'] = $proces_info->applyLeave->leave_reason;

        //加载已经审核的人的评价
        $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), $proces_info['type_id']);
        $data['pre_audit_opinion'] = $audit_opinions = array();
        if(!empty($pre_verify_users_data)){
            foreach ($pre_verify_users_data as $key => $value) {
                $audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
                $audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
            }
            $data['pre_audit_opinion'] = $audit_opinions;
        }

        //表单控件是否可见 是否可编辑
        $controls = new \App\Models\AuditFormControlSetting;
        $form_controls_data = $controls->where('setting_id', $proces_info->setting_id)->where('step', $proces_info->step)->get();
        $fields = ['leave_date' => '离职日期', 'leave_reason' => '离职理由'];
        $fields_judge = array();
        foreach ($fields as $key => $value) {
            $fields_judge[$key]['if_show'] = 1;//默认可见
            $fields_judge[$key]['if_edit'] = 0;//默认可编辑
            foreach ($form_controls_data as $k => $v) {
                if($key == $v->name){
                    $fields_judge[$key]['if_show'] = $v->if_show;
                    $fields_judge[$key]['if_edit'] = $v->if_edit;
                }
            }
        }
        $data['fields_judge'] = $fields_judge;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

   

}

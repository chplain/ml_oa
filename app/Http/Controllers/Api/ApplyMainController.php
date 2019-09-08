<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class ApplyMainController extends Controller
{
    /** 
    *  申请汇总
    *  @author molin
    *	@date 2018-09-26
    */
    public function index(){
    	$inputs = request()->all();
    	if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
    		if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
    			return response()->json(['code' => -1, 'message' => '开始时间必须小于结束时间']);
    		}	
    	}
    	$mains = new \App\Models\ApplyMain;
    	$apply_list = $mains->getQueryList($inputs);

    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
        //1出勤申请 2物品领用3采购申请4招聘申请5培训申请6转正申请7离职申请
        $applys = (new \App\Models\ApplyType)->getTypes();
    	foreach ($apply_list['datalist'] as $key => $value) {
            $apply_list['datalist'][$key]->apply_type = $applys[$value->type_id];
    		$apply_list['datalist'][$key]->username = $user_data['id_realname'][$value->user_id];
    		$apply_list['datalist'][$key]->dept = $user_data['id_dept'][$value->user_id];
    		$apply_list['datalist'][$key]->rank = $user_data['id_rank'][$value->user_id];
    		$apply_list['datalist'][$key]->dept_manager = $user_data['id_realname'][$value->applyDepts->supervisor_id];
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $apply_list]);
    }

    /** 
    *  申请汇总-查看详情页面
    *  @author molin
    *   @date 2018-10-11
    */
    public function show(){
        $inputs = request()->all();
        if(!isset($inputs['type_id']) || !is_numeric($inputs['type_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数type_id']);
        }
        if(!isset($inputs['apply_id']) || !is_numeric($inputs['apply_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数apply_id']);
        }
        $audit_proces = new \App\Models\AuditProces;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        switch ($inputs['type_id']) {
            case 2:
                //物品领用
                $proces_info = $audit_proces->getAccessInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getAccessInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            case 3:
                //采购
                $proces_info = $audit_proces->getPurchaseInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getPurchaseInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            case 4:
                //招聘
                $proces_info = $audit_proces->getRecruitInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getRecruitInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            case 5:
                //培训
                $proces_info = $audit_proces->getTrainingInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getTrainingInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            case 6:
                //转正
                $proces_info = $audit_proces->getFormalInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getFormalInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            case 7:
                //离职
                $proces_info = $audit_proces->getLeaveInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getLeaveInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            case 8:
                //报备
                $proces_info = $audit_proces->getReportInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getReportInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
            default:
                //出勤
                $proces_info = $audit_proces->getAttInfo($inputs);
                if(empty($proces_info)){
                    return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                }
                $data = $this->getAttInfo($inputs, $audit_proces, $proces_info, $user_data);
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                break;
        }
    }

    /** 
    *  我的申请-列表
    *  @author molin
    *   @date 2018-10-11
    */
    public function list(){
        $inputs = request()->all();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'return'){
            //撤回
            if(!isset($inputs['type_id']) || !is_numeric($inputs['type_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数type_id']);
            }
            if(!isset($inputs['apply_id']) || !is_numeric($inputs['apply_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数apply_id']);
            }
            $audit_proces = new \App\Models\AuditProces;
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            switch ($inputs['type_id']) {
                case 2:
                    //物品领用
                    $proces_info = $audit_proces->getAccessInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyAccess;
                    $apply_txt = '物品领用申请';
                    break;
                case 3:
                    //采购
                    $proces_info = $audit_proces->getPurchaseInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyPurchase;
                    $apply_txt = '采购申请';
                    break;
                case 4:
                    //招聘
                    $proces_info = $audit_proces->getRecruitInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyRecruit;
                    $apply_txt = '招聘申请';
                    break;
                case 5:
                    //培训
                    $proces_info = $audit_proces->getTrainingInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyTraining;
                    $apply_txt = '培训申请';
                    break;
                case 6:
                    //转正
                    $proces_info = $audit_proces->getFormalInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyFormal;
                    $apply_txt = '转正申请';
                    break;
                case 7:
                    //离职
                    $proces_info = $audit_proces->getLeaveInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyLeave;
                    $apply_txt = '离职申请';
                    break;
                case 8:
                    //报备
                    $proces_info = $audit_proces->getReportInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyReport;
                    $apply_txt = '报备申请';
                    break;
                default:
                    //出勤
                    $proces_info = $audit_proces->getAttInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $apply_info = $proces_info->applyAtt;
                    $apply_txt = '出勤申请';
                    break;
            }
            $time1 = $apply_info->created_at->format('Y-m-d');
            $time2 = date('Y-m-d');
            if($inputs['type_id'] != 1 && $apply_info->status == 1){
                //出勤申请审核通过后也可以撤回  其它的审核通过后不能撤回
                return response()->json(['code' => -1, 'message' => '这条申请不能撤回']);
            }
            $days = prDates($time1, $time2);
            if(count($days) > 10){
                //申请超过9天的不能撤回
                return response()->json(['code' => -1, 'message' => '这条申请不能撤回']);
            }
            // dd($apply_info);
            $mains = new \App\Models\ApplyMain;
            $mains_info = $mains->where('type_id', $inputs['type_id'])->where('apply_id', $inputs['apply_id'])->first();
            DB::transaction(function () use ($audit_proces, $apply_info, $mains_info, $inputs, $apply_txt, $user_data) {
                $audit_proces->where('type_id', $inputs['type_id'])->where('apply_id', $inputs['apply_id'])->update(['status'=>3, 'updated_at'=>date('Y-m-d H:i:s')]);
                $apply_info->status = 3;
                $apply_info->status_txt = '已撤回';
                $apply_info->save();
                $mains_info->status = 3;
                $mains_info->status_txt = '已撤回';
                $mains_info->save();
                if($inputs['type_id'] == 1){
                    //出勤申请撤回  删除详细信息
                    $detail = new \App\Models\ApplyAttendanceDetail;
                    $detail->where('apply_id', $apply_info->id)->delete();
                    $times = new \App\Models\ApplyAttendanceTime;
                    $times->where('apply_id', $apply_info->id)->delete();
                }
            }, 5);
            systemLog('申请', '撤回了'.$apply_txt);
            $notice_users = $audit_proces->where('type_id', $inputs['type_id'])->where('apply_id', $inputs['apply_id'])->where('status', 1)->pluck('current_verify_user_id')->toArray();
            if(!empty($notice_users)){
                addNotice($notice_users, '申请', $user_data['id_realname'][$apply_info->user_id].'的'.$apply_txt.'已撤回', '', 0, 'approval-audit-index','apply/verify');//提醒已审核审核人
            }
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        $inputs['user_id'] = auth()->user()->id;
        if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
            if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
                return response()->json(['code' => -1, 'message' => '开始时间必须小于结束时间']);
            }   
        }
        $mains = new \App\Models\ApplyMain;
        $apply_list = $mains->getQueryList($inputs);

        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        //1出勤申请 2物品领用3采购申请4招聘申请5培训申请6转正申请7离职申请
        $applys = (new \App\Models\ApplyType)->getTypes();
        foreach ($apply_list['datalist'] as $key => $value) {
            $apply_list['datalist'][$key]->apply_type = $applys[$value->type_id];
            $apply_list['datalist'][$key]->username = $user_data['id_realname'][$value->user_id];
            $apply_list['datalist'][$key]->dept = $user_data['id_dept'][$value->user_id];
            $apply_list['datalist'][$key]->rank = $user_data['id_rank'][$value->user_id];
            $apply_list['datalist'][$key]->dept_manager = $user_data['id_realname'][$value->applyDepts->supervisor_id];
            $apply_list['datalist'][$key]->if_return = 0;//撤回按钮 1显示  0隐藏
            if($value->status == 0){
                $apply_list['datalist'][$key]->if_return = 1;
            }
            if($value->status == 1 && $value->type_id == 1){
                //出勤申请通过后也可以撤回
                $apply_list['datalist'][$key]->if_return = 1;
            }
            $time1 = $value->created_at->format('Y-m-d');
            $time2 = date('Y-m-d');
            $days = prDates($time1, $time2);
            if(count($days) > 10){
                //申请超过9天的不能撤回
                $apply_list['datalist'][$key]->if_return = 0;
            }
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $apply_list]);
    }


    /** 
    *  我的审核-列表
    *  @author molin
    *   @date 2018-10-11
    */
    public function verify(){
        $inputs = request()->all();
        $audit_proces = new \App\Models\AuditProces;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'info'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            if(!isset($inputs['type_id']) || !is_numeric($inputs['type_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数type_id']);
            }
            
            $inputs['status'] = 0;
            //分开判断
            switch ($inputs['type_id']) {
                case 1:
                    //考勤申请
                    $proces_info = $audit_proces->getAttInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getAttInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
                case 2:
                    //物品领用申请
                    $proces_info = $audit_proces->getAccessInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getAccessInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
                case 3:
                    $proces_info = $audit_proces->getPurchaseInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getPurchaseInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
                case 4:
                    $proces_info = $audit_proces->getRecruitInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getRecruitInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
                case 5:
                    $proces_info = $audit_proces->getTrainingInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getTrainingInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
                case 6:
                    $proces_info = $audit_proces->getFormalInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getFormalInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
                case 7:
                    $proces_info = $audit_proces->getLeaveInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getLeaveInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
                case 8:
                    $proces_info = $audit_proces->getReportInfo($inputs);
                    if(empty($proces_info)){
                        return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
                    }
                    $data = $this->getReportInfo($inputs, $audit_proces, $proces_info, $user_data);
                    break;
            }
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'submit'){
            //提交评审
            $rules = [
                'id' => 'required',
                'type_id' => 'required',
                'pass' => 'required',
                'audit_opinion' => 'required'
            ];
            $attributes = [
                'id' => '缺少参数id',
                'type_id' => '缺少参数type_id',
                'pass' => '是否通过',
                'audit_opinion' => '评价'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $user_info = auth()->user();
            $inputs['status'] = 0;
            switch ($inputs['type_id']) {
                case 1:
                    //出勤
                    $log_txt = '出勤申请';
                    $proces_info = $audit_proces->getAttInfo($inputs);
                    if($inputs['pass'] == 1){
                        $days = prDates(date('Y-m-d', strtotime($proces_info->start_time)),date('Y-m-d', strtotime($proces_info->end_time)));
                        $apply_detail = new \App\Models\ApplyAttendanceDetail;
                        $detail_list = $apply_detail->whereIn('date', $days)->where('type', $proces_info->applyAtt->type)->where('user_id', $user_info->id)->first();
                        if(!empty($detail_list)){
                            return response()->json(['code' => 0, 'message' => '该时间段内已经存在申请单,不能重复审核相同时间']);
                        }
                    }
                    break;
                case 2:
                    //物品领用
                    $log_txt = '物品领用申请';
                    $proces_info = $audit_proces->getAccessInfo($inputs);
                    break;
                case 3:
                    //采购
                    $log_txt = '采购申请';
                    $proces_info = $audit_proces->getPurchaseInfo($inputs);
                    break;
                case 4:
                    //招聘
                    $log_txt = '招聘申请';
                    $proces_info = $audit_proces->getRecruitInfo($inputs);
                    break;
                case 5:
                    //培训
                    $log_txt = '培训申请';
                    $proces_info = $audit_proces->getTrainingInfo($inputs);
                    break;
                case 6:
                    //转正
                    $log_txt = '转正申请';
                    $rules = [
                        'work_achievement' => 'required|array',
                        'work_attitude' => 'required|array',
                        'work_ability' => 'required|array',
                        'audit_opinion' => 'required'
                    ];
                    $attributes = [
                        'work_achievement' => '工作业绩打分',
                        'work_attitude' => '工作态度打分',
                        'work_ability' => '工作能力打分',
                        'audit_opinion' => '评价'
                    ];
                    $validator = validator($inputs, $rules, [], $attributes);
                    if ($validator->fails()) {
                        return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
                    }
                    $proces_info = $audit_proces->getFormalInfo($inputs);
                    break;
                case 7:
                    //离职
                    $log_txt = '离职申请';
                    $proces_info = $audit_proces->getLeaveInfo($inputs);
                    break;
                case 8:
                    //报备
                    $log_txt = '报备申请';
                    $proces_info = $audit_proces->getReportInfo($inputs);
                    break;
            }
            if(empty($proces_info)){
                return response()->json(['code' => -1, 'message' => '没有该流程或当前流程不是您来评审']);
            }
            if($user_info->id != $proces_info->current_verify_user_id){
                return response()->json(['code' => -1, 'message' => '没有该流程或当前流程不是您来评审']);
            }
            $res = $this->setVerify($inputs, $audit_proces, $proces_info);
            if($res){
                $audit_record = new \App\Models\AuditProcessStepRecord;
                $record = array();
                $record['type_id'] = $proces_info->type_id;
                $record['apply_id'] = $proces_info->apply_id;
                $record['step'] = $proces_info->step;
                $record['status'] = $inputs['pass'];
                $audit_record->storeData($record);//记录审核步骤
                systemLog('审核', '审核了['.$user_data['id_realname'][$proces_info->user_id].']['.$log_txt.']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }

        if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
            if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
                return response()->json(['code' => -1, 'message' => '开始时间必须小于结束时间']);
            }   
        }
        if(isset($inputs['keywords']) && !empty($inputs['keywords'])){
            $inputs['user_ids'] = $user->where('realname', 'like', '%'.$inputs['keywords'].'%')->pluck('id')->toArray(); 
        }
        $user_id = auth()->user()->id;
        //$inputs['status'] = 0;//待审核
        $inputs['if_verify'] = 1;//查出待审
        $audit_list = $audit_proces->getMyVerifyList($inputs);
        //1出勤申请 2物品领用3采购申请4招聘申请5培训申请6转正申请7离职申请8报备
        $mains = new \App\Models\ApplyMain;
        $applys = (new \App\Models\ApplyType)->getTypes();
        $att_ids = $acc_ids = $cai_ids = $recruit_ids = $training_ids = $formal_ids = $leave_ids = $report_ids = array();
        foreach ($audit_list['datalist'] as $key => $value) {
            switch ($value->table) {
                case 'ApplyAttendance':
                    $att_ids[] = $value->apply_id;
                    break;
                case 'ApplyAccess':
                    $acc_ids[] = $value->apply_id;
                    break;
                case 'ApplyPurchase':
                    $cai_ids[] = $value->apply_id;
                    break;
                case 'ApplyFormal':
                    $formal_ids[] = $value->apply_id;
                    break;
                case 'ApplyLeave':
                    $leave_ids[] = $value->apply_id;
                    break;
                case 'ApplyTraining':
                    $training_ids[] = $value->apply_id;
                    break;
                case 'ApplyRecruit':
                    $recruit_ids[] = $value->apply_id;
                    break;
                case 'ApplyReport':
                    $report_ids[] = $value->apply_id;
                    break;
            }
        }
        //查询对应的内容 和审核状态
        if(!empty($att_ids)){
            $att = new \App\Models\ApplyAttendance;
            $att_list = $att->whereIn('id', $att_ids)->select(['id','remarks','status_txt'])->get();
            $att_content_data = $att_status_data = array();
            foreach ($att_list as $key => $value) {
                // $att_content_data[$value->id] = $value->remarks;
                $att_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyAttendance')->whereIn('apply_id', $att_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $att_content_data[$value->apply_id] = $value->content;
            }
        }
        if(!empty($acc_ids)){
            $acc = new \App\Models\ApplyAccess;
            $acc_list = $acc->whereIn('id', $acc_ids)->select(['id','uses','status_txt'])->get();
            $acc_content_data = $acc_status_data = array();
            foreach ($acc_list as $key => $value) {
                // $acc_content_data[$value->id] = $value->uses;
                $acc_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyAccess')->whereIn('apply_id', $acc_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $acc_content_data[$value->apply_id] = $value->content;
            }
        }
        if(!empty($cai_ids)){
            $cai = new \App\Models\ApplyPurchase;
            $cai_list = $cai->whereIn('id', $cai_ids)->select(['id','goods_name','status_txt'])->get();
            $cai_content_data = $cai_status_data = array();
            foreach ($cai_list as $key => $value) {
                // $cai_content_data[$value->id] = $value->goods_name;
                $cai_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyPurchase')->whereIn('apply_id', $cai_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $cai_content_data[$value->apply_id] = $value->content;
            }
        }
        if(!empty($formal_ids)){
            $formal = new \App\Models\ApplyFormal;
            $formal_list = $formal->whereIn('id', $formal_ids)->select(['id','work_content','status_txt'])->get();
            $formal_content_data = $formal_status_data = array();
            foreach ($formal_list as $key => $value) {
                // $formal_content_data[$value->id] = $value->work_content;
                $formal_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyFormal')->whereIn('apply_id', $formal_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $formal_content_data[$value->apply_id] = $value->content;
            }
        }
        if(!empty($leave_ids)){
            $leave = new \App\Models\ApplyLeave;
            $leave_list = $leave->whereIn('id', $leave_ids)->select(['id','leave_reason','status_txt'])->get();
            $leave_content_data = $leave_status_data = array();
            foreach ($leave_list as $key => $value) {
                // $leave_content_data[$value->id] = $value->leave_reason;
                $leave_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyLeave')->whereIn('apply_id', $leave_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $leave_content_data[$value->apply_id] = $value->content;
            }
        }
        if(!empty($training_ids)){
            $training = new \App\Models\ApplyTraining;
            $training_list = $training->whereIn('id', $training_ids)->select(['id','name','status_txt'])->get();
            $training_content_data = $training_status_data = array();
            foreach ($training_list as $key => $value) {
                // $training_content_data[$value->id] = $value->name;
                $training_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyTraining')->whereIn('apply_id', $training_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $training_content_data[$value->apply_id] = $value->content;
            }
        }
        if(!empty($recruit_ids)){
            $recruit = new \App\Models\ApplyRecruit;
            $recruit_list = $recruit->whereIn('id', $recruit_ids)->select(['id','post','status_txt'])->get();
            $recruit_content_data = $recruit_status_data = array();
            foreach ($recruit_list as $key => $value) {
                // $recruit_content_data[$value->id] = $value->post;
                $recruit_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyRecruit')->whereIn('apply_id', $recruit_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $recruit_content_data[$value->apply_id] = $value->content;
            }
        }
        if(!empty($report_ids)){
            $report = new \App\Models\ApplyReport;
            $report_list = $report->whereIn('id', $report_ids)->select(['id','remarks','status_txt'])->get();
            $report_content_data = $report_status_data = array();
            foreach ($report_list as $key => $value) {
                // $report_content_data[$value->id] = $value->remarks;
                $report_status_data[$value->id] = $value->status_txt;
            }
            $main_list = $mains->where('table', 'ApplyReport')->whereIn('apply_id', $report_ids)->select(['apply_id','content'])->get();
            foreach ($main_list as $key => $value) {
                $report_content_data[$value->apply_id] = $value->content;
            }
        }
        //拼接
        $items = array();
        foreach ($audit_list['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            $items['apply_type'] = $applys[$value->type_id];
            $items['username'] = $user_data['id_realname'][$value->user_id];
            $items['dept'] = $user_data['id_dept'][$value->user_id];
            $items['rank'] = $user_data['id_rank'][$value->user_id];
            $items['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $items['type_id'] = $value->type_id;
            $items['apply_id'] = $value->apply_id;
            $items['dept_manager'] = $user_data['id_realname'][$value->hasDept->supervisor_id] ?? '--';
            switch ($value->table) {
                case 'ApplyAttendance':
                    $items['content'] = $att_content_data[$value->apply_id];
                    $items['status_txt'] = $att_status_data[$value->apply_id];
                    break;
                case 'ApplyAccess':
                    $items['content'] = $acc_content_data[$value->apply_id];
                    $items['status_txt'] = $acc_status_data[$value->apply_id];
                    break;
                case 'ApplyPurchase':
                    $items['content'] = $cai_content_data[$value->apply_id];
                    $items['status_txt'] = $cai_status_data[$value->apply_id];
                    break;
                case 'ApplyFormal':
                    $items['content'] = $formal_content_data[$value->apply_id];
                    $items['status_txt'] = $formal_status_data[$value->apply_id];
                    break;
                case 'ApplyLeave':
                    $items['content'] = $leave_content_data[$value->apply_id];
                    $items['status_txt'] = $leave_status_data[$value->apply_id];
                    break;
                case 'ApplyTraining':
                    $items['content'] = $training_content_data[$value->apply_id];
                    $items['status_txt'] = $training_status_data[$value->apply_id];
                    break;
                case 'ApplyRecruit':
                    $items['content'] = $recruit_content_data[$value->apply_id];
                    $items['status_txt'] = $recruit_status_data[$value->apply_id];
                    break;
                case 'ApplyReport':
                    $items['content'] = $report_content_data[$value->apply_id];
                    $items['status_txt'] = $report_status_data[$value->apply_id];
                    break;
                default:
                    $items['content'] = '--';
                    $items['status_txt'] = '--';
                    break;
            }
            $items['current_step'] = $value->current_step;
            if($value->status == 0 && $value->step == $value->current_step && $value->current_verify_user_id == $user_id){
                $items['if_verify'] = 1;
            }else{
                $items['if_verify'] = 0;
            }
            $audit_list['datalist'][$key] = $items;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $audit_list]);
    }


    public function getAttInfo($inputs, $audit_proces, $proces_info, $user_data){
        
        //拼接数据
        $data = $user_info = $apply_att = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        //员工信息
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $user_data['id_dept'][$proces_info->user_id];
        $user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
        $user_info['created_at'] = $proces_info->applyAtt->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyAtt->status_txt;
        $data['user_info'] = $user_info;
        
        $apply_att['start_time'] = $proces_info->applyAtt->start_time;
        $apply_att['end_time'] = $proces_info->applyAtt->end_time;
        $apply_att['remarks'] = $proces_info->applyAtt->remarks;

        //申请类型：1请假申请2加班申请3外勤申请
        if($proces_info->applyAtt->type == 1){
            $apply_att['type'] = '请假申请';
            $holiday = new \App\Models\HolidayType;
            $type_data = $holiday->getIdToData();
            $apply_att['leave_type'] = $type_data[$proces_info->applyAtt->leave_type];
            $apply_att['time'] = $proces_info->applyAtt->leave_time;
        }else if($proces_info->applyAtt->type == 2){
            $apply_att['type'] = '加班申请';
            $apply_att['time'] = $proces_info->applyAtt->time_str;
        }else{
            $apply_att['type'] = '外勤申请';
            $apply_att['outside_addr'] = $proces_info->applyAtt->outside_addr;
            $apply_att['time'] = $proces_info->applyAtt->time_str;
        }
        $time_data = [0=>['start_time'=>$apply_att['start_time'],'end_time'=>$apply_att['end_time'], 'time'=>$apply_att['time']]];
        if($proces_info->applyAtt->time_data){
            $tdata = unserialize($proces_info->applyAtt->time_data);
            $time_data = [];
            foreach ($tdata as $key => $value) {
                $time_data[$key]['start_time'] = $value['start_time'];
                $time_data[$key]['end_time'] = $value['end_time'];
                $time_data[$key]['time'] = $value['time_str'];
            }
        }
        $apply_att['time_data'] = $time_data;
        $data['apply_att'] = $apply_att;

        //加载已经审核的人的评价
        $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), 1);
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
        $fields = ['type' => '申请类型', 'time' => '请假时间', 'leave_type' => '请假类型', 'outside_addr' => '外出地点', 'leave_time' => '共(天)', 'time_str' => '共(小时)', 'remarks' => '事由'];
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
        return $data;
                    
    }

    public function getAccessInfo($inputs, $audit_proces, $proces_info, $user_data){
        
        $cate = new \App\Models\GoodsCategory;
        $cate_data = $cate->getIdToData();
        $goods = new \App\Models\Goods;
        $goods_data = $goods->getIdToData();

        //拼接数据
        $data = $user_info = $goods_info = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        //领用部门、申请人信息
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $proces_info->hasDept->name;
        $user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
        $user_info['created_at'] = $proces_info->applyAccess->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyAccess->status_txt;
        $data['user_info'] = $user_info;

        $goods_info['created_at'] = $proces_info->applyAccess->created_at->format('Y-m-d H:i:s');
        if($proces_info->applyAccess->if_personnel == 1){
            $goods_info['type'] = '人事预备申请';
        }else{
            $goods_info['type'] = '普通申请';
        }
        $goods_info['status_txt'] = $proces_info->applyAccess->status_txt;
        $content = unserialize($proces_info->applyAccess->content);
        $tmp = $current_storage =  array();
        foreach ($content as $k => $val) {
            $tmp[$k]['goods_name'] = $goods_data['id_name'][$val['goods_id']];
            $tmp[$k]['unit'] = $goods_data['id_unit'][$val['goods_id']];
            $tmp[$k]['num'] = $val['num'];
            $tmp[$k]['user'] = isset($val['user_id']) ? $user_data['id_realname'][$val['user_id']] : '--';

            $current_storage[$k]['goods_name'] = $goods_data['id_name'][$val['goods_id']];
            $current_storage[$k]['unit'] = $goods_data['id_unit'][$val['goods_id']];
            $current_storage[$k]['storage'] = $goods_data['id_storage'][$val['goods_id']];
        }
        $goods_info['goods'] = $tmp;
        $goods_info['current_storage'] = $current_storage;
        $goods_info['uses'] = $proces_info->applyAccess->uses;
        $data['goods_info'] = $goods_info;

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
        $fields = ['content' => '物品领用', 'uses' => '用途'];
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
        return $data;
    }

    public function getPurchaseInfo($inputs, $audit_proces, $proces_info, $user_data){
        $cate = new \App\Models\GoodsCategory;
        $cate_data = $cate->getIdToData();
        $goods = new \App\Models\Goods;
        $goods_data = $goods->getIdToData();
        //拼接数据
        $data = $user_info = $goods_info = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        //领用部门、申请人信息
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $proces_info->hasDept->name;
        $user_info['created_at'] = $proces_info->applyPurchase->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyPurchase->status_txt;
        $data['user_info'] = $user_info;
        //物品信息
        if($proces_info->applyPurchase->type_id == 1){
            $goods_info['type'] = '固定资产';
        }else if($proces_info->applyPurchase->type_id == 2){
            $goods_info['type'] = '消费品资产';
        }
        $goods_info['cate'] = $cate_data[$proces_info->applyPurchase->cate_id];
        $goods_info['goods'] = $goods_data['id_name'][$proces_info->applyPurchase->goods_id];
        $goods_info['goods_name'] = $proces_info->applyPurchase->goods_name;
        $goods_info['unit'] = $goods_data['id_unit'][$proces_info->applyPurchase->goods_id];
        $goods_info['num'] = $proces_info->applyPurchase->num;
        $goods_info['spec'] = $proces_info->applyPurchase->spec;
        if(!empty($proces_info->applyPurchase->images)){
            $images = explode(',', $proces_info->applyPurchase->images);
            foreach ($images as $k => $img) {
                $goods_info['images'][$k] = asset($img);
            }
        }else{
            $goods_info['images'] = array();
        }
        $goods_info['uses'] = $proces_info->applyPurchase->uses;
        if($proces_info->applyPurchase->degree_id == 1){
            $goods_info['degree'] = '紧急';
        }else if($proces_info->applyPurchase->degree_id == 2){
            $goods_info['degree'] = '一般';
        }else if($proces_info->applyPurchase->degree_id == 3){
            $goods_info['degree'] = '不紧急';
        }
        $goods_info['rdate'] = $proces_info->applyPurchase->rdate;
        $data['goods_info'] = $goods_info;

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
        $fields = ['type_id' => '采购类型', 'cate_id' => '大类', 'goods_id' => '小类', 'goods_name' => '物品名称', 'num' => '数量', 'spec' => '规格', 'images' => '图片', 'uses' => '用途', 'degree_id' => '紧急情况', 'rdate' => '最后期限'];
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
        return $data;
    }

    public function getRecruitInfo($inputs, $audit_proces, $proces_info, $user_data){
        //拼接数据
        $data = $user_info =$recruit_detail  = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        $data['id'] = $proces_info['id'];
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $proces_info->hasDept->name;
        $user_info['created_at'] = $proces_info->applyRecruit->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyRecruit->status_txt;
        $data['user_info'] = $user_info;
        //招聘信息
        $proces_info->applyRecruit->reason_ids = explode(',', $proces_info->applyRecruit->reason_ids);
        $data['recruit_detail'] = $proces_info->applyRecruit;

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
        $fields = ['number' => '人数', 'post' => '岗位', 'reason' => '理由', 'type' => '紧急程度', 'duty' => '职责说明', 'demand' => '岗位要求', 'salary' => '薪资范围'];
        $fields_judge = array();
        foreach ($fields as $key => $value) {
            $fields_judge[$key]['if_show'] = 1;//默认可见
            $fields_judge[$key]['if_edit'] = 0;//默认不可编辑
            foreach ($form_controls_data as $k => $v) {
                if($key == $v->name){
                    $fields_judge[$key]['if_show'] = $v->if_show;
                    $fields_judge[$key]['if_edit'] = $v->if_edit;
                }
            }
        }
        $data['fields_judge'] = $fields_judge;
        return $data;
    }

    public function getTrainingInfo($inputs, $audit_proces, $proces_info, $user_data){
        //拼接数据
        $data = $user_info =$apply_training = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $proces_info->hasDept->name;
        $user_info['created_at'] = $proces_info->applyTraining->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyTraining->status_txt;
        $data['user_info'] = $user_info;
        //申请信息
        $apply_training['name'] = $proces_info->applyTraining->name;
        $content = unserialize($proces_info->applyTraining->content);
        $type_id = $proces_info->applyTraining->type_id;
        $by_training_users = $training_users = $training_projects = $projects_ids = $supervision_peoples = array();
        $training = new \App\Models\TrainingProject;
        $training_data = $training->getIdToData();
        $training_content = array();
        foreach ($content as $key => $value) {
            if($type_id == 1){
                $training_content[$key]['by_training_user'] = $user_data['id_realname'][$value['by_training_user']];
                $tmp = array();
                foreach ($value['training_projects'] as $k => $v) {
                    $tmp[] = $training_data['id_name'][$v];
                }
                $training_content[$key]['training_projects'] = implode(',', $tmp);

            }
            if($type_id == 2){
                $training_content[$key]['training_projects'] = $value['training_projects'];
                $training_content[$key]['training_user'] = $user_data['id_realname'][$value['training_user']];
                $tmp = array();
                foreach($value['by_training_user'] as $val){
                    $tmp[] = $user_data['id_realname'][$val];
                }
                $training_content[$key]['by_training_user'] = implode(',', $tmp);
                
            }
        }
        if($type_id == 1){
            $apply_training['type_id'] = 1;
            $apply_training['type'] = '入职培训';
            $apply_training['training_content'] = $training_content;
            $data['apply_training'] = $apply_training;
        }else{
            $apply_training['type_id'] = 2;
            $apply_training['type'] = '拓展培训';
            $apply_training['training_content'] = $training_content;
            //地点 时间
            $addr = new \App\Models\TrainingAddr;
            $addr_list = $addr->get();
            $addr_arr = array();
            foreach ($addr_list as $key => $value) {
                $addr_arr[$value['id']] = $value['name']; 
            }

            $apply_training['addr_info'] = $addr_arr[$proces_info->applyTraining->addr_id] ?? '';
            $apply_training['start_time'] = $proces_info->applyTraining->start_time ? $proces_info->applyTraining->start_time : '';
            $apply_training['end_time'] = $proces_info->applyTraining->end_time ? $proces_info->applyTraining->end_time : '';
            $data['apply_training'] = $apply_training;
        }
        
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
        $fields = ['name' => '培训名称', 'type' => '培训类型', 'addr_id' => '培训地点', 'content' => '培训内容', 'time' => '培训时间', 'training' => '培训人或被培训人'];
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
        return $data;
    }

    public function getFormalInfo($inputs, $audit_proces, $proces_info, $user_data){
        //拼接数据
        $data = $user_detail = $apply_formals = $attendance = $evaluation_score = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        //步骤信息
        $steps_data = $audit_proces->where('type_id', 6)->where('apply_id', $proces_info->apply_id)->select(['step','current_verify_user_id'])->get();

        $current_step = $proces_info->current_step;
        $step_arr = ['0'=>['s'=> '1', 'if_current'=> $current_step == 'step0' ? 1:0, 'step' => '自我评价']];
        $all_steps = $verify_user = array();
        foreach ($steps_data as $key => $val) {
            $verify_user[$val['step']][] = $user_data['id_rank'][$val['current_verify_user_id']].$user_data['id_realname'][$val['current_verify_user_id']];
            $all_steps[$val['step']] = $val['step'];
        }
        $j = 1;
        foreach ($all_steps as $stp) {
            $j++;
            $verify_users = implode('/', $verify_user[$stp]);
            $step_arr[] = ['s' => $j, 'if_current'=> $current_step == $stp ? 1:0, 'step' => $verify_users];
        }
        $data['step_arr'] = $step_arr;

        //员工信息
        $user_detail['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_detail['birthday'] = $proces_info->hasUser->birthday;
        $user_detail['dept'] = $user_data['id_dept'][$proces_info->user_id];
        $user_detail['rank'] = $user_data['id_rank'][$proces_info->user_id];
        if($proces_info->hasUser->sex == 1){
            $user_detail['sex'] = '男';
        }else if($proces_info->hasUser->sex == 2){
            $user_detail['sex'] = '女';
        }else{
            $user_detail['sex'] = '未知';
        }

        $user_detail['entry_date'] = $proces_info->contracts->entry_date;
        $user_detail['positive_date'] = $proces_info->contracts->positive_date ? $proces_info->contracts->positive_date : '--';
        $user_detail['created_at'] = $proces_info->applyFormal->created_at->format('Y-m-d H:i:s');
        $user_detail['status_txt'] = $proces_info->applyFormal->status_txt;
        $data['user_detail'] = $user_detail;
        //自我评价
        $apply_formals['work_content'] = $proces_info->applyFormal->work_content;
        $apply_formals['work_ok'] = $proces_info->applyFormal->work_ok;
        $apply_formals['work_learn'] = $proces_info->applyFormal->work_learn;
        $apply_formals['work_plan'] = $proces_info->applyFormal->work_plan;
        $apply_formals['formal_date'] = $proces_info->applyFormal->formal_date;
        $data['apply_formals'] = $apply_formals;

        //部门评审
        $data['attendance'] = $this->getAttendance($proces_info->user_id,$proces_info->contracts->entry_date,$proces_info->applyFormal->created_at->format('Y-m-d'));
        //评分
        $total_score = '--';
        if(!empty($proces_info->applyFormal->score)){
            $total_score = 0;
            $score = unserialize($proces_info->applyFormal->score);
            $evaluation_score['work_achievement'] = $score['work_achievement'];
            $evaluation_score['work_attitude'] = $score['work_attitude'];
            $evaluation_score['work_ability'] = $score['work_ability'];
            foreach ($score['work_achievement'] as $num) {
                $total_score += $num;
            }
            foreach ($score['work_attitude'] as $num) {
                $total_score += $num;
            }
            foreach ($score['work_ability'] as $num) {
                $total_score += $num;
            }
        }
        
        $data['evaluation_score'] = $evaluation_score;
        $data['total_score'] = $total_score;

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
        $fields = ['work_content' => '工作内容', 'work_ok' => '完成情况', 'work_learn' => '学到的技能', 'work_plan' => '学习计划', 'score' => '评分'];
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
        return $data;
    }

    public function getLeaveInfo($inputs, $audit_proces, $proces_info, $user_data){
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
        return $data;
    }

    public function getReportInfo($inputs, $audit_proces, $proces_info, $user_data){
        //拼接数据
        $data = $user_info = array();
        $data['id'] = $proces_info['id'];
        $data['type_id'] = $proces_info['type_id'];
        //员工信息
        $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
        $user_info['dept'] = $user_data['id_dept'][$proces_info->user_id];
        $user_info['rank'] = $user_data['id_rank'][$proces_info->user_id];
        $user_info['created_at'] = $proces_info->applyReport->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyReport->status_txt;
        $data['user_info'] = $user_info;
        //打卡时间
        $data['daka_info'] = unserialize($proces_info->applyReport->content);
        //备注
        $data['remarks'] = $proces_info->applyReport->remarks;

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
        $fields = ['content' => '打卡时间'];
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
        return $data;
    }

    //审核
    public function setVerify($inputs, $audit_proces, $proces_info){

        if($proces_info->type_id == 1){
            $applyInfo = $proces_info->applyAtt;
        }
        if($proces_info->type_id == 2){
            $applyInfo = $proces_info->applyAccess;
        }
        if($proces_info->type_id == 3){
            $applyInfo = $proces_info->applyPurchase;
        }
        if($proces_info->type_id == 4){
            $applyInfo = $proces_info->applyRecruit;
        }
        if($proces_info->type_id == 5){
            $applyInfo = $proces_info->applyTraining;
        }
        if($proces_info->type_id == 6){
            $applyInfo = $proces_info->applyFormal;
            $score = ['work_achievement' => $inputs['work_achievement'], 'work_attitude' => $inputs['work_attitude'], 'work_ability' => $inputs['work_ability']];
            $applyInfo->score = serialize($score);//评分
        }
        if($proces_info->type_id == 7){
            $applyInfo = $proces_info->applyLeave;
        }
        if($proces_info->type_id == 8){
            $applyInfo = $proces_info->applyReport;
        }
        $applyMain = $proces_info->hasMain;
        $record_steps = array();
        if($inputs['pass'] == 1){
            //通过
            $proces_info->status = 1;
        }else{
            //驳回操作
            $step = $proces_info->step;
            $steps_setting = new \App\Models\AuditProcessStep;
            $current_step_setting = $steps_setting->where('setting_id', $proces_info->setting_id)->where('step', $step)->first();//当前步骤驳回配置
            if($current_step_setting->if_reject == 2){
                //结束流程
                $applyInfo->status = 2;
                $applyInfo->status_txt = '已驳回';
                $proces_info->status = 2;
                $applyMain->status = 2;
                $applyMain->status_txt = '已驳回';
            }else{
                //驳回跳到某一步审核
                if($current_step_setting->reject_step_id == 0){
                    //结束流程
                    $applyInfo->status = 2;
                    $applyInfo->status_txt = '已驳回';
                    $proces_info->status = 2;
                    $applyMain->status = 2;
                    $applyMain->status_txt = '已驳回';
                }else if(is_numeric($current_step_setting->reject_step_id) && $current_step_setting->reject_step_id > 0){
                    $audit_record = new \App\Models\AuditProcessStepRecord;
                    $record_list = $audit_record->where('type_id', $proces_info->type_id)
                                    ->where('apply_id', $proces_info->apply_id)
                                    ->orderBy('id', 'asc')
                                    ->get();
                    $record_info = $audit_record->where('type_id', $proces_info->type_id)
                                    ->where('apply_id', $proces_info->apply_id)
                                    ->where('step', 'step'.$current_step_setting->reject_step_id)
                                    ->orderBy('id', 'desc')
                                    ->first();
                    if(empty($record_list)){
                        //结束流程
                        $applyInfo->status = 2;
                        $applyInfo->status_txt = '已驳回';
                        $proces_info->status = 2;
                        $applyMain->status = 2;
                        $applyMain->status_txt = '已驳回';
                    }else{
                        foreach ($record_list as $key => $value) {
                            if($value->id >= $record_info->id){
                                $record_steps[] = $value->step;
                            }
                        }
                        $applyInfo->step = 'step'.$current_step_setting->reject_step_id;
                        $applyMain->status_txt = $current_step_setting->name;
                    }
                }
            }

        }
        $proces_info->audit_opinion = $inputs['audit_opinion'];
        $if_next_proces = false;
        if($proces_info->is_end != 1 && $inputs['pass'] == 1){
            //如果非最后一步审核 则取出下一步审核数据
            $user = new \App\Models\User;
            $dept = new \App\Models\Dept;
            $step = $proces_info->step;
            $steps_setting = new \App\Models\AuditProcessStep;
            $current_step_setting = $steps_setting->where('setting_id', $proces_info->setting_id)->where('step', $step)->first();
            $next_user_ids = array();
            $next_step_setting = array();//根据条件判断 下一步为结束
            if($current_step_setting->step_type == 1 && $current_step_setting->if_condition == 0){
                //过程步骤并且无条件判断
                $next_step_id = $current_step_setting->next_step_id;
                $next_step_setting = $steps_setting->where('setting_id', $proces_info->setting_id)->where('step', 'step'.$next_step_id)->first();//获得下一步配置信息
                
            }else if($current_step_setting->step_type == 1 && $current_step_setting->if_condition == 1){
                //根据条件判断步骤
                $condition2 = unserialize($current_step_setting->condition2);
                foreach ($condition2 as $key => $value) {
                    $symbol = $audit_proces->symbol($applyInfo[$value['name']],$value['value'],$value['symbol']);
                    if(isset($applyInfo[$value['name']]) && is_numeric($applyInfo[$value['name']]) && $symbol){
                        $next_step_id = $value['next_step_id'];
                    }
                }
                $next_step_setting = $steps_setting->where('setting_id', $proces_info->setting_id)->where('step', 'step'.$next_step_id)->first();//获得下一步配置信息

            }else if($current_step_setting->step_type == 2){
                //结束步骤
                $applyInfo->status = 1;
                $applyInfo->status_txt = '已通过';
                $applyMain->status = 1;
                $applyMain->status_txt = '已通过';

            }else if($current_step_setting->step_type == 3){
                $condition1 = unserialize($current_step_setting->condition1);
                $if_end = false;
                foreach ($condition1 as $key => $value) {
                    $symbol = $audit_proces->symbol($applyInfo[$value['name']],$value['value'],$value['symbol']);
                    if(isset($applyInfo[$value['name']]) && is_numeric($applyInfo[$value['name']]) && $symbol && $value['is_end'] == 1){
                        $if_end = true;//当前满足当前步骤条件判断  结束流程
                        $applyInfo->status = 1;
                        $applyInfo->status_txt = '已通过';
                        $applyMain->status = 1;
                        $applyMain->status_txt = '已通过';

                    }
                }
                if(!$if_end && $current_step_setting->if_condition == 0){
                    //不满足当前步骤条件判断  
                    $next_step_id = $current_step_setting->next_step_id;
                    $next_step_setting = $steps_setting->where('setting_id', $proces_info->setting_id)->where('step', 'step'.$next_step_id)->first();//获得下一步配置信息
                }else if(!$if_end && $current_step_setting->if_condition == 1){
                    //不满足当前步骤条件判断  
                    $condition2 = unserialize($current_step_setting->condition2);
                    foreach ($condition2 as $key => $value) {
                        $symbol = $audit_proces->symbol($applyInfo[$value['name']],$value['value'],$value['symbol']);
                        if(isset($applyInfo[$value['name']]) && is_numeric($applyInfo[$value['name']]) && $symbol){
                            $next_step_id = $value['next_step_id'];
                        }
                    }
                    $next_step_setting = $steps_setting->where('setting_id', $proces_info->setting_id)->where('step', 'step'.$next_step_id)->first();//获得下一步配置信息
                }
            }

            if(!empty($next_step_setting) && $next_step_setting->cur_user_id == 1){
                $dept_id = $proces_info->dept_id;
                $dept_info = $dept->where('id', $dept_id)->select(['id','supervisor_id'])->first();
                $next_user_ids = array($dept_info->supervisor_id);
            }else if(!empty($next_step_setting) && $next_step_setting->cur_user_id == 2){
                $dept_id = $next_step_setting->dept_id;
                $rank_id = $next_step_setting->rank_id;
                $users = $user->where('status', 1)->where('dept_id', $dept_id)->where('rank_id', $rank_id)->select(['id'])->get();
                foreach ($users as $key => $value) {
                    $next_user_ids[] = $value->id;
                }
            }else if(!empty($next_step_setting) && $next_step_setting->cur_user_id == 3){
                $role_id = $next_step_setting->role_id;
                $role_list = $user->where('status', 1)->where('position_id', $role_id)->select(['id'])->get()->toArray();
                if(empty($role_list)){
                    return response()->json(['code' => 0,'message' => '没有符合的审核人员']);
                }
                foreach ($role_list as $key => $value) {
                    $next_user_ids[] = $value['id'];
                }
            }else if(!empty($next_step_setting) && $next_step_setting->cur_user_id == 4){
                $next_user_ids = array($next_step_setting->user_id);
            }
            $applyMain->status_txt = $next_step_setting->name;//xx,xx审核中
            $applyInfo->status_txt = $next_step_setting->name;//xx,xx审核中
            $applyInfo->current_verify_user_id = implode(',', $next_user_ids);//修改当前审核人id
            $applyInfo->step = 'step'.$next_step_id;//下一步
            $if_next_proces = true;
            
        }else{
            //如果是最后一步 把主表改成通过状态
            if ($inputs['pass'] == 1) {
                $applyInfo->status = 1;
                $applyInfo->status_txt = '已通过';
                $applyMain->status = 1;
                $applyMain->status_txt = '已通过';
            }
            
        }
        $res = false;
        DB::transaction(function () use ($audit_proces, $applyInfo, $proces_info, $applyMain, $if_next_proces, $record_steps) {
            $applyInfo->save();
            $proces_info->save();
            $applyMain->save();
            if($if_next_proces){
                $audit_proces->where('type_id', $proces_info->type_id)
                            ->where('apply_id',$proces_info->apply_id)
                            ->where('step', $applyInfo->step)
                            ->update(['pre_verify_user_id' => $proces_info->current_verify_user_id]);//将下一步的数据的字段pre_verify_user_id改成当前的操作人员
            }
            if(!empty($record_steps)){
                //驳回之后把之前审核的状态都改为待审核
                $audit_proces->where('type_id', $proces_info->type_id)
                        ->where('apply_id', $proces_info->apply_id)
                        ->whereIn('step', $record_steps)
                        ->update(['status' => 0, 'audit_opinion' => '']);
            }
            //更新当前步骤
            $audit_proces->where('apply_id', $applyInfo->id)->where('type_id', $proces_info->type_id)->update(['current_step' => $applyInfo->step]);

            if($applyInfo->status == 1 && $proces_info->type_id == 1){
                // 出勤申请 通过后生成每每一天信息
                $detail = new \App\Models\ApplyAttendanceDetail;
                $detail->createDetail($applyInfo);
            }
            if($applyInfo->status == 1 && $proces_info->type_id == 4){
                //招聘 通过后生成一条招聘信息
                $detail = new \App\Models\RecruitList;
                $detail->createRecruit($applyInfo);
            }
            if($applyInfo->status == 1 && $proces_info->type_id == 5){
                //培训 通过后生成每个人每个项目的培训信息
                $detail = new \App\Models\TrainingList;
                $detail->createTraining($applyInfo);
            }
            if($applyInfo->status == 1 && $proces_info->type_id == 6){
                //转正通过后  更新转正日期
                $contract = new \App\Models\UserContract;
                $contract_info = $contract->where('user_id', $applyInfo->user_id)->first();
                $contract_info->positive_date = $applyInfo->formal_date;
                $contract_info->save();
            }
            if($applyInfo->status == 1 && $proces_info->type_id == 8){
                //报备 通过后生成每个日期对应的一条记录
                $detail = new \App\Models\AttendanceReport;
                $detail->createReport($applyInfo);
            }
            $apply_types = (new \App\Models\ApplyMain)->apply_types;
            if($proces_info->is_end != 1 && $proces_info->status == 1){
                //消息提醒
                $user_data = (new \App\Models\User)->getIdToData();
                $notice_users = $audit_proces->where('type_id', $proces_info->type_id)
                            ->where('apply_id',$proces_info->apply_id)
                            ->where('step', $applyInfo->step)
                            ->pluck('current_verify_user_id')->toArray();
                addNotice($notice_users, '审核', $user_data['id_realname'][$proces_info->user_id].'提交了一条'.$apply_types[$proces_info->type_id].'，请及时审核', '', 0, 'approval-audit-index','apply/verify');//提醒下一个审核人
            }elseif($proces_info->is_end == 1 && $proces_info->status == 1){
                $notice_users = $audit_proces->where('type_id', $proces_info->type_id)
                            ->where('apply_id',$proces_info->apply_id)
                            ->where('step', $applyInfo->step)
                            ->select(['user_id'])->first();
                addNotice($notice_users->user_id, '审核', '您的'.$apply_types[$proces_info->type_id].'已通过', '', 0, 'approval-index','apply/list');//提醒申请人
            }elseif($proces_info->is_end != 1 && $proces_info->status == 2){
                $notice_users = $audit_proces->where('type_id', $proces_info->type_id)
                            ->where('apply_id',$proces_info->apply_id)
                            ->where('step', $applyInfo->step)
                            ->select(['user_id'])->first();
                addNotice($notice_users->user_id, '审核', '您的'.$apply_types[$proces_info->type_id].'已被驳回至'.$applyMain->status_txt, '', 0, 'approval-index','apply/list');//提醒申请人
            }
            
        }, 5);
        $res = true;
        return $res;
    }

    /**
    * 出勤情况
    * @author molin
    * @date 2018-09-19
    * user_id 申请人id  start_time  入职时间  end_time 申请转正的时间
    */
    public function getAttendance($user_id, $start_time, $end_time){
        //入职时间后一天 考虑到入职当天未打卡  申请提交前一天
        $end_time = date('Y-m-d', strtotime("$end_time -1 day"));//提交前一天
        $start_time = date('Y-m-d', strtotime("$start_time +1 day"));//入职第二天开始算
        $start_time_cr = $start_time.' 00:00:00';
        $end_time_cr = $end_time.' 23:59:59';
        $user = new \App\Models\User;
        $data = $user->queryUserInfo(['user_id'=>$user_id]);
        $attendance_record = new \App\Models\AttendanceRecord;
        //获取考勤记录
        $my_record_list = $attendance_record->where('user_id', $user_id)->whereBetween('punch_time',[$start_time_cr, $end_time_cr])->get();
        //日期对应的打卡记录
        $tmp_record = array();
        foreach ($my_record_list as $key => $value) {
            $tmp_record[date('Y-m-d', strtotime($value['punch_time']))][$value['id']] = $value;
        }
        //假期类型
        $holiday_type = new \App\Models\HolidayType;
        $type_list = $holiday_type->select(['id', 'name'])->get();
        $type_data = array();
        foreach ($type_list as $key => $value) {
            $type_data[$value->id]['name'] = $value->name;
        }

        //请假、外勤申请--用于抵消迟到、未签到、早退、未签退
        $apply_att = new \App\Models\ApplyAttendance;
        $apply_list = $apply_att->where('status', 1)->where('user_id', $user_id)->whereIn('type', [1,3])->get();
        $apply_data = array();
        foreach ($apply_list as $key => $value) {
            $days = prDates(date('Y-m-d', strtotime($value->start_time)), date('Y-m-d', strtotime($value->end_time)));
            foreach ($days as $d) {
                $apply_data[$d][] = $value;
            }
        }

        //请假、外勤、加班详细信息
        $apply_detail = new \App\Models\ApplyAttendanceDetail;
        $detail_list = $apply_detail->whereBetween('date',[$start_time, $end_time])->where('user_id', $user_id)->get();
        $qingjia_list = $jiaban_list = $waiqin_list = array();
        foreach ($detail_list as $key => $value) {
            if($value->type == 1){
                //请假
                $qingjia_list[$value->date][$value->leave_type] = floatval($value->time_str);
            }
            if($value->type == 2){
                //加班
                $jiaban_list[$value->date][] = floatval($value->time_str);
            }
            if($value->type == 3){
                //外勤
                $waiqin_list[$value->date][] = floatval($value->time_str);
            }
        }
        //获取上班时间配置信息
        $setting = new \App\Models\AttendanceSetting;
        $setting_list = $setting->orderBy('created_at', 'asc')->get();

        //日期对应第一次打卡 和最后一次打卡记录
        $date_record_list = array();
        foreach ($tmp_record as $key => $value) {
            //获取上班时间配置
            $setting_info = array();
            foreach ($setting_list as $sett) {
                //取打卡日期大于配置修改的日期
                if(strtotime($key) > strtotime($sett['punch_time'])){
                    $setting_info = $sett;
                }
            }
            if(empty($setting_info)){
                $setting_info = $setting_list[0];
            }
            $am_start_time = strtotime($key.' '.$setting_info['am_start_time'].':00');//当天上班时间
            $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
            $pm_end_time = strtotime($key.' '.$setting_info['pm_end_time'].':00');//当天下班时间
            $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟
            $tmp_arr = array();
            foreach ($value as $k => $v) {
                if(strtotime($v['punch_time']) >= $am_start_before_time && strtotime($v['punch_time']) <= $pm_end_after_time){
                    $tmp_arr[] = strtotime($v['punch_time']); //一天内上班时间内的打卡记录
                }
            }

            $first = date('H:i:s', min($tmp_arr));//第一次打卡时间
            $last = date('H:i:s', max($tmp_arr));//最后一次打卡时间
            $date_record_list[$key]['first'] = $first;
            $date_record_list[$key]['last'] = $last;

        }

        //节假日、工作日列表
        $attendance =  new \App\Models\Attendance;
        $attendance_list = $attendance->whereBetween('date',[$start_time, $end_time])->get();
        //拼装我的考勤数组
        $items = array();
        //旷工
        $queqin_total = 0;
        //迟到、早退、未签到、未签退
        $chidao_num = $zaotui_num = $chidao_sum = $zaotui_sum = 0;
        $jiaqi_total = $jiaqi_time = array();
        foreach ($attendance_list as $key => $value) {
            //获取上班时间配置
            $setting_info = array();
            foreach ($setting_list as $sett) {
                //取打卡日期大于配置修改的日期
                if(strtotime($value['date']) > strtotime($sett['created_at'])){
                    $setting_info = $sett;
                }
            }
            if(empty($setting_info)){
                $setting_info = $setting_list[0];
            }
            $a_time = $setting_info['am_start_time'];
            $p_time = $setting_info['pm_end_time'];
            $am_start_time = strtotime($value['date'].' '.$a_time.':00');//当天上班时间
            $am_end_time = strtotime($value['date'].' '.$setting_info['am_end_time'].':00');//当天上午下班时间
            $pm_start_time = strtotime($value['date'].' '.$setting_info['pm_start_time'].':00');//当天下午下班时间
            $pm_end_time = strtotime($value['date'].' '.$p_time.':00');//当天下班时间
            $am_start_before_time = $am_start_time - $setting_info['am_start_before_time'] * 60;//上班前多少分钟
            $am_start_after_time = $am_start_time + $setting_info['am_start_after_time'] * 60;//上班后多少分钟
            $pm_end_before_time = $pm_end_time - $setting_info['pm_end_before_time'] * 60;//下班前多少分钟
            $pm_end_after_time = $pm_end_time + $setting_info['pm_end_after_time'] * 60;//下班后多少分钟

            $queqin_num = 0;//缺勤次数
            $cur_day_time = $jiaban_day_time = 0;
            $chidao_time = $zaotui_time = 0;
            //假期
            $shijia = $bingjia = $nianjia = $hunjia = $chanjia = $sangjia = $qita = 0;
            if(isset($date_record_list[$value['date']]) && $value['type'] == 0){
                //工作日
                $first_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['first']);//第一次打卡时间
                $last_time = strtotime($value['date'].' '.$date_record_list[$value['date']]['last']);//最后一次打卡时间
                if(!empty($first_time)){
                    //正常出勤工时
                    if($first_time <= ($am_start_time+59) && $last_time >= $pm_end_time){
                        //正常打卡
                        $shangwu_time = ($am_end_time - $am_start_time) / 3600;//小时
                        $xiawu_time = ($pm_end_time - $pm_start_time) / 3600;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $last_time >= $pm_end_time){
                        //早上迟到
                        $shangwu_time = ($am_end_time - $first_time) / 3600;//小时
                        $xiawu_time = ($pm_end_time - $pm_start_time) / 3600;//小时
                    }else if($first_time >= $am_end_time && $first_time <= ($pm_start_time+59) && $last_time >= $pm_end_time){
                        //中午第一次打卡
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($pm_end_time - $pm_start_time) / 3600;//小时
                    }else if($first_time > ($pm_start_time+59) && $first_time < $pm_end_time && $last_time >= $pm_end_time){
                        //下午第一次打卡
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($pm_end_time - $first_time) / 3600;//小时
                    }else if($first_time <= ($am_start_time+59) && $last_time > ($am_start_time+59) && $last_time < $am_end_time){
                        //第一次打卡正常 最后一次打卡在早上
                        $shangwu_time = ($last_time - $am_start_time) / 3600;//小时
                        $xiawu_time = 0;//小时
                    }else if($first_time <= ($am_start_time+59) && $last_time >= $am_end_time && $last_time < $pm_start_time){
                        //第一次打卡正常 最后一次打卡在中午
                        $shangwu_time = ($am_end_time - $am_start_time) / 3600;//小时
                        $xiawu_time = 0;//小时
                    }else if($first_time <= ($am_start_time+59) && $last_time >= $pm_start_time && $last_time < $pm_end_time){
                        //第一次打卡正常 最后一次打卡在下午
                        $shangwu_time = ($am_end_time - $am_start_time) / 3600;//小时
                        $xiawu_time =  ($last_time - $pm_start_time) / 3600;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $first_time < $last_time && $last_time <= $am_end_time){
                        //第一次迟到或者未签到 最后一次打卡在上午下班前
                        $shangwu_time = ($last_time - $first_time) / 3600;//小时
                        $xiawu_time =  0;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $first_time < $last_time && $last_time > $am_end_time && $last_time <= ($pm_start_time+59)){
                        //第一次迟到或者未签到 最后一次打卡在中午
                        $shangwu_time = ($am_end_time - $first_time) / 3600;//小时
                        $xiawu_time =  0;//小时
                    }else if($first_time > ($am_start_time+59) && $first_time < $am_end_time && $first_time < $last_time && $last_time > ($pm_start_time+59) && $last_time < $pm_end_time){
                        //第一次迟到或者未签到 最后一次打卡在下午下班前
                        $shangwu_time = ($am_end_time - $first_time) / 3600;//小时
                        $xiawu_time = ($last_time - $pm_start_time) / 3600;//小时
                    }else if($first_time >= $am_end_time && $first_time <= ($pm_start_time+59) && $first_time < $last_time && $last_time > ($pm_start_time+59) && $last_time < $pm_end_time){
                        //第一次中午 最后一次打卡在下午下班前
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($last_time - $pm_start_time) / 3600;//小时
                    }else if($first_time >= ($pm_start_time+59) && $first_time < $last_time && $last_time < $pm_end_time){
                        //第一次打卡在下午上班后 最后一次打卡在下午下班前
                        $shangwu_time = 0;//小时
                        $xiawu_time = ($last_time - $first_time) / 3600;//小时
                    }else{
                        //在有效打卡时间内 早退
                        $shangwu_time = 0;//小时
                        $xiawu_time = 0;//小时
                    }
                    $cur_day_time = intval($shangwu_time + $xiawu_time);//出勤工时
                    //迟到、早退、未签到、未签退
                    //首先判断当天是否正常上班和正常下班  
                    $start_zhengchang = false;
                    $end_zhengchang = false;
                    if($first_time >= $am_start_before_time && $first_time <= ($am_start_time + 59)){
                        //打卡时间在上班前有效时间内 属于正常打卡
                        $start_zhengchang = true;//正常上班
                    }
                    if($last_time >= $pm_end_time && $last_time <= ($pm_end_after_time)){
                        //打卡时间在下班前有效时间内 属于正常打卡
                        $end_zhengchang = true;//正常下班
                    }
                    $if_chidao = false;//是否迟到
                    $if_zaotui = false;//是否早退
                    $if_queqin = false;//是否异常考勤
                    if(!$start_zhengchang && $end_zhengchang){
                        //未正常上班  但是正常下班
                        // 迟到
                        if($first_time < $am_start_after_time && $first_time > $am_start_time){
                            //第一个打卡记录大于上班时间 小于有效时间  迟到
                            $if_chidao = true; 
                            //计算迟到时间  第一次打卡时间 - 上班时间 = 迟到时间
                            $chidao_time = floor(($first_time - $am_start_time) / 60);
                        }
                        //是否异常缺勤
                        if($first_time >= $am_start_after_time && $first_time < $pm_end_before_time){
                            //第一次打卡在10:30-16:30
                            $if_queqin = true;//判断缺勤
                        } 
                        //查看是否有请假、外勤
                        if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                            if($am_start_time >= strtotime($app->start_time) && $am_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除迟到、未签到记录、迟到时间
                                $if_chidao = false; 
                                $chidao_time = 0;
                                $if_queqin = false;
                            }
                        }}
                        if($first_time > $pm_end_before_time && $first_time < $pm_end_after_time){
                            //第一次打卡在16:30-00:00  有请假和外勤也不能抵消  只有报备才能抵消 
                            $if_queqin = true;
                        }
                    }else if($start_zhengchang && !$end_zhengchang){
                        //正常上班  但是未正常下班
                        //早退
                        if($last_time >= $pm_end_before_time && $last_time < $pm_end_time){
                            //最后一个打卡记录大于下班前有效时间小于下班时间  早退
                            $if_zaotui = true; 
                            //计算早退时间  下班时间 - 最后一次打卡时间 = 早退时间
                            $zaotui_time = floor(($pm_end_time - $last_time) / 60);
                        }
                        //是否异常缺勤
                        if($last_time > $am_start_after_time && $last_time < $pm_end_before_time){
                            //最后一次打卡在10:30-16:30
                            $if_queqin = true;//判断缺勤
                        }
                        //查看是否有请假、外勤
                        if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                            if($pm_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除早退、未签退记录、早退时间
                                $if_zaotui = false; 
                                $zaotui_time = 0;
                                $if_queqin = false;
                            }
                        }}
                        if($last_time > $am_start_before_time && $last_time < $am_start_after_time){
                            //最后一次打卡在07:30-10:30  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                        }
                    }else if(!$start_zhengchang && !$end_zhengchang){
                        //未正常上班  也未正常下班
                        // 迟到
                        if($first_time < $am_start_after_time && $first_time > $am_start_time){
                            //第一个打卡记录大于上班时间 小于有效时间  迟到
                            $if_chidao = true; 
                            //计算迟到时间  第一次打卡时间 - 上班时间 = 迟到时间
                            $chidao_time = floor(($first_time - $am_start_time) / 60);
                        }
                        //早退
                        if($last_time >= $pm_end_before_time && $last_time < $pm_end_time){
                            //最后一个打卡记录大于下班前有效时间小于下班时间  早退
                            $if_zaotui = true; 
                            //计算早退时间  下班时间 - 最后一次打卡时间 = 早退时间
                            $zaotui_time = floor(($pm_end_time - $last_time) / 60);
                        }
                        if($first_time > $am_start_after_time && $first_time < $pm_end_before_time && $last_time > $am_start_after_time && $last_time < $pm_end_before_time){
                            //第一次和最后一次都在 10:30-16:30之间
                            $if_queqin = true;
                        }
                        //查看是否有请假、外勤
                        if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                            if($am_start_time >= strtotime($app->start_time) && $am_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除迟到、未签到记录、迟到时间
                                $if_chidao = false; 
                                $chidao_time = 0;
                            }
                            if($pm_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                                //如果有请假外勤记录 则清除早退、未签退记录、早退时间
                                $if_zaotui = false; 
                                $zaotui_time = 0;
                            }
                            if(strtotime($app->start_time) <= $am_start_time && strtotime($app->end_time) >= $pm_end_time){
                                $if_queqin = false;//请假一天 抵消异常缺勤
                            }
                        }}
                        if($first_time > $pm_end_before_time && $first_time < $pm_end_after_time){
                            //第一次打卡在16:30-00:00  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                        }
                        if($last_time > $am_start_before_time && $last_time < $am_start_after_time){
                            //最后一次打卡在07:30-10:30  有请假和外勤也不能抵消  只有报备才能抵消
                            $if_queqin = true;
                        }
                    }
                    if($if_chidao){
                        $chidao_num ++;
                    }
                    if($if_zaotui){
                        $zaotui_num ++;
                    }
                    if($if_queqin){
                        $queqin_num ++;
                    }
                    $chidao_sum += $chidao_time;
                    $zaotui_sum += $zaotui_time;
                }
            }
            if(isset($jiaban_list[$value['date']])){
                $jiaban_day_time = array_sum($jiaban_list[$value['date']]);
            }
            
            if($value['type'] == 0){
                //旷工天数
                if(!isset($date_record_list[$value['date']])){
                    //工作日无打卡记录 
                    $if_queqin = true;
                    if(isset($apply_data[$value['date']])){foreach ($apply_data[$value['date']] as $app) {
                        if($am_start_time >= strtotime($app->start_time) && $pm_end_time <= strtotime($app->end_time)){
                            $if_queqin = false;
                        }
                    }}
                    if($if_queqin){
                        $queqin_num++;
                    }
                }
            }
            $queqin_total += $queqin_num; //异常缺勤

            //计算请假(工作日)
            if($value['type'] == 0 && isset($qingjia_list[$value['date']]) && is_array($qingjia_list[$value['date']])){
                foreach ($qingjia_list[$value['date']] as $kk => $vv) {
                    $jiaqi_time[$kk]['num'] = $jiaqi_time[$kk]['num'] ?? 0;
                    $jiaqi_time[$kk]['num'] += $vv;
                }
            }
            foreach ($type_data as $type_id => $v) {
                if(isset($jiaqi_time[$type_id])){
                    $jiaqi_total[$type_id]['num'] = $jiaqi_time[$type_id]['num'];
                    $jiaqi_total[$type_id]['name'] = $v['name'];
                }else{
                    $jiaqi_total[$type_id]['num'] = 0;
                    $jiaqi_total[$type_id]['name'] = $v['name'];
                }
            }
        }
        
        
        $items['chidao_sum'] = $chidao_sum;//迟到
        $items['zaotui_sum'] = $zaotui_sum;//早退
        $items['queqin_total'] = $queqin_total;//缺勤工时
        foreach ($type_data as $type_id => $val) {
            $items['jiaqi_'.$type_id] = $jiaqi_total[$type_id]['num'] ?? 0;
        }
        $fields = array_keys($items);

        $table_head = ['chidao_sum'=> '迟到时间','zaotui_sum'=>'早退时间','queqin_total'=>'异常考勤(次数)','chufa'=>'处罚','jiangli'=>'奖励'];
        $tmp = [];
        foreach ($fields as $value) {
            if(substr($value, 0, 6) == 'jiaqi_'){
                $tmp[$value] = $type_data[substr($value,6)]['name'];
            }
        }

        $items['chufa'] = 0;
        //奖励
        $year = date('Y', strtotime($end_time));
        $reward = new \App\Models\Reward;
        $reward_data = $reward->getRewardData(['year_elt'=>$year]);
        $items['jiangli'] = $reward_data[$user_id] ?? 0;
        $headers = array_merge($table_head, $tmp);
        
        return ['table_head'=>$headers, 'table_body'=>$items];
    } 

    /** 
    *  首页-我的申请
    *  @author molin
    *   @date 2019-01-25
    */
    public function mine(){
        $inputs = request()->all();
        $inputs['user_id'] = auth()->user()->id;
        if(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time'])){
            if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
                return response()->json(['code' => -1, 'message' => '开始时间必须小于结束时间']);
            }   
        }
        $mains = new \App\Models\ApplyMain;
        $apply_list = $mains->getQueryList($inputs);

        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        //1出勤申请 2物品领用3采购申请4招聘申请5培训申请6转正申请7离职申请
        $applys = (new \App\Models\ApplyType)->getTypes();
        $items = array();
        foreach ($apply_list['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['apply_id'] = $value->apply_id;
            $tmp['type_id'] = $value->type_id;
            $tmp['apply_type'] = $applys[$value->type_id];
            $tmp['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $tmp['status_txt'] = $value->status_txt;
            $tmp['content'] = $value->content;
            $items[] = $tmp;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $items]);
    }
}

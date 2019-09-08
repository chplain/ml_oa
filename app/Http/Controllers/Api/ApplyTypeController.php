<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyTypeController extends Controller
{
    //
    /** 
    *  表单申请类型
    *  @author molin
    *	@date 2018-09-21
    */
    public function index(){
    	$inputs = request()->all();
    	$apply_type = new \App\Models\ApplyType;
    	$list = $apply_type->getDataList($inputs);
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

    /** 
    *  表单申请类型--启用、禁用
    *  @author molin
    *   @date 2018-11-12
    */
    public function enable(){
        $inputs = request()->all();
        $apply_type = new \App\Models\ApplyType;
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        if(!isset($inputs['if_use']) || !is_numeric($inputs['if_use']) || !in_array($inputs['if_use'], [0, 1])){
            return response()->json(['code' => -1, 'message' => '缺少参数if_use']);
        }
        $result = $apply_type->if_enable($inputs);
        $log_txt = $inputs['if_use'] == 1 ? '启用' : '禁用';
        if($result){
            systemLog('表单管理', $log_txt.'了表单');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /** 
    *  流程编辑--审核人员设置
    *  @author molin
    *	@date 2018-09-26
    */
    public function edit(){
    	$inputs = request()->all();
    	$setting = new \App\Models\ApplyProcessSetting;
        $data = array();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'process'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id', 'data' => null]);
            }
    		// 获取最新配置信息
	    	$setting_info = $setting->where('type_id', $inputs['id'])->orderBy('id', 'desc')->first();
	    	if(!empty($setting_info['setting_content'])){
	    		$setting_info['setting_content'] = unserialize($setting_info['setting_content']);
	    	}else{
                $setting_info['setting_content'] = null;
            }
            if(!empty($setting_info['apply_setting'])){
                $setting_info['apply_setting'] = unserialize($setting_info['apply_setting']);
            }else{
                $setting_info['apply_setting'] = null;
            }
            $data['id'] = $inputs['id'];
            unset($setting_info['id']);
            $data['setting_info'] = $setting_info;
            //获取部门 ，负责人不为空
            $dept = new \App\Models\Dept;
            $dept_list = $dept->where('status', 1)->where('supervisor_id', '>', 0)->select(['id', 'name'])->get();
            $data['depts'] = $dept_list;
            //获取职级
            $rank = new \App\Models\Rank;
            $rank_list = $rank->where('status', 1)->select(['id', 'name'])->get();
            $data['ranks'] = $rank_list;
            //获取岗位
            $role = new \App\Models\Position;
            $role_list = $role->select(['id', 'name'])->get();
            $data['roles'] = $role_list;
            //获取人员
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['users'] = $user_list;

            $fields_judge = [];
            //根据表单类型  拿字段
            switch ($inputs['id']) {
                case 1:
                    # 出勤申请单
                    $fields = ['type' => '申请类型', 'time' => '请假时间', 'leave_type' => '请假类型', 'outside_addr' => '外出地点', 'leave_time' => '共(天)', 'time_str' => '共(小时)', 'remarks' => '事由'];
                    $fields_judge = ['leave_time' => '共(天)', 'time_str' => '共(小时)'];
                    break;
                case 2:
                    # 物品领用申请单
                    $fields = ['content' => '物品领用', 'uses' => '用途'];
                    break;
                case 3:
                    # 采购申请单
                    $fields = ['type_id' => '采购类型', 'cate_id' => '大类', 'goods_id' => '小类', 'goods_name' => '物品名称', 'num' => '数量', 'spec' => '规格', 'images' => '图片', 'uses' => '用途', 'degree_id' => '紧急情况', 'rdate' => '最后期限'];
                    $fields_judge = ['num' => '数量'];
                    break;
                case 4:
                    # 招聘申请单
                    $fields = ['number' => '人数', 'post' => '岗位', 'reason' => '理由', 'type' => '紧急程度', 'duty' => '职责说明', 'demand' => '岗位要求', 'salary' => '薪资范围'];
                    $fields_judge = ['number' => '人数'];
                    break;
                case 5:
                    # 培训申请单
                    $fields = ['name' => '培训名称', 'type' => '培训类型', 'addr_id' => '培训地点', 'content' => '培训内容', 'time' => '培训时间', 'training' => '培训人或被培训人'];
                    
                    break;
                case 6:
                    # 转正申请单
                    $fields = ['work_content' => '工作内容', 'work_ok' => '完成情况', 'work_learn' => '学到的技能', 'work_plan' => '学习计划', 'score' => '评分'];
                    break;
                case 7:
                    # 离职申请单
                    $fields = ['leave_date' => '离职日期', 'leave_reason' => '离职理由'];
                    break;
                case 8:
                    # 报备申请单
                    $fields = ['content' => '报备日期'];
                    break;
                case 9:
                    # 链接申请单
                    $fields = ['degree_id' => '紧急情况', 'remarks' => '说明情况'];
                    break;
                default:
                    # code...
                    break;
            }
            $data['fields'] = $fields;
            $data['fields_judge'] = $fields_judge;
            $cur_verify_arr = [1=>'部门负责人', 2=>'部门和职级', 3=>'岗位', 4=>'指定人员'];
            $cur_step_arr = [1=>'过程步骤', 2=>'结束步骤', 3=>'根据条件判断'];
            $reject_arr = [1=>'选择步骤ID', 2=>'结束流程'];
            $symbol_arr = ['>' => '大于', '=' => '等于', '<' => '小于', '>=' => '大于等于', '<=' => '小于等于', '!=' => '不等于'];
            $data['cur_verify_arr'] = $cur_verify_arr;
            $data['cur_step_arr'] = $cur_step_arr;
            $data['symbol_arr'] = $symbol_arr;
            $data['reject_arr'] = $reject_arr;
	    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
        

    	$rules = [
            'id' => 'required|integer',
            'apply_setting' => 'required|array',
            'setting_content' => 'required|array'
        ];
        $attributes = [
            'id' => '表单id',
            'apply_setting' => '提交说明',
            'setting_content' => 'setting_content'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $step_data = $controls_data = array();
    	foreach ($inputs['setting_content'] as $key => $value) {
            $response = $this->inputsChecked($key, $value);
            if($response['code'] != 1){
                return response()->json($response);
            }
    		$return = $this->getStepData($key, $value);
            $step_data[] = $return['step'];
            $controls_data[] = $return['controls'];
    	}
        switch ($inputs['id']) {
            case 1:
                # 出勤申请单
                $log_txt = '出勤申请';
                break;
            case 2:
                # 物品领用申请单
                $log_txt = '物品领用申请';
                break;
            case 3:
                # 采购申请单
                $log_txt = '采购申请';
                break;
            case 4:
                # 招聘申请单
                $log_txt = '招聘申请';
                break;
            case 5:
                # 培训申请单
                $log_txt = '培训申请';
                
                break;
            case 6:
                # 转正申请单
                $log_txt = '转正申请';
                break;
            case 7:
                # 离职申请单
                $log_txt = '离职申请';
                break;
            case 8:
                # 报备申请单
                $log_txt = '报备申请';
                break;
            case 9:
                # 链接申请单
                $log_txt = '链接申请';
                break;
        }
        // dd($inputs);
    	$result = $setting->storeData($inputs,$step_data,$controls_data);
    	if($result){
            systemLog('表单管理', '编辑了流程配置['.$log_txt.']');
    		return response()->json(['code' => 1, 'message' => '保存成功']);
    	}
        return response()->json(['code' => 0, 'message' => '保存失败']);
    }

    /** 
    *  流程编辑--审核人员设置
    *  @author molin
    *	@date 2018-09-26
    */
    public function inputsChecked($key,$inputs){

        if(!isset($inputs['name']) || empty($inputs['name'])){
            return $response = ['code' => -1, 'message' => $key.'->步骤说明必填'];
        }
        if(isset($inputs['cur_user_id']) && !empty($inputs['cur_user_id'])){
            if($inputs['cur_user_id'] == 2){
                if(!isset($inputs['dept_id']) || !is_numeric($inputs['dept_id']) || !isset($inputs['rank_id']) || !is_numeric($inputs['rank_id'])){
                    return $response = ['code' => -1, 'message' => $key.'->部门字段、职级必选'];
                }
            }
            if($inputs['cur_user_id'] == 3){
                if(!isset($inputs['role_id']) || !is_numeric($inputs['role_id'])){
                    return $response = ['code' => -1, 'message' => $key.'->岗位必选'];
                }
            }
            if($inputs['cur_user_id'] == 4){
                if(!isset($inputs['user_id']) || !is_numeric($inputs['user_id'])){
                    return $response = ['code' => -1, 'message' => $key.'->指定人员必选'];
                }
            }
        }

        if(!isset($inputs['fields']) || empty($inputs['fields'])){
            return $response = ['code' => -1, 'message' => $key.'->表单控件可见可编辑权限必填'];
        }

        if(!isset($inputs['step_type']) || empty($inputs['step_type'])){
            return $response = ['code' => -1, 'message' => $key.'->步骤类型必选'];
        }   

        if(isset($inputs['step_type']) && !empty($inputs['step_type'])){
            if($inputs['step_type'] == 3 && (!isset($inputs['condition1']) || empty($inputs['condition1']) || !is_array($inputs['condition1'])) ){
                return $response = ['code' => -1, 'message' => $key.'->条件判断必须填写'];
            }
            if($inputs['step_type'] == 3 && !empty($inputs['condition1']) ){
                foreach ($inputs['condition1'] as $kk => $vv) {
                    if(!isset($vv['name']) || empty($vv['name'])){
                        return $response = ['code' => -1, 'message' => $key.'->缺少字段name'];
                    }
                    if(!isset($vv['symbol']) || empty($vv['symbol'])){
                        return $response = ['code' => -1, 'message' => $key.'->缺少symbol'];
                    }
                    if(!isset($vv['value']) || empty($vv['value'])){
                        return $response = ['code' => -1, 'message' => $key.'->缺少value'];
                    }
                    if(!isset($vv['is_end']) || !is_numeric($vv['is_end'])){
                        return $response = ['code' => -1, 'message' => $key.'->缺少is_end'];
                    }
                }
            }
            if(in_array($inputs['step_type'], [1, 3])){
                if((!isset($inputs['if_condition']) || empty($inputs['if_condition'])) && empty($inputs['next_step_id'])){
                    return $response = ['code' => -1, 'message' => $key.'->下一步ID必须填写'];
                }
                if(isset($inputs['if_condition']) && $inputs['if_condition'] == 1 && (!isset($inputs['condition2']) || empty($inputs['condition2']) || !is_array($inputs['condition2']))){
                    return $response = ['code' => -1, 'message' => $key.'->判断条件必须填写'];
                }
                if(!empty($inputs['condition2']) && is_array($inputs['condition2'])){
                    foreach ($inputs['condition2'] as $kk => $vv) {
                        if(!isset($vv['name']) || empty($vv['name'])){
                            return $response = ['code' => -1, 'message' => $key.'->缺少字段name'];
                        }
                        if(!isset($vv['symbol']) || empty($vv['symbol'])){
                            return $response = ['code' => -1, 'message' => $key.'->缺少symbol'];
                        }
                        if(!isset($vv['value']) || empty($vv['value'])){
                            return $response = ['code' => -1, 'message' => $key.'->缺少value'];
                        }
                        if(!isset($vv['next_step_id']) || empty($vv['next_step_id'])){
                            return $response = ['code' => -1, 'message' => $key.'->缺少next_step_id'];
                        }
                    }
                }
            }
        }
        if(!isset($inputs['if_reject']) || empty($inputs['if_reject'])){
            return $response = ['code' => -1, 'message' => $key.'->驳回操作必选'];
        }

        if(isset($inputs['if_reject']) && !empty($inputs['if_reject'])){
            if($inputs['if_reject'] == 1 && empty($inputs['reject_step_id'])){
                return $response = ['code' => -1, 'message' => $key.'->驳回步骤ID必填'];
            }
        }

    	return $response = ['code' => 1, 'message' => '验证通过'];
    }

    public function getStepData($key,$inputs){
        $insert = array();
        $insert['step'] = $key;//第几步
        if(isset($inputs['name']) && !empty($inputs['name'])){
            $insert['name'] = $inputs['name'];//步骤名称
        }
        if(isset($inputs['cur_user_id']) && !empty($inputs['cur_user_id'])){
            $insert['cur_user_id'] = $inputs['cur_user_id'];//当前审核人
            if($inputs['cur_user_id'] == 2){
                $insert['dept_id'] = $inputs['dept_id'];//部门id
                $insert['rank_id'] = $inputs['rank_id'];//职级id
            }
            if($inputs['cur_user_id'] == 3){
                if(isset($inputs['role_id']) && !empty($inputs['role_id'])){
                    $insert['role_id'] = $inputs['role_id'];//岗位
                }
            }
            if($inputs['cur_user_id'] == 4){
                if(isset($inputs['user_id']) && !empty($inputs['user_id'])){
                    $insert['user_id'] = $inputs['user_id'];//指定审核人
                }
            }
        }
        //表单字段控制
        $controls = array();//控件的是否显示 是否可编辑
        if(isset($inputs['fields']) && !empty($inputs['fields'])){
            $i = 0;
            foreach ($inputs['fields'] as $k => $field) {
                $controls[$i]['name'] = $k;//控件name
                $controls[$i]['step'] = $key; //步骤
                $controls[$i]['if_show'] = $field['if_show'];//默认显示
                $controls[$i]['if_edit'] = $field['if_edit']; //默认可修改
                $i++;
            }
        }
        //当前步骤类型
        if(isset($inputs['step_type']) && !empty($inputs['step_type'])){
            $insert['step_type'] = $inputs['step_type'];//步骤类型
            if($inputs['step_type'] == 3){
                $insert['condition1'] = serialize($inputs['condition1']);
            }
            if(in_array($inputs['step_type'], [1, 3])){
                if(!isset($inputs['if_condition']) || empty($inputs['if_condition'])){
                    $insert['next_step_id'] = $inputs['next_step_id'];//过程步骤 下一步
                }else{
                    $insert['condition2'] = serialize($inputs['condition2']);
                }
                $insert['if_condition'] = $inputs['if_condition'] ?? 0;//是否勾选了条件判断
            }
        }
        //驳回
        if(isset($inputs['if_reject']) && !empty($inputs['if_reject'])){
            $insert['if_reject'] = $inputs['if_reject'];
            if($inputs['if_reject'] == 1){
                $insert['reject_step_id'] = $inputs['reject_step_id'];//驳回到哪一步
            }
        }
        return ['step'=> $insert, 'controls' => $controls];
    }

}

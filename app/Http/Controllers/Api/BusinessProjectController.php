<?php

namespace App\Http\Controllers\Api;

use App\Handles\V3Api;
use App\Models\BusinessOrderLink;
use App\Models\BusinessOrderPrice;
use App\Models\BusinessProject;
use App\Models\BusinessProjectLog;
use App\Models\BusinessProjectLogType;
use App\Models\LinkFeedback;
use App\Models\ProjectFeedback;
use App\Models\ProjectFeedbackLog;
use App\Models\ProjectGroup;
use App\Models\Trade;
use App\Models\User;
use App\Models\V3ProjectTaskStat;
use App\Models\V3ProjectTaskTpl;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use phpDocumentor\Reflection\DocBlock\Tags\Author;

class BusinessProjectController extends Controller
{
    //EDM-项目管理
    const LINK_FEEDBACK = 'link_feedback';

    /**
     *  项目列表
     * @author molin
     * @date 2019-01-18
     */
    public function index()
    {
        $inputs = request()->all();
        $project = new \App\Models\BusinessProject;
        $user = new \App\Models\User;
        $group = new \App\Models\ProjectGroup;
        $user_data = $user->getIdToData();
        $data = [];
        if (isset($inputs['request_type']) && $inputs['request_type'] == 'view') {
            if (!isset($inputs['id']) || !is_numeric($inputs['id'])) {
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $business_order = new \App\Models\BusinessOrder;
            $settlement_type = $send_group = $deliver_type = $cooperation_cycle = $resource_arr = $income_main_arr = [];
            
            foreach ($business_order->settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name'];
            }
            // $send_groups = $business_order->send_groups;
            $send_groups = $group->select(['id','name'])->get()->toArray();
            foreach ($send_groups as $key => $value) {
                $send_group[$value['id']] = $value['name'];
            }
            foreach ($business_order->deliver_types as $key => $value) {
                $deliver_type[$value['id']] = $value['name'];
            }
            foreach ($business_order->cooperation_cycles as $key => $value) {
                $cooperation_cycle[$value['id']] = $value['name'];
            }
            foreach ($business_order->resource_types as $key => $value) {
                $resource_arr[$value['id']] = $value['name'];
            }
            foreach ($business_order->income_main_types as $key => $value) {
                $income_main_arr[$value['id']] = $value['name'];
            }
            $project_info = $project->getProjectInfo($inputs);
            if(empty($project_info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            
            $items = [];
            $items['swd_id'] = $project_info->hasOrder->swd_id;
            $items['customer_name'] = $project_info->hasCustomer->customer_name;
            $items['customer_type'] = $project_info->hasCustomer->customer_type == 1 ? '直客' : '渠道';
            $items['customer_tel'] = $project_info->hasCustomer->customer_tel;
            $items['customer_qq'] = $project_info->hasCustomer->customer_qq;
            $items['customer_email'] = $project_info->hasCustomer->customer_email;
            $items['customer_address'] = $project_info->hasCustomer->customer_address;
            $items['contacts'] = $project_info->hasCustomer->contacts;
            $items['bank_accounts'] = $project_info->hasCustomer->bank_accounts;
            $items['project_name'] = $project_info->project_name;
            $items['trade'] = $project_info->trade->name;
            $items['test_cycle'] = $project_info->hasOrder->test_cycle;
            $order_settlement_type = array();
            foreach (explode(',', $project_info->hasOrder->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $items['settlement_type'] = implode(',', $order_settlement_type);
            $links = [];
            if (count($project_info->hasLink) > 0) {
                foreach ($project_info->hasLink as $key => $val) {
                    $links[$key]['id'] = $val['id'];
                    $links[$key]['link_type'] = $val['link_type'] == 1 ? '分链接' : '自适应';
                    $links[$key]['link_name'] = $val['link_name'];
                    $links[$key]['pc_link'] = $val['pc_link'];
                    $links[$key]['wap_link'] = $val['wap_link'];
                    $links[$key]['zi_link'] = $val['zi_link'];
                    $links[$key]['remarks'] = $val['remarks'];
                    $links[$key]['if_use'] = $val['if_use'];
                    $links[$key]['project_name'] = $project_info->project_name;
                    $links[$key]['pricing_manner'] = $val['pricing_manner'];
                    $links[$key]['market_price'] = unserialize($val['market_price']);
                    $links[$key]['created_at'] = $val['created_at']->format('Y-m-d H:i:s');
                    $links[$key]['updated_at'] = $val['updated_at']->format('Y-m-d H:i:s');
                }
            }

            $items['links'] = $links;
            $items['sale_man'] = $user_data['id_realname'][$project_info->sale_man];
            $items['business'] = $user_data['id_realname'][$project_info->business_id];
            $items['assistant'] = $user_data['id_realname'][$project_info->assistant_id];
            $items['charge'] = $user_data['id_realname'][$project_info->charge_id];
            $items['execute'] = $user_data['id_realname'][$project_info->execute_id];
            $items['if_check'] = $project_info->if_check;
            $items['if_xishu'] = $project_info->if_xishu;
            $items['send_group'] = $send_group[$project_info->group_id] ?? '未分配组';
            $items['deliver_type'] = $deliver_type[$project_info->deliver_type];
            $items['cooperation_cycle'] = $cooperation_cycle[$project_info->cooperation_cycle];
            $items['resource_type'] = $resource_arr[$project_info->resource_type];
            $items['income_main_type'] = $income_main_arr[$project_info->income_main_type];
            $items['allowance_date'] = $project_info->allowance_date;
            $items['has_v3'] = $project_info->has_v3;
            $items['username'] = $project_info->username;
            $items['password'] = $project_info->password;
            if ($project_info->status == 0) {
                $items['status_txt'] = '待投递';
            } elseif ($project_info->status == 1) {
                $items['status_txt'] = '投递中';
            } elseif ($project_info->status == 2) {
                $items['status_txt'] = '投递完毕';
            } elseif ($project_info->status == 3) {
                $items['status_txt'] = '投递暂停';
            }

            $data['project_info'] = $items;
            $data['msg'] = '';
            if($project_info->charge_id == 1 || $project_info->business_id == 1 || $project_info->assistant_id == 1 || $project_info->execute_id == 1){
                $data['msg'] = '当前项目负责人/执行/商务/商务助理为超级管理员,请设置其他人员';
            }
            $suspend =  new \App\Models\BusinessProjectSuspend;
            $suspend_list = $suspend->where('project_id', $inputs['id'])->pluck('date')->toArray();
            sort($suspend_list);
            $data['suspend_list'] = $suspend_list;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

        $data = $project->getDataList($inputs);
        foreach ($data['datalist'] as $key => $value) {
            $tmp = [];
            $tmp['id'] = $value->id;
            $tmp['trade'] = $value->trade->name;
            $tmp['project_name'] = $value->project_name;
            $tmp['company'] = $value->hasCustomer->customer_name;
            $tmp['sale_man'] = $user_data['id_realname'][$value->sale_man];
            if ($value->status == 0) {
                $tmp['status_txt'] = '待投递';
            } elseif ($value->status == 1) {
                $tmp['status_txt'] = '投递中';
            } elseif ($value->status == 2) {
                $tmp['status_txt'] = '投递完毕';
            } elseif ($value->status == 3) {
                $tmp['status_txt'] = '投递暂停';
            }
            if (count($value->hasCustomer->contract) > 0) {
                $contract = '';
                $i = 0;
                foreach ($value->hasCustomer->contract as $val) {
                    if ($i < $val->id) {
                        $contract = $val->number;
                    }
                    $i = $val->id;
                }
                $tmp['contract_txt'] = $contract;//最新的合同编号
            } else {
                $tmp['contract_txt'] = '--';
            }
            $data['datalist'][$key] = $tmp;
        }
        $data['status'] = [['id' => 0, 'name' => '待投递'], ['id' => 1, 'name' => '投递中'], ['id' => 2, 'name' => '投递完毕'], ['id' => 3, 'name' => '投递暂停']];
        $data['trade_list'] = (new \App\Models\Trade)->select(['id', 'name'])->get();
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    public function create()
    {
        $inputs = request()->all();
        $data = [];
        $business_order = new \App\Models\BusinessOrder;
        if (isset($inputs['request_type']) && $inputs['request_type'] == 'load') {
            //添加-加载
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['user_list'] = $user_list;
            $data['send_group'] = (new \App\Models\ProjectGroup)->select(['id','name'])->get()->toArray();
            $data['deliver_type'] = $business_order->deliver_types;
            $data['cooperation_cycle'] = $business_order->cooperation_cycles;
            $data['resource_type'] = $business_order->resource_types;
            $data['income_main_type'] = $business_order->income_main_types;
            $allowance_date = $tmp = [];
            for ($i = 0; $i <= 15; $i++) {
                $tmp['id'] = $i;
                $tmp['name'] = $i;
                $allowance_date[] = $tmp;
            }
            $data['allowance_date'] = $allowance_date;
            $data['status_list'] = $business_order->project_status;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if (isset($inputs['request_type']) && $inputs['request_type'] == 'order') {
            if (!isset($inputs['swd_id']) || empty($inputs['swd_id'])) {
                return response()->json(['code' => -1, 'message' => '无效商务单id']);
            }

            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $settlement_type = [];
            
            foreach ($business_order->settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name'];
            }
            $inputs['status_in'] = [1, 3, 4];//待上传合同、待创建项目
            $order_info = $business_order->getOrderInfo($inputs);
            if (empty($order_info)) {
                return response()->json(['code' => 0, 'message' => '当前商务单还不能创建项目']);
            }
            $order_info->customer_type = $order_info->hasCustomer->customer_type == 1 ? '直客' : '渠道';
            $order_info->customer_name = $order_info->hasCustomer->customer_name;
            $order_info->customer_tel = $order_info->hasCustomer->customer_tel;
            $order_info->customer_qq = $order_info->hasCustomer->customer_qq;
            $order_info->customer_email = $order_info->hasCustomer->customer_email;
            $order_info->customer_address = $order_info->hasCustomer->customer_address;
            $order_info->contacts = $order_info->hasCustomer->contacts;
            $order_info->bank_accounts = $order_info->hasCustomer->bank_accounts;
            $order_info->project_type = $order_info->project_type == 1 ? '平台' : '非平台';
            $order_info->project_sale = $user_data['id_realname'][$order_info->project_sale];
            $order_info->project_business = $user_data['id_realname'][$order_info->project_business];
            $order_info->trade_name = $order_info->trade->name;
            $order_info->test_cycle = $order_info->test_cycle;
            $order_settlement_type = array();
            foreach (explode(',', $order_info->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $order_info->settlement_type = implode(',', $order_settlement_type);

            $links = [];
            foreach ($order_info->hasLinks as $key => $value) {
                if($value->project_id == 0){
                    //可用链接
                    $links[$key]['id'] = $value->id;
                    $links[$key]['link_type'] = $value->link_type == 1 ? '分链接' : '自适应';
                    $links[$key]['link_name'] = $value->link_name;
                    $links[$key]['pc_link'] = $value->pc_link;
                    $links[$key]['wap_link'] = $value->wap_link;
                    $links[$key]['zi_link'] = $value->zi_link;
                    $links[$key]['remarks'] = $value->remarks;
                    $links[$key]['if_use'] = $value->if_use;
                    $links[$key]['pricing_manner'] = $value->pricing_manner;
                    $links[$key]['market_price'] = unserialize($value->market_price);
                    $links[$key]['created_at'] = $value->created_at->format('Y-m-d');
                    $links[$key]['updated_at'] = $value->updated_at->format('Y-m-d');
                }

            }
            $order_info->links = $links;
            unset($order_info->hasLinks);
            $data['order_info'] = $order_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        //保存数据
        $rules = [
            'id' => 'required|integer',
            'project_name' => 'required|max:100|unique:business_projects,project_name',
            'charge_id' => 'required|integer',
            'execute_id' => 'required|integer',
            'business_id' => 'required|integer',
            'assistant_id' => 'required|integer',
            'if_check' => 'required|integer',
            'if_xishu' => 'required|integer',
            'send_group' => 'required|integer',
            'deliver_type' => 'required|integer',
            'cooperation_cycle' => 'required|integer',
            'allowance_date' => 'required|integer',
            'has_v3' => 'required|integer',
            'username' => 'max:20',
            'password' => 'max:20',
            'resource_type' => 'required|integer',
            'income_main_type' => 'required|integer'

        ];
        $attributes = [
            'id' => '商务单id',
            'project_name' => '项目名称',
            'charge_id' => '负责人',
            'execute_id' => '执行人',
            'business_id' => '商务',
            'assistant_id' => '商务助理',
            'if_check' => '是否为考核项目',
            'if_xishu' => '是否为洗数项目',
            'send_group' => '发送组段',
            'deliver_type' => '投递类型',
            'cooperation_cycle' => '合作周期',
            'allowance_date' => '余量计算天数',
            'has_v3' => '是否有v3账号',
            'username' => '账号',
            'password' => '密码',
            'resource_type' => '资源',
            'income_main_type' => '收入主体'
        ];

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $inputs['status_in'] = [1, 3, 4];//待上传合同、待创建项目
        $order_info = $business_order->getOrderInfo($inputs);
        if (empty($order_info)) {
            return response()->json(['code' => 0, 'message' => '当前商务单无法创建项目']);
        }
        if(isset($inputs['link_ids']) && is_array($inputs['link_ids']) && !empty($inputs['link_ids']) && count($inputs['link_ids']) > 1){
            //分配多条链接的时候   CPA和CPS可以共存   CPC、CPD只能单独存在
            $res = (new \App\Models\BusinessOrderLink)->ifConflict($inputs);
            if($res['code'] != 1){
                return response()->json($res);
            }

        }
        $inputs['customer_id'] = $order_info->customer_id;//客户id
        $inputs['trade_id'] = $order_info->trade_id;//行业
        $inputs['project_sale'] = $order_info->project_sale;//销售
        $inputs['project_type'] = $order_info->project_type;//项目类型
        $project = new \App\Models\BusinessProject;
        $result = $project->storeData($inputs);
        if ($result) {
            //更改商务单状态
            $order_info->status = 4;//已创建项目
            $order_info->save();
            //推送到V3 测试环境屏蔽  正式环境开启
            $msg = $project->doRequestSyn();
            if($msg['code'] != 1) return response()->json(['code' => 0, 'message' => $msg['message']]);
            systemLog('项目汇总', '新增了项目['.$inputs['project_name'].']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     *  编辑项目
     * @author molin
     * @date 2019-03-22
     */
    public function update()
    {
        $inputs = request()->all();
        if (!isset($inputs['id']) || !is_numeric($inputs['id'])) {
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $project = new \App\Models\BusinessProject;
        if (isset($inputs['request_type']) && $inputs['request_type'] == 'save') {
            //编辑保存
            $rules = [
                'id' => 'required|integer',
                'project_name' => 'required|max:100|unique:business_projects,project_name,'.$inputs['id'],
                'charge_id' => 'required|integer',
                'execute_id' => 'required|integer',
                'business_id' => 'required|integer',
                'assistant_id' => 'required|integer',
                'if_check' => 'required|integer',
                'if_xishu' => 'required|integer',
                'send_group' => 'required|integer',
                'deliver_type' => 'required|integer',
                'cooperation_cycle' => 'required|integer',
                'allowance_date' => 'required|integer',
                'has_v3' => 'required|integer',
                'username' => 'max:20',
                'password' => 'max:20',
                'status' => 'required|integer',
                'resource_type' => 'required|integer',
                'income_main_type' => 'required|integer'

            ];
            $attributes = [
                'id' => '项目id',
                'project_name' => '项目名称',
                'charge_id' => '负责人',
                'execute_id' => '执行人',
                'business_id' => '商务',
                'assistant_id' => '商务助理',
                'if_check' => '是否为考核项目',
                'if_xishu' => '是否为洗数项目',
                'send_group' => '发送组段',
                'deliver_type' => '投递类型',
                'cooperation_cycle' => '合作周期',
                'allowance_date' => '余量计算天数',
                'has_v3' => '是否有v3账号',
                'username' => '账号',
                'password' => '密码',
                'status' => '投递状态',
                'resource_type' => '资源',
                'income_main_type' => '收入主体'
            ];

            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if(isset($inputs['link_ids']) && is_array($inputs['link_ids']) && !empty($inputs['link_ids']) && count($inputs['link_ids']) > 1){
                //分配多条链接的时候   CPA和CPS可以共存   CPC、CPD只能单独存在
                $inputs['pid'] = $inputs['id'];
                $res = (new \App\Models\BusinessOrderLink)->ifConflict($inputs);
                if($res['code'] != 1){
                    return response()->json($res);
                }

            }
            $project_info = $project->getProjectInfo($inputs);
            if(empty($project_info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $result = $project->storeData($inputs);
            if($result){
                //推送到V3 
                $msg = $project->doRequestSyn();
                if($msg['code'] != 1) return response()->json(['code' => 0, 'message' => $msg['message']]);
                systemLog('项目汇总', '编辑了项目['.$inputs['project_name'].']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }

        $business_order = new \App\Models\BusinessOrder;
        $settlement_type = $send_group = $deliver_type = $cooperation_cycle = [];
        
        foreach ($business_order->settlement_types as $key => $value) {
            $settlement_type[$value['id']] = $value['name'];
        }
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $project_info = $project->getProjectInfo($inputs);
        if(empty($project_info)){
            return response()->json(['code' => 0, 'message' => '数据不存在']);
        }
        $items = [];
        $items['id'] = $project_info->id;
        $items['swd_id'] = $project_info->hasOrder->swd_id;
        $items['customer_name'] = $project_info->hasCustomer->customer_name;
        $items['customer_type'] = $project_info->hasCustomer->customer_type == 1 ? '直客' : '渠道';
        $items['customer_tel'] = $project_info->hasCustomer->customer_tel;
        $items['customer_qq'] = $project_info->hasCustomer->customer_qq;
        $items['customer_email'] = $project_info->hasCustomer->customer_email;
        $items['customer_address'] = $project_info->hasCustomer->customer_address;
        $items['contacts'] = $project_info->hasCustomer->contacts;
        $items['bank_accounts'] = $project_info->hasCustomer->bank_accounts;
        $items['project_name'] = $project_info->project_name;
        $items['trade'] = $project_info->trade->name;
        $items['test_cycle'] = $project_info->hasOrder->test_cycle;
        $order_settlement_type = array();
        foreach (explode(',', $project_info->hasOrder->settlement_type) as $t) {
            $order_settlement_type[] = $settlement_type[$t];
        }
        $items['settlement_type'] = implode(',', $order_settlement_type);
        //当前商务单下面的未使用的链接
        $link = new \App\Models\BusinessOrderLink;
        $all_links = $link->where('order_id', $project_info->order_id)->whereIn('project_id', [0,$project_info->id])->get()->toArray();
        $links = [];
        $has_link_ids = [];//当前项目绑定的链接
        foreach ($all_links as $key => $val) {
            $links[$key]['id'] = $val['id'];
            $links[$key]['link_type'] = $val['link_type'] == 1 ? '分链接' : '自适应';
            $links[$key]['link_name'] = $val['link_name'];
            $links[$key]['pc_link'] = $val['pc_link'];
            $links[$key]['wap_link'] = $val['wap_link'];
            $links[$key]['zi_link'] = $val['zi_link'];
            $links[$key]['remarks'] = $val['remarks'];
            $links[$key]['if_use'] = $val['if_use'];
            $links[$key]['project_name'] = '--';
            $links[$key]['pricing_manner'] = $val['pricing_manner'];
            $links[$key]['market_price'] = unserialize($val['market_price']);
            $links[$key]['created_at'] = $val['created_at'];
            $links[$key]['updated_at'] = $val['updated_at'];
            if($val['project_id'] == $project_info->id){
                $has_link_ids[] = $val['id'];
            }
        }

        $items['links'] = $links;
        $items['has_link_ids'] = $has_link_ids;
        $items['sale_man'] = $user_data['id_realname'][$project_info->sale_man];
        $items['business_id'] = $project_info->business_id;
        $items['assistant_id'] = $project_info->assistant_id;
        $items['charge_id'] = $project_info->charge_id;
        $items['execute_id'] = $project_info->execute_id;
        $items['if_check'] = $project_info->if_check;
        $items['if_xishu'] = $project_info->if_xishu;
        $items['send_group'] = $project_info->group_id;
        $items['deliver_type'] = $project_info->deliver_type;
        $items['cooperation_cycle'] = $project_info->cooperation_cycle;
        $items['allowance_date'] = $project_info->allowance_date;
        $items['has_v3'] = $project_info->has_v3;
        $items['username'] = $project_info->username;
        $items['password'] = $project_info->password;
        $items['status'] = $project_info->status;
        $items['resource_type'] = $project_info->resource_type;
        $items['income_main_type'] = $project_info->income_main_type;

        $data['project_info'] = $items;
        $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
        $data['user_list'] = $user_list;
        $data['send_group'] = (new \App\Models\ProjectGroup)->select(['id','name'])->get()->toArray();
        $data['deliver_type'] = $business_order->deliver_types;
        $data['cooperation_cycle'] = $business_order->cooperation_cycles;
        $data['resource_type'] = $business_order->resource_types;
        $data['income_main_type'] = $business_order->income_main_types;
        $allowance_date = [];
        for ($i = 0; $i <= 15; $i++) {
            $tmp = [];
            $tmp['id'] = $i;
            $tmp['name'] = $i;
            $allowance_date[] = $tmp;
        }
        $data['allowance_date'] = $allowance_date;
        $data['status_list'] = $business_order->project_status;
        $suspend =  new \App\Models\BusinessProjectSuspend;
        $suspend_list = $suspend->where('project_id', $inputs['id'])->pluck('date')->toArray();
        sort($suspend_list);
        $data['suspend_list'] = $suspend_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    //EDM-项目管理

    /**
     *  执行详情
     * @author renxianyong
     * @date 2019-03-01
     */
    public function execute()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case 'tpl':
                if(!isset($inputs['tpl_id']) || empty($inputs['tpl_id'])){
                    return response()->json(['code' => -1, 'message' => '模板id不存在，请输入']);
                }
                $data = V3ProjectTaskTpl::where('id',$inputs['tpl_id'])->first(['id','tpl_name','subject','sender_nickname','sender_emails','content','last_modify_time']);
                if($data){
                    $data['receiver'] = '(实际收件人)';
                    $data['last_modify_time'] = date('Y-m-d H:i:s',$data['last_modify_time']);
                    return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
                }
                return response()->json(['code' => 0, 'message' => '获取失败，请重试']);
                break;
            default :
                return $this->executeDetail($inputs);
        }
    }

    /**
     * 运营情况
     * @author qinjintian
     * @date 2019-03-04
     */
    public function operation()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case 'click'://修改cpc点击量
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'cpc_amount' => 'required|integer',
                    'date' => 'required|date_format:Y-m-d'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'cpc_amount' => 'CPC点击量',
                    'date' => '日期'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                //当天不能修改反馈数
                if($inputs['date'] == date('Y-m-d',time())){
                    return ['code' => 0, 'message' => '不能修改今天的反馈数'];
                }
                //检查是否有反馈数
                $project_feedback_new = new ProjectFeedback;
                $result = $project_feedback_new->checkProjectLinkFeedback($inputs['project_id'],$inputs['date']);
                if($result['code'] != 1){
                    return $result;
                }
                $project_feedback = ProjectFeedback::where('project_id',$inputs['project_id'])->where('date',$inputs['date'])->first();
                if(empty($project_feedback)){
                    return ['code' => 0, 'message' => '当前项目在'.$inputs['date'].'无投放链接'];
                }
                try{
                    $original_cpc = $project_feedback->cpc_amount;//原cpc
                    $project_feedback->cpc_amount = $inputs['cpc_amount'];
                    $project_feedback->save();
                    $id = $project_feedback->id;
                    //添加反馈操作日志
                    $project_feedback_log = new ProjectFeedbackLog;
                    $project_feedback_log->fid = $id;
                    $project_feedback_log->project_id = $inputs['project_id'];
                    $project_feedback_log->user_id = auth()->id();
                    $project_feedback_log->pricing_manner = 'cpc';
                    $project_feedback_log->date = $inputs['date'];
                    $project_feedback_log->amount = $original_cpc;
                    $project_feedback_log->e_amount = $inputs['cpc_amount'];
                    $project_feedback_log->save();

                    //更新单价
                    $feedback = new ProjectFeedback;
                    $feedback->updateProjectIncome($inputs['project_id'],$inputs['date']);
                    return ['code' => 1, 'message' => '修改成功'];
                }catch (\Exception $e){
                    return ['code' => -1, 'message' => '修改失败'];
                }
                break;
            case 'show'://现在不同链接的反馈数
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'amount_type' => 'required|string',
                    'date' => 'required|date_format:Y-m-d'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'amount_type' => '反馈数类型',
                    'date' => '日期'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                $type = $inputs['amount_type'];
                $link_feedback = LinkFeedback::with(['hasLink'=>function($query){
                    $query->select('id','link_name');
                }])->where('project_id',$inputs['project_id'])
                    ->where('date',$inputs['date'])
                    ->select('id','link_id',"$type")
                    ->get();
                return ['code' => 1, 'message' => 'success', 'data' => $link_feedback];
                break;
            case 'intercept':
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'amount' => 'required|integer',
                    'date' => 'required|date_format:Y-m-d'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'amount' => '拦截量',
                    'date' => '日期'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                //当天不能修改反馈数
                if($inputs['date'] == date('Y-m-d',time())){
                    return ['code' => 0, 'message' => '不能修改今天的拦截数'];
                }
                //检查是否有反馈数
                $project_feedback_new = new ProjectFeedback;
                $result = $project_feedback_new->checkProjectLinkFeedback($inputs['project_id'],$inputs['date']);
                if($result['code'] != 1){
                    return $result;
                }
                $project_feedback = ProjectFeedback::where('project_id',$inputs['project_id'])->where('date',$inputs['date'])->first();
                if(empty($project_feedback)){
                    return ['code' => 0, 'message' => '当前项目在'.$inputs['date'].'无投放链接'];
                }
                try{
                    $project_feedback->intercept = $inputs['amount'];
                    $project_feedback->save();
                    $project_name = BusinessProject::where('id',$inputs['project_id'])->value('project_name');
                    //添加系统日志
                    systemLog('运营情况', '编辑了[' . $project_name . ']['.$inputs['date'].']的拦截量为['.$inputs['amount'].']');
                    return ['code' => 1, 'message' => '修改成功'];
                }catch (\Exception $e){
                    ddd($e);
                    return ['code' => -1, 'message' => '修改失败'];
                }
                break;
            case 'real_psend':
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'amount' => 'required|integer',
                    'date' => 'required|date_format:Y-m-d'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'amount' => '实际补量数',
                    'date' => '日期'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                //当天不能修改反馈数
                if($inputs['date'] == date('Y-m-d',time())){
                    return ['code' => 0, 'message' => '不能修改今天的实际补量数'];
                }
                //检查是否有反馈数
                $project_feedback_new = new ProjectFeedback;
                $result = $project_feedback_new->checkProjectLinkFeedback($inputs['project_id'],$inputs['date']);
                if($result['code'] != 1){
                    return $result;
                }
                $project_feedback = ProjectFeedback::where('project_id',$inputs['project_id'])->where('date',$inputs['date'])->first();
                if(empty($project_feedback)){
                    return ['code' => 0, 'message' => '当前项目在'.$inputs['date'].'无投放链接'];
                }
                try{
                    $project_feedback->real_psend = $inputs['amount'];
                    $project_feedback->save();
                    $project_name = BusinessProject::where('id',$inputs['project_id'])->value('project_name');
                    //添加系统日志
                    systemLog('运营情况', '编辑了[' . $project_name . ']['.$inputs['date'].']的实际补量数为['.$inputs['amount'].']');
                    return ['code' => 1, 'message' => '修改成功'];
                }catch (\Exception $e){
                    return ['code' => -1, 'message' => '修改失败'];
                }
                break;
            default :
                return $this->projectOperation($inputs);
        }
    }

    /**
     * 反馈列表
     * @author renxianyong
     * @date 2019-05-27
     */
    public function feedbackList()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';

        switch ($request_type){
            case 'link_feedback':
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'link_id' => 'required|integer',
                    'date' => 'required|date_format:Y-m-d',
                    'amount' => 'required',
                    'amount_type' => 'required|string|size:3'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'link_id' => '链接id',
                    'date' => '日期',
                    'amount' => '反馈数据',
                    'amount_type' => '反馈类型'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                //当天不能修改反馈数
                if($inputs['date'] == date('Y-m-d',time())){
                    return ['code' => 0, 'message' => '不能修改今天的反馈数'];
                }
                //检查是否有反馈
                $project_feedback_new = new ProjectFeedback;
                $result = $project_feedback_new->checkProjectLinkFeedback($inputs['project_id'],$inputs['date']);
                if($result['code'] != 1){
                    return $result;
                }
                $link_feedback = LinkFeedback::where('project_id',$inputs['project_id'])
                    ->where('link_id',$inputs['link_id'])
                    ->where('date',$inputs['date'])
                    ->first();
                if(empty($link_feedback)){
                    return ['code' => 0, 'message' => '当前项目在'.$inputs['date'].'无投放链接'];
                }
                try{
                    $type = $inputs['amount_type'].'_amount';
                    $original = $link_feedback[$type];//原来的值
                    if($inputs['amount_type'] == 'cpa'){
                        $link_feedback->cpa_amount = $inputs['amount'];
                    }elseif ($inputs['amount_type'] == 'cps'){
                        $link_feedback->cps_amount = $inputs['amount'];
                    }else{
                        return ['code' => 0, 'message' => '链接反馈表只能修改cpa和cps数据'];
                    }
                    $link_feedback->save();
                    $id = $link_feedback->id;
                    $name = BusinessOrderLink::where('id',$inputs['link_id'])->value('link_name');
                    //添加反馈操作日志
                    $project_feedback_log = new ProjectFeedbackLog;
                    $project_feedback_log->lid = $id;
                    $project_feedback_log->project_id = $inputs['project_id'];
                    $project_feedback_log->user_id = auth()->id();
                    $project_feedback_log->pricing_manner = $inputs['amount_type'];
                    $project_feedback_log->date = $inputs['date'];
                    $project_feedback_log->amount = $original;
                    $project_feedback_log->e_amount = $inputs['amount'];
                    $project_feedback_log->save();
                    //添加到系统日志
                    $project_name = BusinessProject::where('id',$inputs['project_id'])->value('project_name');
                    systemLog('运营情况', '编辑了['.$project_name.']的['.$name.'][' . $inputs["amount_type"] . ']反馈数');
                    $project_feedback = new ProjectFeedback;
                    $project_feedback->updateProjectIncome($inputs['project_id'],$inputs['date']);
                    return ['code' => 1, 'message' => '修改成功'];
                }catch (\Exception $e){
                    return ['code' => 0, 'message' => '修改失败'];
                }
                break;
            case 'project_feedback':
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'date' => 'required|date_format:Y-m-d',
                    'amount' => 'required',
                    'amount_type' => 'required|string|size:3'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'date' => '日期',
                    'amount' => '反馈数据',
                    'amount_type' => '反馈类型'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                //当天不能修改反馈数
                if($inputs['date'] == date('Y-m-d',time())){
                    return ['code' => 0, 'message' => '不能修改今天的反馈数'];
                }
                //检查是否有反馈数
                $project_feedback_new = new ProjectFeedback;
                $result = $project_feedback_new->checkProjectLinkFeedback($inputs['project_id'],$inputs['date']);
                if($result['code'] != 1){
                    return $result;
                }
                $project_feedback = ProjectFeedback::where('project_id',$inputs['project_id'])
                    ->where('date',$inputs['date'])->first();
                if(empty($project_feedback)){
                    return ['code' => 0, 'message' => '当前项目在'.$inputs['date'].'无投放链接'];
                }
                try{
                    $type = $inputs['amount_type'].'_amount';
                    $original = $project_feedback[$type];//原来的值
                    if($inputs['amount_type'] == 'cpd'){
                        $project_feedback->cpd_amount = $inputs['amount'];
                    }elseif ($inputs['amount_type'] == 'cpc'){
                        $project_feedback->cpc_amount = $inputs['amount'];
                    }else{
                        return ['code' => 0, 'message' => '统计表只能修改cpc和cpd数据'];
                    }
                    $project_feedback->save();
                    $id = $project_feedback->id;
                    //添加反馈操作日志
                    $project_feedback_log = new ProjectFeedbackLog;
                    $project_feedback_log->fid = $id;
                    $project_feedback_log->project_id = $inputs['project_id'];
                    $project_feedback_log->user_id = auth()->id();
                    $project_feedback_log->pricing_manner = $inputs['amount_type'];
                    $project_feedback_log->date = $inputs['date'];
                    $project_feedback_log->amount = $original;
                    $project_feedback_log->e_amount = $inputs['amount'];
                    $project_feedback_log->save();
                    //添加到系统日志
                    $project_name = BusinessProject::where('id',$inputs['project_id'])->value('project_name');
                    systemLog('运营情况', '编辑了[' . $project_name . ']的[' . $inputs["amount_type"] . ']反馈数');
                    return ['code' => 1, 'message' => '修改成功'];
                }catch (\Exception $e){
                    return ['code' => 0, 'message' => '修改失败'];
                }
                break;
            default :
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'start_date' => 'nullable|date_format:Y-m-d',
                    'end_date' => 'nullable|date_format:Y-m-d'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'start_date' => '开始日期',
                    'end_date' => '结束日期'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                $keyword_name = $inputs['keyword_name'] ?? '';
                $keyword_id = $inputs['keyword_id'] ?? '';
                $start_date = empty($inputs['start_date']) ? date('Y-m-d', strtotime('-14 day')) : $inputs['start_date'];
                $end_date = empty($inputs['end_date']) ? date('Y-m-d', time()) : $inputs['end_date'];
                $dates = array_reverse(prDates($start_date,$end_date));
                $links = BusinessOrderLink::where('project_id',$inputs['project_id'])->get()->toArray();
                if(empty($links)){
                    return ['code' => 0, 'message' => '项目没有投放连接，请检查'];
                }
                $feedback_list = LinkFeedback::with(['hasLink'=>function($query){
                    $query->select('id','link_name');
                }])->when($keyword_name,function($query) use($keyword_name){
                    $query->whereHas('hasLink',function($query) use($keyword_name){
                        $query->where('link_name','like','%'.$keyword_name.'%');
                    });
                })->when($keyword_id,function($query) use($keyword_id){
                        $query->where('link_id','like','%'.$keyword_id.'%');
                    })->where('project_id',$inputs['project_id'])
                    ->whereBetween('date',[$start_date,$end_date])
                    ->select('id','link_id','date','cpa_amount','cps_amount')
                    ->orderBy('date','DESC')
                    ->get()->toArray();
                $project_feedback = ProjectFeedback::where('project_id',$inputs['project_id'])
                    ->whereBetween('date',[$start_date,$end_date])
                    ->select('id','date','cpc_amount','cpd_amount')
                    ->orderBy('date','DESC')
                    ->get()->toArray();
                $datas = [];
                $datas['title'][0]['t1']['name'] = '汇总';
                $datas['title'][0]['t1']['id'] = '';
                $datas['title'][0]['t2'] = ['CPD','CPC','CPA','CPS'];
//                ddd($links);
                foreach($dates as $key => $date){
                    $datas['data'][$key][0]['date'] = $date;//日期
                    $datas['data'][$key][0]['cpd'] = '';
                    $datas['data'][$key][0]['cpc'] = '';
                    $datas['data'][$key][0]['cpa'] = '';
                    $datas['data'][$key][0]['cps'] = '0.00';
                    if(empty($feedback_list)){//没有反馈数据都为0
                        foreach ($links as $value){
                            $datas['title'][$value['id']]['t1']['name'] = $value['link_name'];//链接名称
                            $datas['title'][$value['id']]['t1']['id'] = 'ID:'.$value['id'];//链接id
                            $datas['title'][$value['id']]['t2'] = ['CPA','CPS'];
                            $datas['data'][$key][0]['cpd'] = 0; //汇总CPD
                            $datas['data'][$key][0]['cpc'] = 0; //汇总CPC
                            $datas['data'][$key][0]['cpa'] = 0; //汇总CPA
                            $datas['data'][$key][0]['cps'] = sprintf('%.2f',0); //汇总CPS
                            $datas['data'][$key][$value['id']]['link_cpa'] = 0;
                            $datas['data'][$key][$value['id']]['link_cps'] = sprintf('%.2f',0);
                            if(empty($datas['data'][$key][$value['id']])){
                                $datas['data'][$key][$value['id']]['link_cpa'] = '';
                                $datas['data'][$key][$value['id']]['link_cps'] = '0.00';
                            }
                        }
                    }else{
                        foreach($project_feedback as $total){
                            foreach ($feedback_list as $value){
                                if($value['date'] == $date && $total['date'] == $date){
                                    $datas['title'][$value['link_id']]['t1']['name'] = $value['has_link']['link_name'];//链接名称
                                    $datas['title'][$value['link_id']]['t1']['id'] = 'ID:'.$value['has_link']['id'];//链接id
                                    $datas['title'][$value['link_id']]['t2'] = ['CPA','CPS'];
                                    $datas['data'][$key][0]['cpd'] = $total['cpd_amount']; //汇总CPD
                                    $datas['data'][$key][0]['cpc'] = $total['cpc_amount']; //汇总CPC
                                    $datas['data'][$key][0]['cpa'] = empty($datas['data'][$key][0]['cpa']) ? $value['cpa_amount'] :$datas['data'][$key][0]['cpa'] + $value['cpa_amount']; //汇总CPA
                                    $datas['data'][$key][0]['cps'] = empty($datas['data'][$key][0]['cps']) ? sprintf('%.2f',$value['cps_amount']) :sprintf('%.2f',$datas['data'][$key][0]['cps'] + $value['cps_amount']); //汇总CPS
                                    $datas['data'][$key][$value['link_id']]['link_cpa'] = $value['cpa_amount'];
                                    $datas['data'][$key][$value['link_id']]['link_cps'] = sprintf('%.2f',$value['cps_amount']);
                                }
                                if(empty($datas['data'][$key][$value['link_id']])){
                                    $datas['data'][$key][$value['link_id']]['link_cpa'] = '';
                                    $datas['data'][$key][$value['link_id']]['link_cps'] = '0.00';
                                }
                            }
                        }
                    }
                }
                $title_key = 0;
                foreach($datas['title'] as $key => $val){
                    unset($datas['title'][$key]);
                    $datas['title'][$title_key] = $val;
                    $title_key++;
                }
                foreach($datas['data'] as $key1 => $val){
                    $data_key = 0;
                    foreach($val as $key2 => $data){
                        unset($datas['data'][$key1][$key2]);
                        $datas['data'][$key1][$data_key] = $data;
                        $data_key++;
                    }
                }
                if($datas){
                    return ['code' => 1, 'message' => '获取成功','data'=>$datas];
                }
                return ['code' => 0, 'message' => '获取失败'];
        }
    }


    /**
     *  操作日志列表
     * @author renxianyong
     * @date 2019-03-26
     */
    public function logIndex()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case 1://获取操作类型
                $datas = BusinessProjectLogType::pluck('type','id');
                if ($datas) {
                    return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $datas]);
                }
                return response()->json(['code' => 0, 'message' => '获取失败，请重试']);
                break;

            case 'data':
                return $this->projectOperation($inputs);
                break;

            default :
                if (!isset($inputs['business_project_id'])) {
                    return response()->json(['code' => -1, 'message' => '项目id不存在，请输入']);
                }
                $bussiness_project_log = new BusinessProjectLog;
                $datas = $bussiness_project_log->queryDatas($inputs);
                if ($datas) {
                    return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $datas]);
                }
                return response()->json(['code' => 0, 'message' => '获取失败，请重试']);
        }

    }

    /**
     *  新增记录
     * @author renxianyong
     * @date 2019-03-26
     */
    public function logStore()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                if(!isset($inputs['business_project_id'])){
                    return response()->json(['code'=>-1,'message'=>'项目id不存在']);
                }
                $datas['type'] = BusinessProjectLogType::get(['id', 'type']);
                $project_datas = BusinessProject::where('id', $inputs['business_project_id'])->first(['charge_id', 'execute_id']);
                $datas['query_charge'] = User::where('id',$project_datas['charge_id'])->first(['id','realname'])->toArray();
                $datas['query_execute'] = User::where('id',$project_datas['execute_id'])->first(['id','realname'])->toArray();
                if($datas['type'] && $datas['query_charge'] && $datas['query_execute']){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$datas]);
                }
                return response()->json(['code'=>0,'message'=>'获取失败，请重试']);
                break;
            default :
                return $this->storeDatas($inputs);
        }
    }

    /**
     *  新增记录
     * @author renxianyong
     * @date 2019-03-26
     */
    public function logEdit()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'操作记录id不存在，请输入']);
        }
        switch ($request_type) {
            case 'edit':
                $datas['type'] = BusinessProjectLogType::get(['id', 'type']);
                $datas['log_data'] = BusinessProjectLog::with(['queryExecute'=>function($query){
                    $query->select('id','realname');
                },'queryCharge'=>function($query){
                    $query->select('id','realname');
                }])->where('id',$inputs['id'])->first()->toArray();
                if($datas['type'] && $datas['log_data']){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$datas]);
                }
                return response()->json(['code'=>0,'message'=>'获取失败，请重试']);
                break;
            default :
                return $this->storeDatas($inputs);
        }
    }

    /**
     *  删除记录
     * @author renxianyong
     * @date 2019-03-26
     */
    public function logDelete()
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'操作记录id不存在，请输入']);
        }
        $result = BusinessProjectLog::destroy($inputs['id']);
        if ($result){
            systemLog('项目汇总', '删除了一个项目操作日志');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请重试']);

    }

    /**
     *  项目数据列表
     * @author renxianyong
     * @date 2019-06-03
     */
    public function projectList()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case 'export'://导出数据
                $data = $this->showProjectList($inputs);
                $keyword_period = $inputs['keyword_period'] ?? 1;//周期数值
                if ($keyword_period == 2) {
                    $start_date = empty($inputs['start_date']) ? date('Y-m-d', strtotime('-6 day')) : $inputs['start_date'];
                } else {
                    $start_date = empty($inputs['start_date']) ? date('Y-m-d', strtotime('-14 day')) : $inputs['start_date'];
                }
                $end_date = empty($inputs['end_date']) ? date('Y-m-d', time()) : $inputs['end_date'];
                if($keyword_period != 1){
                    $dates = prDates($start_date,$end_date);
                    $count = count($dates) - 1;
                }else{
                    $count = 0;
                }
                $theads = $inputs['head'];
                $k = 0;
                $tbodys = [];
                if($keyword_period == 1){
                    foreach($data['data']['data'] as $val){
                            $tbodys[$k][] = $val['company'];
                            $tbodys[$k][] = $val['trade'];
                            $tbodys[$k][] = $val['project_name'];
                            $tbodys[$k][] = $val['group'];
                            $tbodys[$k][] = $val['resource_type'];
                            $tbodys[$k][] = $val['date'];
                            foreach($inputs['head'] as $name => $head) {
                                $tbodys[$k][] = $val[$name];
                            }
                            $k++;
                    }
                }else{
                    foreach($data['data']['data'] as $project){
                        foreach($project as $val){
                            $tbodys[$k][] = $val['company'];
                            $tbodys[$k][] = $val['trade'];
                            $tbodys[$k][] = $val['project_name'];
                            $tbodys[$k][] = $val['group'];
                            $tbodys[$k][] = $val['resource_type'];
                            $tbodys[$k][] = $val['date'];
                            foreach($inputs['head'] as $name => $head) {
                                $tbodys[$k][] = $val[$name];
                            }
                            $k++;
                        }
                    }
                }
                array_unshift($theads,'公司','行业','项目','组段','项目类型','日期');
                $sheet_names = '项目数据列表';
                $file_name = '项目数据列表';
                $fileinfo = $this->exportSingleExcel($count,$theads,$tbodys,$sheet_names,$file_name);
                return ['code' => 1, 'message' => '导出成功', 'data' => ['url' => asset('storage/temps/' . $fileinfo['file']), 'filepath' => 'storage/temps/' . $fileinfo['file']]];
                break;
            case 'data'://获取列表所需数据
                $data['trades'] = Trade::where('if_use',1)->select('id','name')->get();//行业数据
                $data['groups'] = ProjectGroup::select('id','name')->get();//组段数据
                $data['types'] = [1=>'正常投递',2=>'触发',3=>'特殊组段'];//项目类型
                $data['periods'] = [1=>'日',2=>'周',3=>'自定义'];
                return ['code' => 1, 'message' => '获取成功','data' => $data];
                break;
            case 'intercept':
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'amount' => 'required|integer',
                    'date' => 'required|date_format:Y-m-d'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'amount' => '拦截量',
                    'date' => '日期'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                //当天不能修改反馈数
                if($inputs['date'] == date('Y-m-d',time())){
                    return ['code' => 0, 'message' => '不能修改今天的拦截数'];
                }
                //检查是否有反馈数
                $project_feedback_new = new ProjectFeedback;
                $result = $project_feedback_new->checkProjectLinkFeedback($inputs['project_id'],$inputs['date']);
                if($result['code'] != 1){
                    return $result;
                }
                $project_feedback = ProjectFeedback::where('project_id',$inputs['project_id'])->where('date',$inputs['date'])->first();
                if(empty($project_feedback)){
                    return ['code' => 0, 'message' => '当前项目在'.$inputs['date'].'无投放链接'];
                }
                try{
                    $project_feedback->intercept = $inputs['amount'];
                    $project_feedback->save();
                    $project_name = BusinessProject::where('id',$inputs['project_id'])->value('project_name');
                    //添加系统日志
                    systemLog('项目数据列表', '编辑了[' . $project_name . ']['.$inputs['date'].']的拦截量为['.$inputs['amount'].']');
                    return ['code' => 1, 'message' => '修改成功'];
                }catch (\Exception $e){
                    return ['code' => -1, 'message' => '修改失败'];
                }
                break;
            case 'real_psend':
                $rules = [
                    'project_id' => 'required|integer|min:1',
                    'amount' => 'required|integer',
                    'date' => 'required|date_format:Y-m-d'
                ];
                $attributes = [
                    'project_id' => '项目ID',
                    'amount' => '实际补量数',
                    'date' => '日期'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                //当天不能修改反馈数
                if($inputs['date'] == date('Y-m-d',time())){
                    return ['code' => 0, 'message' => '不能修改今天的实际补量数'];
                }
                //检查是否有反馈数
                $project_feedback_new = new ProjectFeedback;
                $result = $project_feedback_new->checkProjectLinkFeedback($inputs['project_id'],$inputs['date']);
                if($result['code'] != 1){
                    return $result;
                }
                $project_feedback = ProjectFeedback::where('project_id',$inputs['project_id'])->where('date',$inputs['date'])->first();
                if(empty($project_feedback)){
                    return ['code' => 0, 'message' => '当前项目在'.$inputs['date'].'无投放链接'];
                }
                try{
                    $project_feedback->real_psend = $inputs['amount'];
                    $project_feedback->save();
                    $project_name = BusinessProject::where('id',$inputs['project_id'])->value('project_name');
                    //添加系统日志
                    systemLog('项目数据列表', '编辑了[' . $project_name . ']['.$inputs['date'].']的实际补量数为['.$inputs['amount'].']');
                    return ['code' => 1, 'message' => '修改成功'];
                }catch (\Exception $e){
                    return ['code' => -1, 'message' => '修改失败'];
                }
                break;
            default :
                return $this->showProjectList($inputs);
        }
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function storeDatas(array $inputs)
    {
        $rules = [
            'business_project_id' => 'required|integer',
            'business_project_log_type_id' => 'required|integer',
            'content' => 'required|string',
            'date' => 'required|date'
        ];
        $attributes = [
            'business_project_id' => '项目id',
            'business_project_log_type_id' => '操作类型id',
            'content' => '修改内容',
            'date' => '操作日期'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $business_project_log = new BusinessProjectLog;
        $result = $business_project_log->storeDatas($inputs);
        $type = BusinessProjectLogType::where('id', $inputs['business_project_log_type_id'])->value('type');
        if ($result) {
            systemLog('项目汇总', '添加/修改了一个项目操作日志[' . $type . ']');
            return \response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return \response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }


    /**
     * 项目监控
     * @author molin
     * @cycle_id 1周 2月 3季度 4年度
     * @date 2019-03-25
     */
    public function monitor()
    {
        $inputs = request()->all();
        $data = array();
        $project = new \App\Models\BusinessProject;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            //加载数据
            $rules = [
                'start_time' => 'required|date_format:Y-m-d',
                'end_time' => 'required|date_format:Y-m-d',
                'cycle_id' => 'required|integer',
                'project_ids' => 'required|array'

            ];
            $attributes = [
                'start_time' => '开始计算时间',
                'end_time' => '结束结算时间',
                'cycle_id' => '周期选择',
                'project_ids' => '已项目'
            ];

            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $start = !isset($inputs['start']) ? 0 : $inputs['start'];
            $length = !isset($inputs['length']) ? 10 : $inputs['length'];
            $days = prDates($inputs['start_time'], $inputs['end_time']);
            switch ($inputs['cycle_id']) {
                case 1:
                    //按周计算
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        $week = date('w', strtotime($d));
                        if($week == 0){
                            $i++;
                        }
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;
                case 2:
                    //按月计算
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $day = date('d', strtotime($d));
                        if($day == '01'){
                            $i++;
                        }
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;
                case 3:
                    //按季度计算  --先按年区分 再统计季度
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $day = date('m-d', strtotime($d));
                        if($day == '01-01' || $day == '04-01' || $day == '07-01' || $day == '10-01'){
                            $i++;
                        }
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;
                case 4:
                    //按年度计算
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $day = date('m-d', strtotime($d));
                        if($day == '01-01'){
                            $i++;
                        }
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;

            }
            $start_date = $inputs['start_time'];
            $end_date = $inputs['end_time'];
            $inputs['start_time'] = date('Ymd', strtotime($inputs['start_time']));
            $inputs['end_time'] = date('Ymd', strtotime($inputs['end_time']));
            //$stat = new \App\Models\V3ProjectTaskStat;
            //$stat_list = $stat->getQueryList($inputs);
            $feedbacks = new \App\Models\ProjectFeedback;
            $feedbacks_list = $feedbacks->getQueryList($inputs);
            $send_count = $succ_count = $open_count_email_uv = $click_count_email_uv = $p_send_amount = $p_succ_count = $intercept_count = $real_psend_count = array();
            $feedbacks_data = array();
            $project_money = $project_all_money = array();
            foreach ($feedbacks_list as $key => $value) {
                $send_count[$value->project_id][$value->date] = $send_count[$value->project_id][$value->date] ?? 0;//发送数
                $send_count[$value->project_id][$value->date] += $value->send_amount;//发送数
                $p_send_count[$value->project_id][$value->date] = $p_send_count[$value->project_id][$value->date] ?? 0;//补量发送数
                $p_send_count[$value->project_id][$value->date] += $value->p_send_amount;//补量发送数

                $succ_count[$value->project_id][$value->date] = $succ_count[$value->project_id][$value->date] ?? 0;//成功发送数
                $succ_count[$value->project_id][$value->date] += $value->succ_amount;//成功发送数
                $p_succ_count[$value->project_id][$value->date] = $p_succ_count[$value->project_id][$value->date] ?? 0;//补量到达数
                $p_succ_count[$value->project_id][$value->date] += $value->p_succ_amount;//补量到达数

                //拦截量
                $intercept_count[$value->project_id][$value->date] = $intercept_count[$value->project_id][$value->date] ?? 0;
                $intercept_count[$value->project_id][$value->date] += $value->intercept;//拦截量
                //实际补量数
                $real_psend_count[$value->project_id][$value->date] = $real_psend_count[$value->project_id][$value->date] ?? 0;
                $real_psend_count[$value->project_id][$value->date] += $value->real_psend;//实际补量数

                $open_count_email_uv[$value->project_id][$value->date] = $open_count_email_uv[$value->project_id][$value->date] ?? 0;//打开数
                $open_count_email_uv[$value->project_id][$value->date] += $value->open_inde_amount;//打开数
                $click_count_email_uv[$value->project_id][$value->date] = $click_count_email_uv[$value->project_id][$value->date] ?? 0;//点击数
                $click_count_email_uv[$value->project_id][$value->date] += $value->click_inde_amount;//点击数

                $feedbacks_data[$value->project_id][$value->date]['CPA'] = $feedbacks_data[$value->project_id][$value->date]['CPA'] ?? 0;//数据反馈
                $feedbacks_data[$value->project_id][$value->date]['CPA'] += $value->cpa_amount;//cpa反馈数
                $feedbacks_data[$value->project_id][$value->date]['CPS'] = $feedbacks_data[$value->project_id][$value->date]['CPS'] ?? 0;
                $feedbacks_data[$value->project_id][$value->date]['CPS'] += $value->cps_amount;//cps数据反馈

                $feedbacks_data[$value->project_id][$value->date]['CPC'] = $feedbacks_data[$value->project_id][$value->date]['CPC'] ?? 0;
                $feedbacks_data[$value->project_id][$value->date]['CPC'] += $value->cpc_amount;//cpc数据反馈

                $feedbacks_data[$value->project_id][$value->date]['CPD'] = $feedbacks_data[$value->project_id][$value->date]['CPD'] ?? 0;
                $feedbacks_data[$value->project_id][$value->date]['CPD'] += $value->cpd_amount;//cpd数据反馈

                $project_money[$value->project_id][$value->date] = $value->money;
                $project_all_money[$value->project_id] = $project_all_money[$value->project_id] ?? 0;
                $project_all_money[$value->project_id] += $value->money;//当前项目在搜索时间段内的总收入
            }
            $project_list = $project->whereIn('id', $inputs['project_ids'])->select(['id','project_name','order_id'])->get();
            $order_ids = $title_data = array();
            foreach ($project_list as $key => $value) {
                $order_ids[] = $value->order_id;
                $title_data[$value->id] = $value->project_name;
            }
            
            $datalist = array();
            $chart_data = $x_data = $chart_send_count = $chart_open_rate = $chart_click_rate = $chart_open_click_rate = $chart_cpa_reg_rate = $chart_danfeng = $chart_click_price = array();
            foreach ($list_data as $key => $value) {
                $items = array();
                foreach ($project_list as $val) {
                    $sdate = reset($value);
                    $sdate = date('Y.m.d', strtotime($sdate));
                    $edate = end($value);
                    $edate = date('Y.m.d', strtotime($edate));
                    $tmp = array();
                    $tmp['date'] = $x_date = $sdate.'~'.$edate;
                    $x_data[$sdate] = $x_date;
                    $tmp['project_name'] = $val->project_name;
                    $tmp_send_count = $tmp_succ_count = $tmp_open_count = $tmp_click_count = $tmp_intercept_count = $tmp_real_psend_count = 0;
                    $tmp_feedback['CPA'] = 0;
                    $tmp_feedback['CPS'] = 0;
                    $tmp_feedback['CPC'] = 0;
                    $tmp_feedback['CPD'] = 0;
                    $cpa_cps_cpc_cpd_sum = 0;
                    foreach ($value as $d) {
                        $tmp_send_count += $send_count[$val->id][$d] ?? 0;
                        $tmp_succ_count += $succ_count[$val->id][$d] ?? 0;
                        $tmp_intercept_count += $intercept_count[$val->id][$d] ?? 0;//拦截量
                        $tmp_real_psend_count += $real_psend_count[$val->id][$d] ?? 0;//实际补量
                        $tmp_open_count += $open_count_email_uv[$val->id][$d] ?? 0;
                        $tmp_click_count += $click_count_email_uv[$val->id][$d] ?? 0;
                        $price_type = array();
                        $tmp_feedback['CPA'] += $price_type['CPA'] = $feedbacks_data[$val->id][$d]['CPA'] ?? 0;
                        $tmp_feedback['CPS'] += $price_type['CPS'] = $feedbacks_data[$val->id][$d]['CPS'] ?? 0;
                        $tmp_feedback['CPC'] += $price_type['CPC'] = $feedbacks_data[$val->id][$d]['CPC'] ?? 0;
                        $tmp_feedback['CPD'] += $price_type['CPD'] = $tmp_succ_count;
                        $cpa_cps_cpc_cpd_sum += $project_money[$val->id][$d] ?? 0;//该项目在这段时间内的总收入
                    }
                    $tmp['send_count'] = $tmp_send_count;//发送数
                    $tmp['succ_count'] = $tmp_succ_count;//到达数
                    $tmp['real_succ_count'] = $real_succ_count = $tmp_succ_count - $tmp_intercept_count + $tmp_real_psend_count;//实际到达数 = 到达数 - 拦截量 + 实际补量数
                    $tmp['open_count'] = $tmp_open_count;//打开数
                    $tmp['open_rate'] = $real_succ_count > 0 ? sprintf("%.2f", ($tmp_open_count /$real_succ_count) * 100) : 0;//打开率 = 独立打开数/实际到达数
                    $tmp['click_count'] = $tmp_click_count;//点击数-v3接口的
                    $tmp['click_rate'] = $real_succ_count > 0 ? sprintf("%.2f", ($tmp_click_count /$real_succ_count) * 100) : 0;//点击率 = 点击人数/实际到达数
                    $tmp['open_click_rate'] = $tmp_open_count > 0 ? sprintf("%.2f", ($tmp_click_count /$tmp_open_count) * 100) : 0;//点击打开数
                    $tmp['reg_count'] = $tmp_feedback['CPA'];
                    $tmp['cpa_reg_rate'] = $tmp_open_count > 0 ? sprintf("%.2f", ($tmp_feedback['CPA'] /$tmp_open_count) * 100) : 0;//注册率
                    $tmp['income'] = $cpa_cps_cpc_cpd_sum;//收入
                    $tmp['danfeng'] = $real_succ_count > 0 ? sprintf("%.4f", $cpa_cps_cpc_cpd_sum /$real_succ_count) : 0;//总收入/实际到达数 = 单封收益
                    $tmp['click_price'] = $tmp_click_count > 0 ? sprintf("%.2f", $cpa_cps_cpc_cpd_sum /$tmp_click_count) : 0;//总收入/点击数 =点击单价
                    $items[] = $tmp;

                    //图表数据
                    $chart_send_count[$val->id][$sdate] = $tmp['send_count'];
                    $chart_open_rate[$val->id][$sdate] = $tmp['open_rate'];
                    $chart_click_rate[$val->id][$sdate] = $tmp['click_rate'];
                    $chart_open_click_rate[$val->id][$sdate] = $tmp['open_click_rate'];
                    $chart_cpa_reg_rate[$val->id][$sdate] = $tmp['cpa_reg_rate'];
                    $chart_danfeng[$val->id][$sdate] = $tmp['danfeng'];
                    $chart_click_price[$val->id][$sdate] = $tmp['click_price'];
                }
                $datalist[] = $items;
                
            }
            $chart_data['send_count'] = $chart_send_count;
            $chart_data['open_rate'] = $chart_open_rate;
            $chart_data['click_rate'] = $chart_click_rate;
            $chart_data['open_click_rate'] = $chart_open_click_rate;
            $chart_data['cpa_reg_rate'] = $chart_cpa_reg_rate;
            $chart_data['danfeng'] = $chart_danfeng;
            $chart_data['click_price'] = $chart_click_price;
            $data['datalist'] = $datalist;
            $data['title_data'] = $title_data;
            $data['x_data'] = $x_data;
            $data['chart_data'] = $chart_data;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'export'){
            //加载数据
            $rules = [
                'start_time' => 'required|date_format:Y-m-d',
                'end_time' => 'required|date_format:Y-m-d',
                'cycle_id' => 'required|integer',
                'project_ids' => 'required|array'

            ];
            $attributes = [
                'start_time' => '开始计算时间',
                'end_time' => '结束结算时间',
                'cycle_id' => '周期选择',
                'project_ids' => '已项目'
            ];

            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $start = !isset($inputs['start']) ? 0 : $inputs['start'];
            $length = !isset($inputs['length']) ? 10 : $inputs['length'];
            $days = prDates($inputs['start_time'], $inputs['end_time']);
            switch ($inputs['cycle_id']) {
                case 1:
                    //按周计算
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        $week = date('w', strtotime($d));
                        if($week == 0){
                            $i++;
                        }
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;
                case 2:
                    //按月计算
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $day = date('d', strtotime($d));
                        if($day == '01'){
                            $i++;
                        }
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;
                case 3:
                    //按季度计算  --先按年区分 再统计季度
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $day = date('m-d', strtotime($d));
                        if($day == '01-01' || $day == '04-01' || $day == '07-01' || $day == '10-01'){
                            $i++;
                        }
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;
                case 4:
                    //按年度计算
                    $i = 0;
                    $list_data = array();
                    foreach ($days as $d) {
                        $day = date('m-d', strtotime($d));
                        if($day == '01-01'){
                            $i++;
                        }
                        $list_data[$i] = $list_data[$i] ?? [];
                        $list_data[$i][] = $d;
                        
                    }
                    $records_filtered = count($list_data);//列表总数
                    break;

            }
            $start_date = $inputs['start_time'];
            $end_date = $inputs['end_time'];
            $inputs['start_time'] = date('Ymd', strtotime($inputs['start_time']));
            $inputs['end_time'] = date('Ymd', strtotime($inputs['end_time']));
            $feedbacks = new \App\Models\ProjectFeedback;
            $feedbacks_list = $feedbacks->getQueryList($inputs);
            $send_count = $succ_count = $open_count_email_uv = $click_count_email_uv = $p_send_count = $p_succ_count = $intercept_count = $real_psend_count = array();
            $feedbacks_data = array();
            $project_money = $project_all_money = array();
            foreach ($feedbacks_list as $key => $value) {
                $send_count[$value->project_id][$value->date] = $send_count[$value->project_id][$value->date] ?? 0;//发送数
                $send_count[$value->project_id][$value->date] += $value->send_amount;//发送数
                $p_send_count[$value->project_id][$value->date] = $p_send_count[$value->project_id][$value->date] ?? 0;//补量发送数
                $p_send_count[$value->project_id][$value->date] += $value->p_send_amount;//补量发送数
                $succ_count[$value->project_id][$value->date] = $succ_count[$value->project_id][$value->date] ?? 0;//成功发送数
                $succ_count[$value->project_id][$value->date] += $value->succ_amount;//成功发送数
                $p_succ_count[$value->project_id][$value->date] = $p_succ_count[$value->project_id][$value->date] ?? 0;//补量到达数
                $p_succ_count[$value->project_id][$value->date] += $value->p_succ_amount;//补量到达数

                //拦截量
                $intercept_count[$value->project_id][$value->date] = $intercept_count[$value->project_id][$value->date] ?? 0;
                $intercept_count[$value->project_id][$value->date] += $value->intercept;//拦截量
                //实际补量数
                $real_psend_count[$value->project_id][$value->date] = $real_psend_count[$value->project_id][$value->date] ?? 0;
                $real_psend_count[$value->project_id][$value->date] += $value->real_psend;//实际补量数

                $open_count_email_uv[$value->project_id][$value->date] = $open_count_email_uv[$value->project_id][$value->date] ?? 0;//打开数
                $open_count_email_uv[$value->project_id][$value->date] += $value->open_inde_amount;//打开数
                $click_count_email_uv[$value->project_id][$value->date] = $click_count_email_uv[$value->project_id][$value->date] ?? 0;//点击数
                $click_count_email_uv[$value->project_id][$value->date] += $value->click_inde_amount;//点击数

                $feedbacks_data[$value->project_id][$value->date]['CPA'] = $feedbacks_data[$value->project_id][$value->date]['CPA'] ?? 0;//数据反馈
                $feedbacks_data[$value->project_id][$value->date]['CPA'] += $value->cpa_amount;//cpa反馈数
                $feedbacks_data[$value->project_id][$value->date]['CPS'] = $feedbacks_data[$value->project_id][$value->date]['CPS'] ?? 0;
                $feedbacks_data[$value->project_id][$value->date]['CPS'] += $value->cps_amount;//cps数据反馈

                $feedbacks_data[$value->project_id][$value->date]['CPC'] = $feedbacks_data[$value->project_id][$value->date]['CPC'] ?? 0;
                $feedbacks_data[$value->project_id][$value->date]['CPC'] += $value->cpc_amount;//cpc数据反馈

                $feedbacks_data[$value->project_id][$value->date]['CPD'] = $feedbacks_data[$value->project_id][$value->date]['CPD'] ?? 0;
                $feedbacks_data[$value->project_id][$value->date]['CPD'] += $value->cpd_amount;//cpd数据反馈

                $project_money[$value->project_id][$value->date] = $value->money;
                $project_all_money[$value->project_id] = $project_all_money[$value->project_id] ?? 0;
                $project_all_money[$value->project_id] += $value->money;//当前项目在搜索时间段内的总收入
            }
            $project_list = $project->whereIn('id', $inputs['project_ids'])->select(['id','project_name','order_id'])->get();
            $order_ids = array();
            foreach ($project_list as $key => $value) {
                $order_ids[] = $value->order_id;
            }
            
            $export_data = array();
            foreach ($list_data as $key => $value) {
                $summary_send_count = $summary_succ_count = $summary_open_count = $summary_click_count = $summary_cpa = $summary_cps = $summary_cpc = $summary_cpd = $summary_cpa_cps_cpc_cpd_sum = 0;
                foreach ($project_list as $val) {
                    $sdate = reset($value);
                    $sdate = date('Y.m.d', strtotime($sdate));
                    $edate = end($value);
                    $edate = date('Y.m.d', strtotime($edate));
                    $tmp = array();
                    $tmp['date'] = $x_date = $sdate.'~'.$edate;
                    $tmp['project_name'] = $val->project_name;
                    $tmp_send_count = $tmp_succ_count = $tmp_open_count = $tmp_click_count = $tmp_intercept_count = $tmp_real_psend_count = 0;
                    $tmp_feedback['CPA'] = 0;
                    $tmp_feedback['CPS'] = 0;
                    $tmp_feedback['CPC'] = 0;
                    $tmp_feedback['CPD'] = 0;
                    $cpa_cps_cpc_cpd_sum = 0;
                    
                    foreach ($value as $d) {
                        $tmp_send_count += $send_count[$val->id][$d] ?? 0;
                        $tmp_succ_count += $succ_count[$val->id][$d] ?? 0;
                        $tmp_intercept_count += $intercept_count[$val->id][$d] ?? 0;
                        $tmp_real_psend_count += $real_psend_count[$val->id][$d] ?? 0;
                        $tmp_open_count += $open_count_email_uv[$val->id][$d] ?? 0;
                        $tmp_click_count += $click_count_email_uv[$val->id][$d] ?? 0;
                        $price_type = array();
                        $tmp_feedback['CPA'] += $price_type['CPA'] = $feedbacks_data[$val->id][$d]['CPA'] ?? 0;
                        $tmp_feedback['CPS'] += $price_type['CPS'] = $feedbacks_data[$val->id][$d]['CPS'] ?? 0;
                        $tmp_feedback['CPC'] += $price_type['CPC'] = $feedbacks_data[$val->id][$d]['CPC'] ?? 0;
                        $tmp_feedback['CPD'] += $price_type['CPD'] = $tmp_succ_count;
                        $cpa_cps_cpc_cpd_sum += $project_money[$val->id][$d];//该项目在这段时间内的总收入
                    }
                    $tmp['send_count'] = $tmp_send_count;//发送数
                    $real_succ_count = $tmp_succ_count - $tmp_intercept_count + $tmp_real_psend_count;//实际到达数 = 到达数 - 拦截量 + 实际补量
                    $tmp['open_rate'] = ($real_succ_count > 0 ? sprintf("%.2f", ($tmp_open_count /$real_succ_count) * 100) : 0).'%';//打开率
                    $tmp['click_rate'] = ($real_succ_count > 0 ? sprintf("%.2f", ($tmp_click_count /$real_succ_count) * 100) : 0).'%';//点击率
                    $tmp['open_click_rate'] = ($tmp_open_count > 0 ? sprintf("%.2f", ($tmp_click_count /$tmp_open_count) * 100) : 0).'%';//点击打开数
                    $tmp['cpa_reg_rate'] = ($tmp_open_count > 0 ? sprintf("%.2f", ($tmp_feedback['CPA'] /$tmp_open_count) * 100) : 0).'%';//注册率
                    $tmp['danfeng'] = $real_succ_count > 0 ? sprintf("%.4f", $cpa_cps_cpc_cpd_sum /$real_succ_count) : 0;//总收入/实际到达数 = 单封
                    $tmp['click_price'] = $tmp_click_count > 0 ? sprintf("%.2f", $cpa_cps_cpc_cpd_sum /$tmp_click_count) : 0;//总收入/点击数 =点击单价
                    $export_data[] = $tmp;
                    //小结计算
                    $summary_send_count += $tmp_send_count; 
                    $summary_succ_count += $real_succ_count; 
                    $summary_open_count += $tmp_open_count;
                    $summary_click_count += $tmp_click_count;
                    $summary_cpa += $tmp_feedback['CPA'];
                    $summary_cps += $tmp_feedback['CPS'];
                    $summary_cpc += $tmp_feedback['CPC'];
                    $summary_cpa_cps_cpc_cpd_sum += $cpa_cps_cpc_cpd_sum;
                }
                $summary_tmp = array();
                $summary_tmp['date'] = '小结';
                $summary_tmp['project_name'] = '';
                $summary_tmp['send_count'] = $summary_send_count;//小结-发送数
                $summary_tmp['open_rate'] = ($summary_succ_count > 0 ? sprintf("%.2f", ($summary_open_count /$summary_succ_count) * 100) : 0).'%';//小结-打开率
                $summary_tmp['click_rate'] = ($summary_succ_count > 0 ? sprintf("%.2f", ($summary_click_count /$summary_succ_count) * 100) : 0).'%';//小结-点击率
                $summary_tmp['open_click_rate'] = ($summary_open_count > 0 ? sprintf("%.2f", ($summary_click_count /$summary_open_count) * 100) : 0).'%';//小结-点击打开率
                $summary_tmp['cpa_reg_rate'] = ($summary_open_count > 0 ? sprintf("%.2f", ($summary_cpa /$summary_open_count) * 100) : 0).'%';//小结-cpa注册率
                $summary_tmp['danfeng'] = $summary_succ_count > 0 ? sprintf("%.4f", $summary_cpa_cps_cpc_cpd_sum /$summary_succ_count) : 0;;//小结-单封
                $summary_tmp['click_price'] = $summary_click_count > 0 ? sprintf("%.2f", $summary_cpa_cps_cpc_cpd_sum /$summary_click_count) : 0;//小结-点击单价
                $export_data[] = $summary_tmp;
            }
            $th = ['date'=>'日期','project_name'=>'项目名称','send_count'=>'发送数','open_rate'=>'打开率','click_rate'=>'点击率','open_click_rate'=>'点击打开率','cpa_reg_rate'=>'CPA注册率','danfeng'=>'单封收益','click_price'=>'点击单价'];
            // dd($export_data);
            $filedata = pExprot($th, $export_data, 'project_monitor');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'data' => ['filepath'=>$filepath, 'fileurl' => $fileurl]]);

        }
        $data['datalist'] = [];
        $data['cycle_list'] = [['id'=>1, 'name'=>'周'],['id'=>2, 'name'=>'月'],['id'=>3, 'name'=>'季度'],['id'=>4, 'name'=>'年度']];
        $data['data_type'] = [['id'=>'send_count', 'name'=>'发送数'],['id'=>'click_rate', 'name'=>'点击率'],['id'=>'open_click_rate', 'name'=>'点击打开率'],['id'=>'open_rate', 'name'=>'打开率'],['id'=>'cpa_reg_rate', 'name'=>'CPA注册率'],['id'=>'danfeng','name'=>'单封收益'],['id'=>'click_price','name'=>'点击单价']];
        $data['project_list'] = $project->select(['id', 'project_name'])->orderBy('id', 'desc')->get();
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * @param array $inputs
     * @return \Illuminate\Http\JsonResponse
     */
    public function executeDetail(array $inputs): \Illuminate\Http\JsonResponse
    {
        $rules = [
            'project_id' => 'required|integer|min:1',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ];

        $attributes = [
            'project_id' => '项目id',
            'start_date' => '开始日期',
            'end_date' => '结束日期',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if (isset($inputs['start_date']) && isset($inputs['end_date']) && (empty($inputs['start_date']) && !empty($inputs['end_date'])) || (!empty($inputs['start_date']) && empty($inputs['end_date']))) {
            return response()->json(['code' => 0, 'message' => '不能只填开始日期或者结束日期']);
        }
        $start_date = isset($inputs['start_date']) ? date('Ymd',strtotime($inputs['start_date'])) : date('Ymd',strtotime('-14 day'));
        $end_date = isset($inputs['end_date']) ? date('Ymd',strtotime($inputs['end_date'])) : date('Ymd',time());
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $task_name = $inputs['task_name'] ?? '';
        $tpl_name = $inputs['tpl_name'] ?? '';
        $is_margin = $inputs['is_margin'] ?? 1;
        if($is_margin == 1){
            $query = V3ProjectTaskStat::with(['V3ProjectTaskTpl' => function ($query) {
                $query->select('id', 'tpl_id', 'tpl_name', 'subject');
            }])->whereHas('V3ProjectTaskTpl', function ($query) use ($tpl_name) {
                $query->when($tpl_name, function ($query) use ($tpl_name) {
                    $query->where('tpl_name', 'like', '%' . $tpl_name . '%');
                });
            })->when($task_name, function ($query) use ($task_name) {
                $query->where('name', 'like', '%' . $task_name . '%');
            })->whereBetween('date', [$start_date, $end_date])
                ->where(function ($query) use ($inputs) {
                    $query->where('project_id', $inputs['project_id']);
                });
        }else{
            $query = V3ProjectTaskStat::with(['V3ProjectTaskTpl' => function ($query) {
                $query->select('id', 'tpl_id', 'tpl_name', 'subject');
            }])->whereHas('V3ProjectTaskTpl', function ($query) use ($tpl_name) {
                $query->when($tpl_name, function ($query) use ($tpl_name) {
                    $query->where('tpl_name', 'like', '%' . $tpl_name . '%');
                });
            })->when($task_name, function ($query) use ($task_name) {
                $query->where('name', 'like', '%' . $task_name . '%');
            })->whereBetween('date', [$start_date, $end_date])
                ->where(function ($query) use ($inputs) {
                    $query->where('project_id', $inputs['project_id']);
                })->where('send_count','!=',0);
        }
        $count = $query->count();//获取数据总条数
        $v3_datas = $query->orderBy('date', 'DESC')->skip($start)->take($length)->get()->toArray();  //获取v3任务数据
        $project = [];
        $project['project_data'] = [];
        $tags = include_once(storage_path('app/private/v3OpenLibs/tags.inc.php'));
        foreach ($v3_datas as $key => $data) {
            $start_time = date('Y-m-d', strtotime($data['date']));
            $project['project_data'][$key]['start_date'] = $start_time;//日期
            $project['project_data'][$key]['task_name'] = $data['name'];//任务名
            $project['project_data'][$key]['tpl_name'] = $data['v3_project_task_tpl']['tpl_name'];//模板名
            $project['project_data'][$key]['tpl_title'] = $data['v3_project_task_tpl']['subject'];//模板标题
            $project['project_data'][$key]['tpl_id'] = $data['v3_project_task_tpl']['id'];//模板id
            $project['project_data'][$key]['task_id'] = $data['task_id'];//V3任务id
            $project['project_data'][$key]['start_time'] = date('H:i', $data['start_time']);//发送时间
            $project['project_data'][$key]['server_group'] = $data['server_group'];//发送组段
            if($data['task_id'] == 0){
                $project['project_data'][$key]['send_count'] = $data['p_send_count'];//发送数
                $project['project_data'][$key]['fix_send_count'] = 0;//补量发送数
                $project['project_data'][$key]['succ_count'] = $data['p_succ_count'];//到达数
                $project['project_data'][$key]['fix_succ_count'] = 0;//补量到达数
            }else{
                $project['project_data'][$key]['send_count'] = $data['send_count'];//发送数
                $project['project_data'][$key]['fix_send_count'] = 0;//补量发送数
                $project['project_data'][$key]['succ_count'] = $data['succ_count'];//到达数
                $project['project_data'][$key]['fix_succ_count'] = 0;//补量到达数
            }
            $project['project_data'][$key]['open_count_email_uv'] = $data['open_count_email_uv'];//打开人数
            $project['project_data'][$key]['open_rate'] = $project['project_data'][$key]['succ_count'] > 0 ? sprintf('%.2f%%', $data['open_count_email_uv'] / $project['project_data'][$key]['succ_count'] * 100) : 0;//打开率
            $project['project_data'][$key]['open_count_pv'] = $data['open_count_pv'];//打开pv
            $project['project_data'][$key]['open_count_ip_uv'] = $data['open_count_ip_uv'];//IP独立打开
            $project['project_data'][$key]['click_count_email_uv'] = $data['click_count_email_uv'];//点击人数
            $project['project_data'][$key]['click_rate'] = $project['project_data'][$key]['succ_count'] > 0 ? sprintf('%.2f%%', $data['click_count_email_uv'] / $project['project_data'][$key]['succ_count'] * 100) : 0;//点击率
            $project['project_data'][$key]['click_count_pv'] = $data['click_count_pv'];//点击pv
            $project['project_data'][$key]['click_count_ip_uv'] = $data['click_count_ip_uv'];//ip独立点击
            $project['project_data'][$key]['open_click'] = $project['project_data'][$key]['open_count_email_uv'] > 0 ? sprintf('%.2f%%', $data['click_count_email_uv'] / $project['project_data'][$key]['open_count_email_uv'] * 100) : 0;//点击打开比
            $project['project_data'][$key]['complaint_count_email_uv'] = $data['complaint_count_email_uv'];//投诉数
            $project['project_data'][$key]['complaint_rate'] = $project['project_data'][$key]['succ_count'] > 0 ? sprintf('%.2f%%', $data['complaint_count_email_uv'] / $project['project_data'][$key]['succ_count'] * 100) : 0;//投诉率
            $project['project_data'][$key]['complaint_open'] = $project['project_data'][$key]['open_count_email_uv'] > 0 ? sprintf('%.2f%%', $data['complaint_count_email_uv'] / $project['project_data'][$key]['open_count_email_uv'] * 100) : 0;//投诉打开比
            $project['project_data'][$key]['unsubscribe_count_email_uv'] = $data['unsubscribe_count_email_uv'];//退订数
            $project['project_data'][$key]['unsubscribe_rate'] = $project['project_data'][$key]['succ_count'] > 0 ? sprintf('%.2f%%', $data['unsubscribe_count_email_uv'] / $project['project_data'][$key]['succ_count'] * 100) : 0;//退订率
            $project['project_data'][$key]['unsubscribe_open'] = $project['project_data'][$key]['open_count_email_uv'] > 0 ? sprintf('%.2f%%', $data['unsubscribe_count_email_uv'] / $project['project_data'][$key]['open_count_email_uv'] * 100) : 0;//退订打开比
            $project['project_data'][$key]['soft_fail_count'] = $data['soft_fail_count'];//软弹数
            $project['project_data'][$key]['soft_rate'] = $project['project_data'][$key]['send_count'] > 0 ? sprintf('%.2f%%', $data['soft_fail_count'] / $project['project_data'][$key]['send_count'] * 100) : 0;//软弹率
            $project['project_data'][$key]['hard_fail_count'] = $data['hard_fail_count'];//硬弹数
            $project['project_data'][$key]['hard_rate'] = $project['project_data'][$key]['send_count'] > 0 ? sprintf('%.2f%%', $data['hard_fail_count'] / $project['project_data'][$key]['send_count'] * 100) : 0;//硬弹率
            $project['project_data'][$key]['timeout_count'] = $data['timeout_count'];//超时数
            $project['project_data'][$key]['succ_rate'] = $project['project_data'][$key]['send_count'] > 0 ? sprintf('%.2f%%', $data['succ_count'] / $project['project_data'][$key]['send_count'] * 100) : 0;//到达率

            $include_tags = [];
            $address_property = json_decode($data['address_property'], true);
            $task_use_data = []; // 任务使用数据
            $task_use_data['max_count'] = '最多提取' . $address_property['max_count'];
            $task_use_data['domains'] = isset($address_property['domains']) ? implode(',', $address_property['domains']) : []; // 域名
            $include_tag_ids = isset($address_property['include_tags'][0]) ? explode(',', $address_property['include_tags'][0]) : [];
            foreach ($include_tag_ids as $tag_id) {
                if (isset($tags[$tag_id])) {
                    $include_tags[] = $tags[$tag_id];
                }
            }
            $task_use_data['include_tags'] = implode(',', $include_tags); // 包含标签

            $exclude_tag_ids = isset($address_property['exclude_tags']) ? explode(',', $address_property['exclude_tags']) : [];
            $exclude_tags = [];
            foreach ($exclude_tag_ids as $tag_id) {
                if (isset($tags[$tag_id])) {
                    $exclude_tags[] = $tags[$tag_id];
                }
            }
            $task_use_data['exclude_tags'] = implode(',', $exclude_tags); // 排除标签

            $exclude_projects_click_ids = $address_property['exclude_projects_click'] ?? [];
            $exclude_projects_clicks = [];
            foreach ($exclude_projects_click_ids as $click_id) {
                if (isset($tags[$click_id])) {
                    $exclude_projects_clicks[] = $tags[$click_id];
                }
            }
            $task_use_data['exclude_projects_clicks'] = implode(',', $exclude_projects_clicks); // 排除项目点击数据
            $task_use_data['before_days'] = '取 ' . $address_property['before_days'] . ' 天前的数据,-1时取所有';
            $project['project_data'][$key]['task_use_data'] = $task_use_data;
        }

        $project['count'] = $count;
        return response()->json(['code' => 1, 'message' => 'success', 'data' => $project]);
    }

    /**
     * @param array $inputs
     * @return array
     */
    public function projectOperation(array $inputs): array
    {
        $rules = [
            'project_id' => 'required|integer|min:1',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'is_margin' => 'integer|min:0|max:1',
        ];

        $attributes = [
            'project_id' => '项目ID',
            'start_date' => '开始日期',
            'end_date' => '结束日期',
            'is_margin' => '显示余量'
        ];

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $start_date = empty($inputs['start_date']) ? date('Y-m-d', strtotime('-14 day')) : $inputs['start_date'];
        $end_date = empty($inputs['end_date']) ? date('Y-m-d', time()) : $inputs['end_date'];
        $is_margin = $inputs['is_margin'] ?? 1;//是否显示余量(1显示 0隐藏)

        // 通过项目id查找对应的商务单id
        $business_project = BusinessProject::with(['hasCustomer'=>function($query){
            $query->select('id','customer_name');
        },'trade'=>function($query){
            $query->select('id','name');
        }])->where('id', $inputs['project_id'])->first()->toArray();
        $datas = [];
        if ($business_project['charge_id'] == 1 || $business_project['business_id'] == 1 || $business_project['execute_id'] == 1) {
            $datas['msg'] = '当前项目负责人/执行/商务助理为超级管理员,请设置其他人员';
        }

        //查询对应日期的反馈信息
        $project_feedbacks = ProjectFeedback::whereBetween('date',[$start_date,$end_date])
            ->where('project_id',$inputs['project_id'])
            ->orderBy('date','DESC')->get();

        if($is_margin == 0){//隐藏余量
            $start_time = date('Ymd',strtotime($start_date));
            $end_time = date('Ymd',strtotime($end_date));
            //对应日期的v3任务数据
            $project_datas = V3ProjectTaskStat::where('send_count','!=',0)
                ->whereBetween('date',[$start_time,$end_time])
                ->where('project_id',$inputs['project_id'])
                ->orderBy('date','DESC')
                ->get();
        }
        $dates = array_reverse(prDates($start_date,$end_date));
        foreach($dates as $date){
            //初始化
            $project[$date]['date'] = $date;
            $project[$date]['customer_name'] = $business_project['has_customer']['customer_name'];
            $project[$date]['trade'] = $business_project['trade']['name'];
            $project[$date]['project_name'] = $business_project['project_name'];
            $project[$date]['succ_amount'] = 0;
            $project[$date]['send_amount'] = 0;
            $project[$date]['p_send_amount'] = 0;
            $project[$date]['reach_amount'] = 0;
            $project[$date]['p_succ_amount'] = 0;
            $project[$date]['open_amount'] = 0;
            $project[$date]['open_inde_amount'] = 0;
            $project[$date]['open_ip_inde_amount'] = 0;
            $project[$date]['click_amount'] = 0;
            $project[$date]['click_inde_amount'] = 0;
            $project[$date]['click_ip_inde_amount'] = 0;
            $project[$date]['open_rate'] = 0;
            $project[$date]['click_rate'] = 0;
            $project[$date]['click_open_rate'] = 0;
            $project[$date]['email_complaint_uv'] = 0;
            $project[$date]['complaint_rate'] = 0;
            $project[$date]['complaint_open_rate'] = 0;
            $project[$date]['email_unsubscribe_uv'] = 0;
            $project[$date]['unsubscribe_rate'] = 0;
            $project[$date]['unsubscribe_open_rate'] = 0;
            $project[$date]['soft_fail'] = 0;
            $project[$date]['soft_rate'] = 0;
            $project[$date]['hard_fail'] = 0;
            $project[$date]['hard_rate'] = 0;
            $project[$date]['timeout_amount'] = 0;
            $project[$date]['succ_rate'] = 0;
            $project[$date]['cpa_amount'] = 0;
            $project[$date]['cps_amount'] = 0;
            $project[$date]['cpc_amount'] = 0;
            $project[$date]['cpd_amount'] = 0;
            $project[$date]['income'] = 0;
            $project[$date]['income_succ'] = sprintf('%.5f',0);
            $project[$date]['reg_click_rate'] = 0;
            $project[$date]['intercept'] = 0;
            $project[$date]['real_psend'] = 0;
            $project[$date]['income_cpc'] = sprintf('%.3f',0);

            if($is_margin == 1){//显示余量
                foreach($project_feedbacks as $feedback){
                    if($feedback['date'] == $date){
                        $project[$date]['succ_amount'] += $feedback['succ_amount'] - $feedback['intercept'] + $feedback['real_psend'];//实际到达数
                        $project[$date]['send_amount'] += $feedback['send_amount'];//发送数
                        $project[$date]['p_send_amount'] += $feedback['p_send_amount'];//补量发送数
                        $project[$date]['reach_amount'] += $feedback['succ_amount'];//到达数
                        $project[$date]['p_succ_amount'] += $feedback['p_succ_amount'];//补量到达数
                        $project[$date]['open_amount'] += $feedback['open_amount'];//打开PV
                        $project[$date]['open_inde_amount'] += $feedback['open_inde_amount'];//打开人数
                        $project[$date]['open_ip_inde_amount'] += $feedback['open_ip_inde_amount'];//ip独立打开
                        $project[$date]['click_amount'] += $feedback['click_amount'];//点击PV
                        $project[$date]['click_inde_amount'] += $feedback['click_inde_amount'];//点击人数
                        $project[$date]['click_ip_inde_amount'] += $feedback['click_ip_inde_amount'];//ip独立点击
                        $project[$date]['open_rate'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.4f', $project[$date]['open_inde_amount'] / $project[$date]['succ_amount'] ) : 0;//打开率
                        $project[$date]['click_rate'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.4f', $project[$date]['click_inde_amount'] / $project[$date]['succ_amount'] ) : 0;//点击率
                        $project[$date]['click_open_rate'] = $project[$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $project[$date]['click_inde_amount'] / $project[$date]['open_inde_amount'] ) : 0;//点击打开比
                        $project[$date]['email_complaint_uv'] += $feedback['email_complaint_uv'];//投诉数
                        $project[$date]['complaint_rate'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_complaint_uv'] / $project[$date]['succ_amount'] ) : 0;//投诉率
                        $project[$date]['complaint_open_rate'] = $project[$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_complaint_uv'] / $project[$date]['open_inde_amount'] ) : 0;//投诉打开比
                        $project[$date]['email_unsubscribe_uv'] += $feedback['email_unsubscribe_uv'];//退订数
                        $project[$date]['unsubscribe_rate'] = $project[$date]['reach_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_unsubscribe_uv'] / $project[$date]['reach_amount'] ) : 0;//退订率
                        $project[$date]['unsubscribe_open_rate'] = $project[$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_unsubscribe_uv'] / $project[$date]['open_inde_amount'] ) : 0;//退订打开比
                        $project[$date]['soft_fail'] += $feedback['soft_fail'];//软弹数
                        $project[$date]['soft_rate'] = $project[$date]['send_amount'] > 0 ? sprintf('%.4f', $project[$date]['soft_fail'] / $project[$date]['send_amount'] ) : 0;//软弹率
                        $project[$date]['hard_fail'] += $feedback['hard_fail'];//硬弹数
                        $project[$date]['hard_rate'] = $project[$date]['send_amount'] > 0 ? sprintf('%.4f', $project[$date]['hard_fail'] / $project[$date]['send_amount'] ) : 0;//硬弹率
                        $project[$date]['timeout_amount'] += $feedback['timeout_amount'];//超时数
                        $project[$date]['succ_rate'] = $project[$date]['send_amount'] > 0 ? sprintf('%.4f', $project[$date]['reach_amount'] / $project[$date]['send_amount'] ) : 0;//到达率
                        $project[$date]['cpa_amount'] += $feedback['cpa_amount'];//CPA注册数
                        $project[$date]['cps_amount'] += $feedback['cps_amount'];//CPS注册数
                        $project[$date]['cpc_amount'] += $feedback['cpc_amount'];//CPC点击数
                        $project[$date]['cpd_amount'] += $feedback['cpd_amount'];//CPD点击数
                        $project[$date]['income'] += $feedback['money'];//收入
                        $project[$date]['income_succ'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.5f', $project[$date]['income'] / $project[$date]['succ_amount'] ) : sprintf('%.5f',0);//单封收益
                        $project[$date]['reg_click_rate'] = $project[$date]['click_inde_amount'] > 0 ? sprintf('%.4f', ($project[$date]['cpa_amount']+$project[$date]['cps_amount']+$project[$date]['cpc_amount']+$project[$date]['cpd_amount']) / $project[$date]['click_inde_amount'] ) : 0;//注册转化率
                        $project[$date]['intercept'] += $feedback['intercept'];//拦截量
                        $project[$date]['real_psend'] += $feedback['real_psend'];//实际补量数
                        $project[$date]['income_cpc'] = $project[$date]['click_inde_amount'] > 0 ? sprintf('%.3f', $project[$date]['income'] / $project[$date]['click_inde_amount'] ) : sprintf('%.3f', 0);//点击单价
                    }
                }
            }else{//隐藏余量
                foreach($project_feedbacks as $key => $feedback){
                    if($feedback['date'] == $date){
                        $project[$date]['cpa_amount'] += $feedback['cpa_amount'];//CPA注册数
                        $project[$date]['cps_amount'] += $feedback['cps_amount'];//CPS注册数
                        $project[$date]['cpc_amount'] += $feedback['cpc_amount'];//CPC点击数
                        $project[$date]['cpd_amount'] += $feedback['cpd_amount'];//CPD点击数
                        $project[$date]['income'] += $feedback['money'];//收入
                        $project[$date]['intercept'] += $feedback['intercept'];//拦截量
                        $project[$date]['real_psend'] += $feedback['real_psend'];//实际补量数
                    }
                }
                foreach($project_datas as $key => $data){
                    $data['date'] = date('Y-m-d',strtotime($data['date']));
                    if($data['date'] == $date){
                        $project[$date]['send_amount'] += $data['send_count'];//发送数
                        $project[$date]['p_send_amount'] += $data['p_send_count'];//补量发送数
                        $project[$date]['reach_amount'] += $data['succ_count'];//到达数
                        $project[$date]['p_succ_amount'] += $data['p_succ_count'];//补量到达数
                        $project[$date]['open_amount'] += $data['open_count_pv'];//打开PV
                        $project[$date]['open_inde_amount'] += $data['open_count_email_uv'];//打开人数
                        $project[$date]['open_ip_inde_amount'] += $data['open_count_ip_uv'];//ip独立打开
                        $project[$date]['click_amount'] += $data['click_count_pv'];//点击PV
                        $project[$date]['click_inde_amount'] += $data['click_count_email_uv'];//点击人数
                        $project[$date]['click_ip_inde_amount'] += $data['click_count_ip_uv'];//ip独立点击
                        $project[$date]['email_complaint_uv'] += $data['complaint_count_email_uv'];//投诉数
                        $project[$date]['email_unsubscribe_uv'] += $data['unsubscribe_count_email_uv'];//退订数
                        $project[$date]['soft_fail'] += $data['soft_fail_count'];//软弹数
                        $project[$date]['hard_fail'] += $data['hard_fail_count'];//硬弹数
                        $project[$date]['timeout_amount'] += $data['timeout_count'];//超时数
                    }
                }
                $project[$date]['succ_amount'] += $project[$date]['reach_amount'] - $project[$date]['intercept'] + $project[$date]['real_psend'];//实际到达数
                $project[$date]['open_rate'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.4f', $project[$date]['open_inde_amount'] / $project[$date]['succ_amount'] ) : 0;//打开率
                $project[$date]['click_rate'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.4f', $project[$date]['click_inde_amount'] / $project[$date]['succ_amount'] ) : 0;//点击率
                $project[$date]['click_open_rate'] = $project[$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $project[$date]['click_inde_amount'] / $project[$date]['open_inde_amount'] ) : 0;//点击打开比
                $project[$date]['complaint_rate'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_complaint_uv'] / $project[$date]['succ_amount'] ) : 0;//投诉率
                $project[$date]['complaint_open_rate'] = $project[$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_complaint_uv'] / $project[$date]['open_inde_amount'] ) : 0;//投诉打开比
                $project[$date]['unsubscribe_rate'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_unsubscribe_uv'] / $project[$date]['succ_amount'] ) : 0;//退订率
                $project[$date]['unsubscribe_open_rate'] = $project[$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $project[$date]['email_unsubscribe_uv'] / $project[$date]['open_inde_amount'] ) : 0;//退订打开比
                $project[$date]['soft_rate'] = $project[$date]['send_amount'] > 0 ? sprintf('%.4f', $project[$date]['soft_fail'] / $project[$date]['send_amount'] ) : 0;//软弹率
                $project[$date]['hard_rate'] = $project[$date]['send_amount'] > 0 ? sprintf('%.4f', $project[$date]['hard_fail'] / $project[$date]['send_amount'] ) : 0;//硬弹率
                $project[$date]['succ_rate'] = $project[$date]['send_amount'] > 0 ? sprintf('%.4f', $project[$date]['reach_amount'] / $project[$date]['send_amount'] ) : 0;//到达率
                $project[$date]['income_succ'] = $project[$date]['succ_amount'] > 0 ? sprintf('%.5f', $project[$date]['income'] / $project[$date]['succ_amount'] ) : sprintf('%.5f',0);//单封收益
                $project[$date]['reg_click_rate'] = $project[$date]['click_inde_amount'] > 0 ? sprintf('%.4f', ($project[$date]['cpa_amount']+$project[$date]['cps_amount']+$project[$date]['cpc_amount']+$project[$date]['cpd_amount']) / $project[$date]['click_inde_amount'] ) : 0;//注册转化率
                $project[$date]['income_cpc'] = $project[$date]['click_inde_amount'] > 0 ? sprintf('%.3f', $project[$date]['income'] / $project[$date]['click_inde_amount'] ) : sprintf('%.3f',0);//点击单价
            }
        }
        $count = count($dates);
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $k = 0;
        $c = 0;
        foreach($project as $date => $value){
            unset($project[$date]);
            if( $start <= $k && $k < ($start + $length) ){
                $project[$c] = $value;
                $c++;
            }
            $k = $k + 1;
        }
        $datas['count'] = $count;
        $datas['data'] = $project;

         $datas['data_type']['succ_amount'] = '实际到达数';
         $datas['data_type']['send_amount'] = '发送数';
         $datas['data_type']['p_send_amount'] = '补量发送数';
         $datas['data_type']['reach_amount'] = '到达数';
         $datas['data_type']['p_succ_amount'] = '补量到达数';
         $datas['data_type']['open_amount'] = '打开PV';
         $datas['data_type']['open_inde_amount'] = '打开人数';
         $datas['data_type']['open_ip_inde_amount'] = 'ip独立打开';
         $datas['data_type']['click_amount'] = '点击PV';
         $datas['data_type']['click_inde_amount'] = '点击人数';
         $datas['data_type']['click_ip_inde_amount'] = 'ip独立点击';
         $datas['data_type']['open_rate'] = '打开率';
         $datas['data_type']['click_rate'] = '点击率';
         $datas['data_type']['click_open_rate'] = '点击打开比';
         $datas['data_type']['email_complaint_uv'] = '投诉数';
         $datas['data_type']['complaint_rate'] = '投诉率';
         $datas['data_type']['complaint_open_rate'] = '投诉打开比';
         $datas['data_type']['email_unsubscribe_uv'] = '退订数';
         $datas['data_type']['unsubscribe_rate'] = '退订率';
         $datas['data_type']['unsubscribe_open_rate'] = '退订打开比';
         $datas['data_type']['soft_fail'] = '软弹数';
         $datas['data_type']['soft_rate'] = '软弹率';
         $datas['data_type']['hard_fail'] = '硬弹数';
         $datas['data_type']['hard_rate'] = '硬弹率';
         $datas['data_type']['timeout_amount'] = '超时数';
         $datas['data_type']['succ_rate'] = '到达率';
         $datas['data_type']['cpa_amount'] = 'CPA注册数';
         $datas['data_type']['cps_amount'] = 'CPS注册数';
         $datas['data_type']['cpc_amount'] = 'CPC点击量';
         $datas['data_type']['income'] = '收入';
         $datas['data_type']['income_succ'] = '单封收益';
         $datas['data_type']['reg_click_rate'] = '注册转化率';
         $datas['data_type']['intercept'] = '拦截量';
         $datas['data_type']['real_psend'] = '实际补量数';
         $datas['data_type']['income_cpc'] = 'CPC点击单价';

        return ['code' => 1, 'message' => 'success', 'data' => $datas];
    }

    /**
     * @param array $inputs
     * @return array
     */
    public function showProjectList(array $inputs):array
    {
        $rules = [
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'is_margin' => 'integer|min:0|max:1',
        ];

        $attributes = [
            'start_date' => '开始日期',
            'end_date' => '结束日期',
            'is_margin' => '显示余量'
        ];

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $keyword_project = $inputs['keyword_project'] ?? '';//项目
        $keyword_trade = $inputs['keyword_trade'] ?? '';//行业id
        $keyword_group = $inputs['keyword_group'] ?? '';//组段id
        $keyword_type = $inputs['keyword_type'] ?? '';//项目类型数值
        $keyword_period = $inputs['keyword_period'] ?? 1;//周期数值
        if ($keyword_period == 2) {
            $start_date = empty($inputs['start_date']) ? date('Y-m-d', strtotime('-6 day')) : $inputs['start_date'];
        } else {
            $start_date = empty($inputs['start_date']) ? date('Y-m-d', strtotime('-14 day')) : $inputs['start_date'];
        }
        $end_date = empty($inputs['end_date']) ? date('Y-m-d', time()) : $inputs['end_date'];
        if($start_date > date('Y-m-d',time())){
            return ['code' => 0, 'message' => '选择日期不能超过今天'];
        }
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $dates = array_reverse(prDates($start_date, $end_date));
        $day = count($dates);
        if ($keyword_period == 2 && $day > 7) {
            return ['code' => 0, 'message' => '选择周期是周的时候，日期区间不能超过7天'];
        }
        $is_margin = $inputs['is_margin'] ?? 1;//是否显示余量(1显示 0隐藏)

        //20190603格式
        $start_day = date('Ymd', strtotime($start_date));
        $end_day = date('Ymd', strtotime($end_date . ' 23:59:59'));

        //设置缓存
        if(!in_array(date('Y-m-d',time()),$dates)){//当天的情况不缓存
            $key = md5($start_date.$end_date.$is_margin.$keyword_project.$keyword_trade.$keyword_group.$keyword_type.$keyword_period.$start.$length);
            $location = \cache()->store('file')->remember($key,1440,function() use($is_margin,$keyword_project,$keyword_trade,$keyword_group,$keyword_type,$keyword_period,$start,$length,$dates,$start_day,$end_day){
                return $this->isProjectListCache($keyword_project, $keyword_group, $keyword_trade, $keyword_type, $keyword_period, $is_margin, $start_day, $end_day, $start, $length, $dates);
            });
        }else{
            $location =  $this->isProjectListCache($keyword_project, $keyword_group, $keyword_trade, $keyword_type, $keyword_period, $is_margin, $start_day, $end_day, $start, $length, $dates);
        }
        return ['code' => 1, 'message' => '获取成功', 'data' => $location];


    }

    private function exportSingleExcel($count = 0, $theads = [], $tbodys = [], $sheet_name = null, $file_name = null, $path = 'app/public/temps', $suffix = 'xls')
    {
        $file_name = $file_name ? $file_name : date('YmdHis') . uniqid();
        $sheet_name = $sheet_name ? $sheet_name : 'Sheet1';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return Excel::create($file_name, function ($excel) use ($count,$theads, $tbodys, $sheet_name) {
            $cell_data = array_merge([$theads], $tbodys);
            $excel->sheet($sheet_name, function ($sheet) use ($cell_data,$count) {
                $sheet->fromArray($cell_data, null, 'A1', true, false);
                $max_string_column = $sheet->getHighestColumn();
                $max_index_column = \PHPExcel_Cell::columnIndexFromString($max_string_column) - 1; // 英文字母索引转数字索引
                $cell_size = [];
                for ($i = 0; $i <= $max_index_column; $i++) {
                    $cell_size[\PHPExcel_Cell::stringFromColumnIndex($i)] = 15;
                }
                $sheet->setWidth($cell_size); // 设置单元格宽度
                $style = ['alignment' => ['horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER, // 水平居中
                    'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER, // 垂直居中
                ]];
                $sheet->getDefaultStyle()->applyFromArray($style);
                $max_index_row = $sheet->getHighestRow();
                if($count){
                    $start_number = 2;
                    $end_number = $start_number + $count;
                    $start_index_column = \PHPExcel_Cell::columnIndexFromString('G') - 1;
                    for($i=1;$i<$max_index_row;$i=$i+$count+1){
                        $sheet->insertNewRowBefore($end_number+1,1);
                        $sheet->mergeCells( 'A'.$start_number .':'. 'A' .$end_number);
                        $sheet->mergeCells( 'B'.$start_number .':' .'B' .$end_number);
                        $sheet->mergeCells( 'C'.$start_number .':' .'C' .$end_number);
                        $sheet->mergeCells( 'D'.$start_number .':' .'D' .$end_number);
                        $sheet->mergeCells( 'E'.$start_number .':' .'E' .$end_number);
                        $sheet->setCellValue('A'.($end_number+1),'合计');
                        $sheet->mergeCells( 'A'.($end_number+1) .':' .'F' .($end_number+1));
                        $sheet->getStyle('A'.($end_number+1))->getFont()->setBold(true);
                        $sheet->getStyle('A'.($end_number+1))->getFont()->setSize(14);
                        for($j=$start_index_column; $j<=$max_index_column; $j++){
                            $row = \PHPExcel_Cell::stringFromColumnIndex($j);
                            $sheet->setCellValue($row.($end_number+1),'=SUM(' . $row.$start_number . ':' . $row.$end_number .')');
                            $sheet->getStyle($row.($end_number+1))->getFont()->setBold(true);
                            $sheet->getStyle($row.($end_number+1))->getFont()->setSize(14);
                        }
                        $sheet->getStyle('A'.($end_number+1).':'.$max_string_column.($end_number+1))->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('ccffff');
                        $sheet->getStyle('A'.$start_number .':'. 'A' .$end_number)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        $sheet->getStyle('A'.$start_number .':'. 'A' .$end_number)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('A'.($end_number+1) .':' .'F' .($end_number+1))->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                        $sheet->getStyle('A'.($end_number+1) .':' .'F' .($end_number+1))->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                        $start_number = $start_number + $count + 2;
                        $end_number = $end_number + $count + 2;
                    }
                }
            });
        })->store($suffix, storage_path($path), true);
    }

    /**
     * @param string $keyword_project
     * @param string $keyword_group
     * @param string $keyword_trade
     * @param string $keyword_type
     * @param int $keyword_period
     * @param int $is_margin
     * @param $start_day
     * @param $end_day
     * @param int $start
     * @param int $length
     * @param array $dates
     * @return array
     */
    public function isProjectListCache(string $keyword_project, string $keyword_group, string $keyword_trade, string $keyword_type, int $keyword_period, int $is_margin, $start_day, $end_day, int $start, int $length, array $dates, array $v3_datas = [], array $project_feedback_array = []): array
    {
        // 查找项目情况
        $business_project = BusinessProject::with(['hasCustomer' => function ($query) {
            $query->select('id', 'customer_name');
        }, 'trade' => function ($query) {
            $query->select('id', 'name');
        }, 'projectGroup' => function ($query) {
            $query->select('id', 'name');
        }])->when($keyword_project, function ($query) use ($keyword_project) {
            $query->where('project_name', 'like', '%' . $keyword_project . '%');
        })->when($keyword_group, function ($query) use ($keyword_group) {
            $query->where('group_id', $keyword_group);
        })->when($keyword_trade, function ($query) use ($keyword_trade) {
            $query->where('trade_id', $keyword_trade);
        })->when($keyword_type, function ($query) use ($keyword_type) {
            $query->where('resource_type', $keyword_type);
        })->select('id', 'project_name', 'resource_type', 'customer_id', 'trade_id', 'group_id')
            ->get()->toArray();
        //列出所有符合条件的项目id
        $business_project_array = [];
        foreach ($business_project as $val) {
            $business_project_array[$val['id']] = $val['id'];
        }
        //获取分页对应项目id和日期
        if ($keyword_period == 1) {
            if ($is_margin == 1) {//显示余量
                $v3_query = V3ProjectTaskStat::whereIn('project_id', $business_project_array)
                    ->whereBetween('date', [$start_day, $end_day])
                    ->select(DB::raw('project_id, date,SUM(succ_count) as succ_amount'))
                    ->groupBy('date', 'project_id')->orderBy('date', 'DESC')->orderBy('succ_amount','DESC');
            } else {//隐藏余量
                $v3_query = V3ProjectTaskStat::where('send_count', '>', 0)
                    ->whereIn('project_id', $business_project_array)
                    ->whereBetween('date', [$start_day, $end_day])
                    ->select(DB::raw('project_id, date,SUM(succ_count) as succ_amount'))
                    ->groupBy('date', 'project_id')->orderBy('date', 'DESC')->orderBy('succ_amount','DESC');
            }
            $counts = $v3_query->get()->count();//数据总数
            $v3_selects = $v3_query->skip($start)->take($length)->get()->toArray();
        } else {
            if ($is_margin == 1) {//显示余量
                $v3_query = V3ProjectTaskStat::whereIn('project_id', $business_project_array)
                    ->whereBetween('date', [$start_day, $end_day])
                    ->select('project_id')
                    ->groupBy('project_id');
            } else {//隐藏余量
                $v3_query = V3ProjectTaskStat::where('send_count', '>', 0)
                    ->whereIn('project_id', $business_project_array)
                    ->whereBetween('date', [$start_day, $end_day])
                    ->select('project_id')
                    ->groupBy('project_id');
            }
            $counts = $v3_query->get()->count();//数据总数
            $v3_selects = $v3_query->skip($start)->take($length)->get()->toArray();
        }
        $project_array = [];
        $day_array = [];
        foreach ($v3_selects as $val) {
            if (!empty($val['date'])) {
                $project_array[$val['date']][$val['project_id']] = $val['project_id'];
                $day_array[$val['date']] = $val['date'];
            } else {
                $project_array[0][$val['project_id']] = $val['project_id'];
                $date_array = $dates;
            }
        }

        //时间转换为20190603
        if (empty($day_array) && !empty($date_array)) {
            $day_array = [];
            foreach ($date_array as $val) {
                $day_array[] = date('Ymd', strtotime($val));
            }
        }
        //获取对应日期和项目id的发送情况
        if ($is_margin == 1) {//显示余量
            if (count($project_array) > 1) {
                foreach ($project_array as $key => $project) {
                    $v3_datas[] = V3ProjectTaskStat::where('date', $key)
                        ->whereIn('project_id', $project)
                        ->select('project_id', 'date', 'succ_count', 'send_count', 'p_send_count', 'succ_count', 'p_succ_count', 'open_count_pv', 'open_count_email_uv', 'open_count_ip_uv', 'click_count_pv', 'click_count_email_uv', 'click_count_ip_uv', 'complaint_count_email_uv', 'unsubscribe_count_email_uv', 'soft_fail_count', 'hard_fail_count', 'timeout_count')
                        ->orderBy('date', 'DESC')->orderBy('project_id')
                        ->get()->toArray();
                }
            } else {
                foreach ($project_array as $project) {
                    $v3_datas[] = V3ProjectTaskStat::whereIn('date', $day_array)
                        ->whereIn('project_id', $project)
                        ->select('project_id', 'date', 'succ_count', 'send_count', 'p_send_count', 'succ_count', 'p_succ_count', 'open_count_pv', 'open_count_email_uv', 'open_count_ip_uv', 'click_count_pv', 'click_count_email_uv', 'click_count_ip_uv', 'complaint_count_email_uv', 'unsubscribe_count_email_uv', 'soft_fail_count', 'hard_fail_count', 'timeout_count')
                        ->orderBy('date', 'DESC')->orderBy('project_id')
                        ->get()->toArray();
                }
            }
        } else {//隐藏余量
            if (count($project_array) > 1) {
                foreach ($project_array as $key => $project) {
                    $v3_datas[] = V3ProjectTaskStat::where('date', $key)
                        ->whereIn('project_id', $project)
                        ->where('send_count', '>', 0)
                        ->select('project_id', 'date', 'succ_count', 'send_count', 'p_send_count', 'succ_count', 'p_succ_count', 'open_count_pv', 'open_count_email_uv', 'open_count_ip_uv', 'click_count_pv', 'click_count_email_uv', 'click_count_ip_uv', 'complaint_count_email_uv', 'unsubscribe_count_email_uv', 'soft_fail_count', 'hard_fail_count', 'timeout_count')
                        ->orderBy('date', 'DESC')->orderBy('project_id')
                        ->get()->toArray();
                }
            } else {
                foreach ($project_array as $project) {
                    $v3_datas[] = V3ProjectTaskStat::whereIn('date', $day_array)
                        ->whereIn('project_id', $project)
                        ->where('send_count', '>', 0)
                        ->select('project_id', 'date', 'succ_count', 'send_count', 'p_send_count', 'succ_count', 'p_succ_count', 'open_count_pv', 'open_count_email_uv', 'open_count_ip_uv', 'click_count_pv', 'click_count_email_uv', 'click_count_ip_uv', 'complaint_count_email_uv', 'unsubscribe_count_email_uv', 'soft_fail_count', 'hard_fail_count', 'timeout_count')
                        ->orderBy('date', 'DESC')->orderBy('project_id')
                        ->get()->toArray();
                }
            }
        }

        //时间转换为2019-06-03
        if (empty($date_array)) {
            $date_array = [];
            foreach ($day_array as $val) {
                $date_array[] = date('Y-m-d', strtotime($val));
            }
        }

        //查询对应日期的反馈信息
        foreach ($project_array as $val) {
            foreach ($val as $value) {
                $project_feedback_array[] = $value;
            }
        }
        if (!empty($project_feedback_array)) {
            $project_feedbacks = ProjectFeedback::whereIn('date', $date_array)
                ->whereIn('project_id', $project_feedback_array)
                ->select('project_id', 'date', 'cpa_amount', 'cps_amount', 'cpc_amount', 'cpd_amount', 'money', 'intercept', 'real_psend')
                ->orderBy('date', 'DESC')->orderBy('project_id')->get()->toArray();
        }
        //以id为键的项目信息
        $project_datas = [];
        foreach ($business_project as $val) {
            $project_datas[$val['id']] = $val;
        }
        $total_datas = [];
        if (count($project_array) > 1) {
            foreach ($project_array as $date => $project_arr) {
                foreach ($project_arr as $id) {
                    //初始化
                    $total_datas[$id][$date]['id'] = $id;
                    $total_datas[$id][$date]['company'] = $project_datas[$id]['has_customer']['customer_name'];
                    $total_datas[$id][$date]['trade'] = $project_datas[$id]['trade']['name'];
                    $total_datas[$id][$date]['project_name'] = $project_datas[$id]['project_name'];
                    $total_datas[$id][$date]['group'] = $project_datas[$id]['project_group']['name'];
                    switch ($project_datas[$id]['resource_type']) {
                        case 1:
                            $total_datas[$id][$date]['resource_type'] = '正常投递';
                            break;
                        case 2:
                            $total_datas[$id][$date]['resource_type'] = '触发';
                            break;
                        case 3:
                            $total_datas[$id][$date]['resource_type'] = '特殊组段';
                            break;
                    }
                    $total_datas[$id][$date]['date'] = date('Y-m-d', strtotime($date));
                    $total_datas[$id][$date]['succ_amount'] = 0;
                    $total_datas[$id][$date]['send_amount'] = 0;
                    $total_datas[$id][$date]['p_send_amount'] = 0;
                    $total_datas[$id][$date]['reach_amount'] = 0;
                    $total_datas[$id][$date]['p_succ_amount'] = 0;
                    $total_datas[$id][$date]['open_amount'] = 0;
                    $total_datas[$id][$date]['open_inde_amount'] = 0;
                    $total_datas[$id][$date]['open_ip_inde_amount'] = 0;
                    $total_datas[$id][$date]['click_amount'] = 0;
                    $total_datas[$id][$date]['click_inde_amount'] = 0;
                    $total_datas[$id][$date]['click_ip_inde_amount'] = 0;
                    $total_datas[$id][$date]['open_rate'] = 0;
                    $total_datas[$id][$date]['click_rate'] = 0;
                    $total_datas[$id][$date]['click_open_rate'] = 0;
                    $total_datas[$id][$date]['email_complaint_uv'] = 0;
                    $total_datas[$id][$date]['complaint_rate'] = 0;
                    $total_datas[$id][$date]['complaint_open_rate'] = 0;
                    $total_datas[$id][$date]['email_unsubscribe_uv'] = 0;
                    $total_datas[$id][$date]['unsubscribe_rate'] = 0;
                    $total_datas[$id][$date]['unsubscribe_open_rate'] = 0;
                    $total_datas[$id][$date]['soft_fail'] = 0;
                    $total_datas[$id][$date]['soft_rate'] = 0;
                    $total_datas[$id][$date]['hard_fail'] = 0;
                    $total_datas[$id][$date]['hard_rate'] = 0;
                    $total_datas[$id][$date]['timeout_amount'] = 0;
                    $total_datas[$id][$date]['succ_rate'] = 0;
                    $total_datas[$id][$date]['cpa_amount'] = 0;
                    $total_datas[$id][$date]['cps_amount'] = sprintf('%.2f', 0);
                    $total_datas[$id][$date]['cpc_amount'] = 0;
                    $total_datas[$id][$date]['cpd_amount'] = 0;
                    $total_datas[$id][$date]['income'] = sprintf('%.2f', 0);
                    $total_datas[$id][$date]['income_succ'] = sprintf('%.5f', 0);
                    $total_datas[$id][$date]['reg_click_rate'] = 0;
                    $total_datas[$id][$date]['intercept'] = 0;
                    $total_datas[$id][$date]['real_psend'] = 0;
                    $total_datas[$id][$date]['income_cpc'] = sprintf('%.3f', 0);
                    foreach ($v3_datas as $val) {
                        foreach ($val as $v3_data) {
                            if ($v3_data['project_id'] == $id && $v3_data['date'] == $date) {
                                //项目信息
                                if (!empty($project_datas[$id])) {
                                    $total_datas[$id][$date]['id'] = $id;
                                    $total_datas[$id][$date]['company'] = $project_datas[$id]['has_customer']['customer_name'];
                                    $total_datas[$id][$date]['trade'] = $project_datas[$id]['trade']['name'];
                                    $total_datas[$id][$date]['project_name'] = $project_datas[$id]['project_name'];
                                    $total_datas[$id][$date]['group'] = $project_datas[$id]['project_group']['name'];
                                    switch ($project_datas[$id]['resource_type']) {
                                        case 1:
                                            $total_datas[$id][$date]['resource_type'] = '正常投递';
                                            break;
                                        case 2:
                                            $total_datas[$id][$date]['resource_type'] = '触发';
                                            break;
                                        case 3:
                                            $total_datas[$id][$date]['resource_type'] = '特殊组段';
                                            break;
                                    }
                                }
                                //项目反馈数据
                                if (!empty($project_feedbacks)) {
                                    $fdate = date('Y-m-d', strtotime($date));
                                    foreach ($project_feedbacks as $feedback) {
                                        if ($feedback['project_id'] == $id && $feedback['date'] == $fdate) {
                                            $total_datas[$id][$date]['cpa_amount'] = $feedback['cpa_amount'];//CPA注册数
                                            $total_datas[$id][$date]['cps_amount'] = $feedback['cps_amount'];//CPS注册数
                                            $total_datas[$id][$date]['cpc_amount'] = $feedback['cpc_amount'];//CPC点击数
                                            $total_datas[$id][$date]['cpd_amount'] = $feedback['cpd_amount'];//CPD点击数
                                            $total_datas[$id][$date]['income'] = $feedback['money'];//收入
                                            $total_datas[$id][$date]['intercept'] += $feedback['intercept'];//拦截量
                                            $total_datas[$id][$date]['real_psend'] += $feedback['real_psend'];//实际补量数
                                        }
                                    }
                                }
                                //项目数据
                                $total_datas[$id][$date]['date'] = date('Y-m-d', strtotime($date));
                                $total_datas[$id][$date]['send_amount'] += $v3_data['send_count'];//发送数
                                $total_datas[$id][$date]['p_send_amount'] += $v3_data['p_send_count'];//补量发送数
                                $total_datas[$id][$date]['reach_amount'] += $v3_data['succ_count'];//到达数
                                $total_datas[$id][$date]['p_succ_amount'] += $v3_data['p_succ_count'];//补量到达数
                                $total_datas[$id][$date]['open_amount'] += $v3_data['open_count_pv'];//打开PV
                                $total_datas[$id][$date]['open_inde_amount'] += $v3_data['open_count_email_uv'];//打开人数
                                $total_datas[$id][$date]['open_ip_inde_amount'] += $v3_data['open_count_ip_uv'];//ip独立打开
                                $total_datas[$id][$date]['click_amount'] += $v3_data['click_count_pv'];//点击PV
                                $total_datas[$id][$date]['click_inde_amount'] += $v3_data['click_count_email_uv'];//点击人数
                                $total_datas[$id][$date]['click_ip_inde_amount'] += $v3_data['click_count_ip_uv'];//ip独立点击
                                $total_datas[$id][$date]['email_complaint_uv'] += $v3_data['complaint_count_email_uv'];//投诉数
                                $total_datas[$id][$date]['email_unsubscribe_uv'] += $v3_data['unsubscribe_count_email_uv'];//退订数
                                $total_datas[$id][$date]['soft_fail'] += $v3_data['soft_fail_count'];//软弹数
                                $total_datas[$id][$date]['hard_fail'] += $v3_data['hard_fail_count'];//硬弹数
                                $total_datas[$id][$date]['timeout_amount'] += $v3_data['timeout_count'];//超时数

                            }
                        }
                    }
                    $total_datas[$id][$date]['succ_amount'] = $total_datas[$id][$date]['reach_amount'] - $total_datas[$id][$date]['intercept'] + $total_datas[$id][$date]['real_psend'];//实际到达数
                    $total_datas[$id][$date]['open_rate'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['open_inde_amount'] / $total_datas[$id][$date]['succ_amount']) : 0;//打开率
                    $total_datas[$id][$date]['click_rate'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['click_inde_amount'] / $total_datas[$id][$date]['succ_amount']) : 0;//点击率
                    $total_datas[$id][$date]['click_open_rate'] = $total_datas[$id][$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['click_inde_amount'] / $total_datas[$id][$date]['open_inde_amount']) : 0;//点击打开比
                    $total_datas[$id][$date]['complaint_rate'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_complaint_uv'] / $total_datas[$id][$date]['succ_amount']) : 0;//投诉率
                    $total_datas[$id][$date]['complaint_open_rate'] = $total_datas[$id][$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_complaint_uv'] / $total_datas[$id][$date]['open_inde_amount']) : 0;//投诉打开比
                    $total_datas[$id][$date]['unsubscribe_rate'] = $total_datas[$id][$date]['reach_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_unsubscribe_uv'] / $total_datas[$id][$date]['reach_amount']) : 0;//退订率
                    $total_datas[$id][$date]['unsubscribe_open_rate'] = $total_datas[$id][$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_unsubscribe_uv'] / $total_datas[$id][$date]['open_inde_amount']) : 0;//退订打开比
                    $total_datas[$id][$date]['soft_rate'] = $total_datas[$id][$date]['send_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['soft_fail'] / $total_datas[$id][$date]['send_amount']) : 0;//软弹率
                    $total_datas[$id][$date]['hard_rate'] = $total_datas[$id][$date]['send_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['hard_fail'] / $total_datas[$id][$date]['send_amount']) : 0;//硬弹率
                    $total_datas[$id][$date]['succ_rate'] = $total_datas[$id][$date]['send_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['reach_amount'] / $total_datas[$id][$date]['send_amount']) : 0;//到达率
                    $total_datas[$id][$date]['income_succ'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.5f', $total_datas[$id][$date]['income'] / $total_datas[$id][$date]['succ_amount']) : sprintf('%.5f', 0);//单封收益
                    $total_datas[$id][$date]['reg_click_rate'] = $total_datas[$id][$date]['click_inde_amount'] > 0 ? sprintf('%.4f', ($total_datas[$id][$date]['cpa_amount'] + $total_datas[$id][$date]['cps_amount'] + $total_datas[$id][$date]['cpc_amount'] + $total_datas[$id][$date]['cpd_amount']) / $total_datas[$id][$date]['click_inde_amount']) : 0;//注册转化率
                    $total_datas[$id][$date]['income_cpc'] = $total_datas[$id][$date]['click_inde_amount'] > 0 ? sprintf('%.3f', $total_datas[$id][$date]['income'] / $total_datas[$id][$date]['click_inde_amount']) : sprintf('%.3f', 0);//点击单价

                }
            }
        } else {
            foreach ($project_array as $project_arr) {
                foreach ($project_arr as $id) {
                    foreach ($day_array as $date) {
                        //初始化
                        $total_datas[$id][$date]['id'] = $id;
                        $total_datas[$id][$date]['company'] = $project_datas[$id]['has_customer']['customer_name'];
                        $total_datas[$id][$date]['trade'] = $project_datas[$id]['trade']['name'];
                        $total_datas[$id][$date]['project_name'] = $project_datas[$id]['project_name'];
                        $total_datas[$id][$date]['group'] = $project_datas[$id]['project_group']['name'];
                        switch ($project_datas[$id]['resource_type']) {
                            case 1:
                                $total_datas[$id][$date]['resource_type'] = '正常投递';
                                break;
                            case 2:
                                $total_datas[$id][$date]['resource_type'] = '触发';
                                break;
                            case 3:
                                $total_datas[$id][$date]['resource_type'] = '特殊组段';
                                break;
                        }
                        $total_datas[$id][$date]['date'] = date('Y-m-d', strtotime($date));
                        $total_datas[$id][$date]['succ_amount'] = 0;
                        $total_datas[$id][$date]['send_amount'] = 0;
                        $total_datas[$id][$date]['p_send_amount'] = 0;
                        $total_datas[$id][$date]['reach_amount'] = 0;
                        $total_datas[$id][$date]['p_succ_amount'] = 0;
                        $total_datas[$id][$date]['open_amount'] = 0;
                        $total_datas[$id][$date]['open_inde_amount'] = 0;
                        $total_datas[$id][$date]['open_ip_inde_amount'] = 0;
                        $total_datas[$id][$date]['click_amount'] = 0;
                        $total_datas[$id][$date]['click_inde_amount'] = 0;
                        $total_datas[$id][$date]['click_ip_inde_amount'] = 0;
                        $total_datas[$id][$date]['open_rate'] = 0;
                        $total_datas[$id][$date]['click_rate'] = 0;
                        $total_datas[$id][$date]['click_open_rate'] = 0;
                        $total_datas[$id][$date]['email_complaint_uv'] = 0;
                        $total_datas[$id][$date]['complaint_rate'] = 0;
                        $total_datas[$id][$date]['complaint_open_rate'] = 0;
                        $total_datas[$id][$date]['email_unsubscribe_uv'] = 0;
                        $total_datas[$id][$date]['unsubscribe_rate'] = 0;
                        $total_datas[$id][$date]['unsubscribe_open_rate'] = 0;
                        $total_datas[$id][$date]['soft_fail'] = 0;
                        $total_datas[$id][$date]['soft_rate'] = 0;
                        $total_datas[$id][$date]['hard_fail'] = 0;
                        $total_datas[$id][$date]['hard_rate'] = 0;
                        $total_datas[$id][$date]['timeout_amount'] = 0;
                        $total_datas[$id][$date]['succ_rate'] = 0;
                        $total_datas[$id][$date]['cpa_amount'] = 0;
                        $total_datas[$id][$date]['cps_amount'] = sprintf('%.2f', 0);
                        $total_datas[$id][$date]['cpc_amount'] = 0;
                        $total_datas[$id][$date]['cpd_amount'] = 0;
                        $total_datas[$id][$date]['income'] = sprintf('%.2f', 0);
                        $total_datas[$id][$date]['income_succ'] = sprintf('%.5f', 0);
                        $total_datas[$id][$date]['reg_click_rate'] = 0;
                        $total_datas[$id][$date]['intercept'] = 0;
                        $total_datas[$id][$date]['real_psend'] = 0;
                        $total_datas[$id][$date]['income_cpc'] = sprintf('%.3f', 0);
                        foreach ($v3_datas as $val) {
                            foreach ($val as $v3_data) {
                                if ($v3_data['project_id'] == $id && $v3_data['date'] == $date) {
                                    //项目信息
                                    if (!empty($project_datas[$id])) {
                                        $total_datas[$id][$date]['id'] = $id;
                                        $total_datas[$id][$date]['company'] = $project_datas[$id]['has_customer']['customer_name'];
                                        $total_datas[$id][$date]['trade'] = $project_datas[$id]['trade']['name'];
                                        $total_datas[$id][$date]['project_name'] = $project_datas[$id]['project_name'];
                                        $total_datas[$id][$date]['group'] = $project_datas[$id]['project_group']['name'];
                                        switch ($project_datas[$id]['resource_type']) {
                                            case 1:
                                                $total_datas[$id][$date]['resource_type'] = '正常投递';
                                                break;
                                            case 2:
                                                $total_datas[$id][$date]['resource_type'] = '触发';
                                                break;
                                            case 3:
                                                $total_datas[$id][$date]['resource_type'] = '特殊组段';
                                                break;
                                        }
                                    }
                                    //项目反馈数据
                                    if (!empty($project_feedbacks)) {
                                        $fdate = date('Y-m-d', strtotime($date));
                                        foreach ($project_feedbacks as $feedback) {
                                            if ($feedback['project_id'] == $id && $feedback['date'] == $fdate) {
                                                $total_datas[$id][$date]['cpa_amount'] = $feedback['cpa_amount'];//CPA注册数
                                                $total_datas[$id][$date]['cps_amount'] = $feedback['cps_amount'];//CPS注册数
                                                $total_datas[$id][$date]['cpc_amount'] = $feedback['cpc_amount'];//CPC点击数
                                                $total_datas[$id][$date]['cpd_amount'] = $feedback['cpd_amount'];//CPD点击数
                                                $total_datas[$id][$date]['income'] = $feedback['money'];//收入
                                                $total_datas[$id][$date]['intercept'] = $feedback['intercept'];//拦截量
                                                $total_datas[$id][$date]['real_psend'] = $feedback['real_psend'];//实际补量数
                                            }
                                        }
                                    }
                                    //项目数据
                                    $total_datas[$id][$date]['date'] = date('Y-m-d', strtotime($date));
                                    $total_datas[$id][$date]['send_amount'] += $v3_data['send_count'];//发送数
                                    $total_datas[$id][$date]['p_send_amount'] += $v3_data['p_send_count'];//补量发送数
                                    $total_datas[$id][$date]['reach_amount'] += $v3_data['succ_count'];//到达数
                                    $total_datas[$id][$date]['p_succ_amount'] += $v3_data['p_succ_count'];//补量到达数
                                    $total_datas[$id][$date]['open_amount'] += $v3_data['open_count_pv'];//打开PV
                                    $total_datas[$id][$date]['open_inde_amount'] += $v3_data['open_count_email_uv'];//打开人数
                                    $total_datas[$id][$date]['open_ip_inde_amount'] += $v3_data['open_count_ip_uv'];//ip独立打开
                                    $total_datas[$id][$date]['click_amount'] += $v3_data['click_count_pv'];//点击PV
                                    $total_datas[$id][$date]['click_inde_amount'] += $v3_data['click_count_email_uv'];//点击人数
                                    $total_datas[$id][$date]['click_ip_inde_amount'] += $v3_data['click_count_ip_uv'];//ip独立点击
                                    $total_datas[$id][$date]['email_complaint_uv'] += $v3_data['complaint_count_email_uv'];//投诉数
                                    $total_datas[$id][$date]['email_unsubscribe_uv'] += $v3_data['unsubscribe_count_email_uv'];//退订数
                                    $total_datas[$id][$date]['soft_fail'] += $v3_data['soft_fail_count'];//软弹数
                                    $total_datas[$id][$date]['hard_fail'] += $v3_data['hard_fail_count'];//硬弹数
                                    $total_datas[$id][$date]['timeout_amount'] += $v3_data['timeout_count'];//超时数

                                }
                            }
                        }
                        $total_datas[$id][$date]['succ_amount'] = $total_datas[$id][$date]['reach_amount'] + $v3_data['p_succ_count'];//实际到达数
                        $total_datas[$id][$date]['open_rate'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['open_inde_amount'] / $total_datas[$id][$date]['succ_amount']) : 0;//打开率
                        $total_datas[$id][$date]['click_rate'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['click_inde_amount'] / $total_datas[$id][$date]['succ_amount']) : 0;//点击率
                        $total_datas[$id][$date]['click_open_rate'] = $total_datas[$id][$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['click_inde_amount'] / $total_datas[$id][$date]['open_inde_amount']) : 0;//点击打开比
                        $total_datas[$id][$date]['complaint_rate'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_complaint_uv'] / $total_datas[$id][$date]['succ_amount']) : 0;//投诉率
                        $total_datas[$id][$date]['complaint_open_rate'] = $total_datas[$id][$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_complaint_uv'] / $total_datas[$id][$date]['open_inde_amount']) : 0;//投诉打开比
                        $total_datas[$id][$date]['unsubscribe_rate'] = $total_datas[$id][$date]['reach_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_unsubscribe_uv'] / $total_datas[$id][$date]['reach_amount']) : 0;//退订率
                        $total_datas[$id][$date]['unsubscribe_open_rate'] = $total_datas[$id][$date]['open_inde_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['email_unsubscribe_uv'] / $total_datas[$id][$date]['open_inde_amount']) : 0;//退订打开比
                        $total_datas[$id][$date]['soft_rate'] = $total_datas[$id][$date]['send_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['soft_fail'] / $total_datas[$id][$date]['send_amount']) : 0;//软弹率
                        $total_datas[$id][$date]['hard_rate'] = $total_datas[$id][$date]['send_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['hard_fail'] / $total_datas[$id][$date]['send_amount']) : 0;//硬弹率
                        $total_datas[$id][$date]['succ_rate'] = $total_datas[$id][$date]['send_amount'] > 0 ? sprintf('%.4f', $total_datas[$id][$date]['reach_amount'] / $total_datas[$id][$date]['send_amount']) : 0;//到达率
                        $total_datas[$id][$date]['income_succ'] = $total_datas[$id][$date]['succ_amount'] > 0 ? sprintf('%.5f', $total_datas[$id][$date]['income'] / $total_datas[$id][$date]['succ_amount']) : sprintf('%.5f', 0);//单封收益
                        $total_datas[$id][$date]['reg_click_rate'] = $total_datas[$id][$date]['click_inde_amount'] > 0 ? sprintf('%.4f', ($total_datas[$id][$date]['cpa_amount'] + $total_datas[$id][$date]['cps_amount'] + $total_datas[$id][$date]['cpc_amount'] + $total_datas[$id][$date]['cpd_amount']) / $total_datas[$id][$date]['click_inde_amount']) : 0;//注册转化率
                        $total_datas[$id][$date]['income_cpc'] = $total_datas[$id][$date]['click_inde_amount'] > 0 ? sprintf('%.3f', $total_datas[$id][$date]['income'] / $total_datas[$id][$date]['click_inde_amount']) : sprintf('%.3f', 0);//点击单价

                    }
                }
            }
        }

        $last_datas = [];
        $last_datas['count'] = $counts;
        $last_datas['data'] = [];
        if ($keyword_period == 1) {
            foreach ($total_datas as $total_data) {
                foreach ($total_data as $val) {
                    if ($val['project_name']) {
                        $last_datas['data'][] = $val;
                    }
                }
            }
        } else {
            $k = 0;
            foreach ($total_datas as $id => $total_data) {
                foreach ($total_data as $val) {
                    $last_datas['data'][$k][] = $val;
                }
                $k++;
            }
        }
        return $last_datas;
    }
}

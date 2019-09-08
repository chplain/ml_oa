<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BusinessOrderCustomerController extends Controller
{
    //商务单客户
    /**
     * 添加客户
     * @Author: molin
     * @Date:   2019-01-07
     */
    public function store(){
    	$inputs = request()->all();
    	$data = array();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
    		$user = new \App\Models\User;
    		$user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
    		$data['user_list'] = $user_list;
    		$data['customer_type'] = [['id'=> 1,'name' => '直客'], ['id'=> 2, 'name' => '渠道']];
            //合同类型 1.EDM技术服务合同 2.系统开发合同 3.服务器托管合同 4.代理合同 5.购销合同 6.API
            $data['type_list'] = [['id' => 1, 'name' => 'EDM技术服务合同'], ['id' => 2, 'name' => '系统开发合同'], ['id' => 3, 'name' => '服务器托管合同'], ['id' => 4, 'name' => '代理合同'], ['id' => 5, 'name' => '购销合同'], ['id' => 6, 'name' => 'API']];
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'upload'){
            $upload_file = \request()->file('file');
            if(!empty($upload_file)){
                $ext = $upload_file->getClientOriginalExtension();//扩展名
                $type_arr = ['jpg','png','gif','doc','docx','pdf'];
                if(!in_array($ext, $type_arr)){
                    return response()->json(['code' => -1, 'message' => '只能上传图片、word文档、pdf文件！！', 'data' => ['type' => $ext]]);
                }
                $size = $upload_file->getSize();//文件大小  限制大小10M
                if($size > 10485760){
                    return response()->json(['code' => -1, 'message' => '只能上传10M以内的图片！！', 'data' => null]);
                }
                if(!\File::isDirectory(storage_path('app/public/uploads/contracts/'))){
                    \File::makeDirectory(storage_path('app/public/uploads/contracts/'),  $mode = 0777, $recursive = false);
                }
                //重命名
                $fileName = date('YmdHis').uniqid().'.'.$ext;
                $upload_file->move(storage_path('app/public/uploads/contracts/'), $fileName);
                $data = array();
                $data['file'] = '/storage/uploads/contracts/'.$fileName;
                $data['file_name'] = $upload_file->getClientOriginalName();//原文件名
                $data['file_path'] = asset('/storage/uploads/contracts/'.$fileName);
                return response()->json(['code' => 1, 'message' => '上传成功', 'data' => $data]);
            }else{
                return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
            }
        }
    	//保存数据
    	$rules = [
    		'customer_name' => 'required|max:50',
    		'customer_type' => 'required|integer',
    		'contacts' => 'required|max:50',
    		'customer_tel' => 'required|max:30',
    		'customer_email' => 'required|email',
            'customer_qq' => 'required|max:50',
            'bank_accounts' => 'required|max:30',
    		'customer_address' => 'required|max:100',
            'sale_user_id' => 'required|integer',
            'type' => 'required|integer',
            'deadline' => 'required|date_format:Y-m-d',
            'number' => 'required',
            'if_auto' => 'required|integer',
            'file_url' => 'required',
            'file_name' => 'required|max:100'

    	];
        $attributes = [
            'customer_name' => '客户名称',
            'customer_type' => '客户类型',
            'contacts' => '客户联系人',
            'customer_tel' => '联系电话',
            'customer_email' => '邮箱',
            'customer_qq' => 'QQ',
            'bank_accounts' => '银行账户',
            'customer_address' => '联系地址',
            'sale_user_id' => '销售',
            'type' => '合同类型',
            'deadline' => '合同期限',
            'number' => '合同编号',
            'if_auto' => '是否自动生成子合同',
            'file_url' => '电子版',
            'file_name' => '原文件名'
            
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if(isset($inputs['number']) && !empty($inputs['number'])){
            //合同非必填
            $rules = [
                'type' => 'required|integer',
                'deadline' => 'required|date_format:Y-m-d',
                'if_auto' => 'required|integer',
                'file_url' => 'required',
                'file_name' => 'required|max:100'

            ];
            $attributes = [
                'type' => '合同类型',
                'deadline' => '合同期限',
                'if_auto' => '是否自动生成子合同',
                'file_url' => '电子版',
                'file_name' => '原文件名'
                
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
        }
        $customer = new \App\Models\BusinessOrderCustomer;
        $result = $customer->storeData($inputs);
        if($result){
            $number = $inputs['number'] ?? '';
            systemLog('客户管理', '添加了客户['.$inputs['customer_name'].']-合同['.$number.']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 客户列表
     * @Author: molin
     * @Date:   2019-01-07
     */
    public function index(){
        $inputs = request()->all();
        $customer = new \App\Models\BusinessOrderCustomer;
        $data = array();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //查看详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $customer_info = $customer->getQueryInfo($inputs);
            $customer_info->sale_user = $customer_info->user->realname;
            $customer_info->customer_type = $customer_info->customer_type == 1 ? '直客' : '渠道';
            $data['customer_info'] = $customer_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'contracts'){
            //合同详情
            if(!isset($inputs['contract_id']) || !is_numeric($inputs['contract_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数contract_id']);
            }
            $type_list = [1=>'EDM技术服务合同', 2=>'系统开发合同', 3=>'服务器托管合同', 4=>'代理合同', 5=>'购销合同', 6=>'API'];
            $contract = new \App\Models\BusinessOrderContract;
            $contract_info = $contract->where('id', $inputs['contract_id'])->first();
            $contract_info->type = $type_list[$contract_info->type];
            $contract_info->file_url = asset($contract_info->file_url);
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $contract_info]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'order'){
            //合同详情
            if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
            }
            $business_order = new \App\Models\BusinessOrder;
            $settlement_types = $business_order->settlement_types;
            $settlement_type = [];
            foreach ($settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name'];
            }
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $order = new \App\Models\BusinessOrder;
            $order_info = $order->getOrderInfo(['id' => $inputs['order_id']]);
            $data = array();
            $data['id'] = $order_info->id;
            $data['swd_id'] = $order_info->swd_id;
            $data['customer_name'] = $order_info->hasCustomer->customer_name;
            $data['customer_type'] = $order_info->hasCustomer->customer_type == 1 ? '直客' : '渠道';
            $data['contacts'] = $order_info->hasCustomer->contacts;
            $data['customer_tel'] = $order_info->hasCustomer->customer_tel;
            $data['customer_email'] = $order_info->hasCustomer->customer_email;
            $data['customer_qq'] = $order_info->hasCustomer->customer_qq;
            $data['customer_address'] = $order_info->hasCustomer->customer_address;
            $data['project_name'] = $order_info->project_name;
            $data['trade_name'] = $order_info->trade->name;
            $data['test_cycle'] = $order_info->test_cycle;
            $order_settlement_type = array();
            foreach (explode(',', $order_info->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $data['settlement_type'] = implode(',', $order_settlement_type);
            
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                $links[$key]['id'] = $value->id;
                $links[$key]['link_type'] = $value->link_type == 1 ? '分链接' : '自适应';
                $links[$key]['link_name'] = $value->link_name;
                $links[$key]['pc_link'] = $value->pc_link;
                $links[$key]['wap_link'] = $value->wap_link;
                $links[$key]['zi_link'] = $value->zi_link;
                $links[$key]['remarks'] = $value->remarks;
                $links[$key]['if_use'] = $value->if_use;
                $links[$key]['project_name'] = $value->project_id > 0 ? $value->hasProject->project_name : '--';
                $links[$key]['pricing_manner'] = $value->pricing_manner;
                $links[$key]['market_price'] = unserialize($value->market_price);
                $links[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
                $links[$key]['updated_at'] = $value->updated_at->format('Y-m-d H:i:s');
            }
            $data['links'] = $links;
            $project = array();
            foreach ($order_info->hasProject as $key => $value) {
                $project[$key]['id'] = $value->id;
                $project[$key]['project_name'] = $order_info->project_name;
                $project[$key]['charge'] = $user_data['id_realname'][$value->charge_id];//负责人
                $project[$key]['execute'] = $user_data['id_realname'][$value->execute_id];//执行
                $project[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            }
            $data['project'] = $project;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        $data = $customer->getQueryList($inputs);
        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items[$key]['id'] = $value->id;
            $items[$key]['customer_name'] = $value->customer_name;
            $items[$key]['customer_type'] = $value->customer_type == 1 ? '直客' : '渠道';
            $items[$key]['sale_user'] = $value->user->realname;
            $items[$key]['number'] = $value->contract[0]['number'] ?? '--';
        }
        $data['datalist'] = $items;
        $data['customer_type'] = [['id'=>1, 'name' => '直客'],['id'=>2, 'name' => '渠道']];

        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 客户列表-编辑
     * @Author: molin
     * @Date:   2019-01-09
     */
    public function update(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $customer = new \App\Models\BusinessOrderCustomer;
        $info = $customer->where('id', $inputs['id'])->first();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            $data['customer_info'] = $info;
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get(); 
            $data['user_list'] = $user_list;
            $data['customer_type'] = [['id'=> 1,'name' => '直客'], ['id'=> 2, 'name' => '渠道']];
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        //保存数据
        $rules = [
            'id' => 'required|integer',
            'customer_name' => 'required|max:50',
            'customer_type' => 'required|integer',
            'contacts' => 'required|max:50',
            'customer_tel' => 'required|max:30',
            'sale_user_id' => 'required|integer'
        ];
        $attributes = [
            'id' => 'id',
            'customer_name' => '客户名称',
            'customer_type' => '客户类型',
            'contacts' => '客户联系人',
            'customer_tel' => '联系电话',
            'sale_user_id' => '销售'
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $result = $customer->updateData($inputs);
        if($result){
            systemLog('客户管理', '编辑了客户信息['.$inputs['customer_name'].']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 上传合同
     * @Author: molin
     * @Date:   2019-01-09
     */
    public function contracts(){
        $inputs = request()->all();
        $data = array();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $contract = new \App\Models\BusinessOrderContract;
            $contract_info = $contract->where('id', $inputs['id'])->first();
            $type_list = [1=>'EDM技术服务合同', 2=>'系统开发合同', 3=>'服务器托管合同', 4=>'代理合同', 5=>'购销合同', 6=>'API'];
            $contract_info->type = $type_list[$contract_info->type];
            $contract_info->file_path = asset($contract_info->file_url);
            $data['contract_info'] = $contract_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $customer = new \App\Models\BusinessOrderCustomer;
            $customer_info = $customer->getQueryInfo($inputs);
            $customer_detail = $contract = array(); 
            $customer_detail['id'] = $customer_info->id; 
            $customer_detail['customer_name'] = $customer_info->customer_name; 
            $type_list = [1=>'EDM技术服务合同', 2=>'系统开发合同', 3=>'服务器托管合同', 4=>'代理合同', 5=>'购销合同', 6=>'API'];
            $items = array();
            foreach ($customer_info->contract as $key => $value) {
                $items[$key]['id'] = $value->id;
                $items[$key]['customer_id'] = $value->customer_id;
                $items[$key]['number'] = $value->number;
                $items[$key]['deadline'] = $value->deadline;
                $items[$key]['type'] = $type_list[$value->type];
            }
            $contract = $items;
            $data['contract'] = $contract;
            $data['customer_info'] = $customer_detail;
            //合同类型 1.EDM技术服务合同 2.系统开发合同 3.服务器托管合同 4.代理合同 5.购销合同 6.API
            $data['type_list'] = [['id' => 1, 'name' => 'EDM技术服务合同'], ['id' => 2, 'name' => '系统开发合同'], ['id' => 3, 'name' => '服务器托管合同'], ['id' => 4, 'name' => '代理合同'], ['id' => 5, 'name' => '购销合同'], ['id' => 6, 'name' => 'API']];
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'upload'){
            $upload_file = \request()->file('file');
            if(!empty($upload_file)){
                $ext = $upload_file->getClientOriginalExtension();//扩展名
                $type_arr = ['jpg','png','gif','doc','docx','pdf'];
                if(!in_array($ext, $type_arr)){
                    return response()->json(['code' => -1, 'message' => '只能上传图片、word文档、pdf文件！！', 'data' => ['type' => $ext]]);
                }
                $size = $upload_file->getSize();//文件大小  限制大小10M
                if($size > 10485760){
                    return response()->json(['code' => -1, 'message' => '只能上传10M以内的图片！！', 'data' => null]);
                }
                if(!\File::isDirectory(storage_path('app/public/uploads/contracts/'))){
                    \File::makeDirectory(storage_path('app/public/uploads/contracts/'),  $mode = 0777, $recursive = false);
                }
                //重命名
                $fileName = date('YmdHis').uniqid().'.'.$ext;
                $upload_file->move(storage_path('app/public/uploads/contracts/'), $fileName);
                $data = array();
                $data['file'] = '/storage/uploads/contracts/'.$fileName;
                $data['file_name'] = $upload_file->getClientOriginalName();//原文件名
                $data['file_path'] = asset('/storage/uploads/contracts/'.$fileName);
                return response()->json(['code' => 1, 'message' => '上传成功', 'data' => $data]);
            }else{
                return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
            }
        }
        //保存数据
        $rules = [
            'customer_id' => 'required|integer',
            'customer_name' => 'required|max:50',
            'type' => 'required|integer',
            'deadline' => 'required|date_format:Y-m-d',
            'number' => 'required',
            'if_auto' => 'required|integer',
            'file_url' => 'required',
            'file_name' => 'required|max:100'

        ];
        //合同类型 1.EDM技术服务合同 2.系统开发合同 3.服务器托管合同 4.代理合同 5.购销合同 6.API
        $attributes = [
            'customer_id' => '客户id',
            'customer_name' => '客户名称',
            'type' => '合同类型',
            'deadline' => '合同期限',
            'number' => '合同编号',
            'if_auto' => '是否自动生成子合同',
            'file_url' => '电子版',
            'file_name' => '文件名'
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $contract = new \App\Models\BusinessOrderContract;
        $result = $contract->storeData($inputs);
        if($result){
            systemLog('客户管理', '上传了合同信息['.$inputs['number'].']');
            return response()->json(['code' => 1, 'message' => '上传成功']);
        }
        return response()->json(['code' => 0, 'message' => '上传失败']);
    }

    /**
     * 开票公司列表
     * @Author: molin
     * @Date:   2019-01-17
     */
    public  function receipt(){
        $inputs = request()->all();
        $customer = new \App\Models\BusinessOrderCustomer;
        $data = array();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //查看详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $customer_info = $customer->getQueryInfo($inputs);
            $tmp = array();
            $tmp['id'] = $customer_info->id;
            $tmp['customer_name'] = $customer_info->customer_name;
            $tmp['customer_type'] = $customer_info->customer_type == 1 ? '直客' : '渠道';
            $tmp['contacts'] = $customer_info->contacts;
            $tmp['customer_tel'] = $customer_info->customer_tel;
            $tmp['customer_email'] = $customer_info->customer_email;
            $tmp['customer_qq'] = $customer_info->customer_qq;
            $tmp['sale_user'] = $customer_info->user->realname;
            $receipt_info = [];
            $income = new \App\Models\ProjectIncome;
            $invoice_types = $invoice_contents = [];
            foreach ($income->invoice_type as $key => $value) {
                $invoice_types[$value['id']] = $value['name'];
            }
            foreach ($income->invoice_content as $key => $value) {
                $invoice_contents[$value['id']] = $value['name'];
            }
            
            foreach ($customer_info->receipt as $key => $value) {
                $tp = [];
                $tp['id'] = $value->id;
                $tp['name'] = $value->name ? $value->name : '--';
                $tp['invoice_type'] = $value->invoice_type ? $invoice_types[$value->invoice_type] : '--';
                $tp['invoice_content'] = $value->invoice_content ? $invoice_contents[$value->invoice_content] : '--';
                $tp['taxpayer'] = $value->taxpayer ? $value->taxpayer : '--';
                $tp['address'] = $value->address ? $value->address : '--';
                $tp['tel'] = $value->tel ? $value->tel : '--';
                $tp['bank'] = $value->bank ? $value->bank : '--';
                $tp['bank_account'] = $value->bank_account ? $value->bank_account : '--';
                $tp['remarks'] = $value->remarks ? $value->remarks : '无';
                $receipt_info[] = $tp;
            }
            
            $tmp['receipt'] = $receipt_info;
            $data['customer_info'] = $tmp;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        
        $data = $customer->getQueryList($inputs);
        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items[$key]['id'] = $value->id;
            $items[$key]['customer_name'] = $value->customer_name;
            $items[$key]['customer_type'] = $value->customer_type == 1 ? '直客' : '渠道';
            $items[$key]['sale_user'] = $value->user->realname;
            $name_str = array();
            if(!empty($value->receipt)){
                foreach ($value->receipt as $val) {
                    $name_str[] =$val->name;
                }
            }
            $items[$key]['receipt'] = implode(',', $name_str);//开票公司
        }
        $data['datalist'] = $items;
        $data['customer_type'] = [['id'=>1, 'name' => '直客'],['id'=>2, 'name' => '渠道']];

        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 开票公司列表-添加开票公司
     * @Author: molin
     * @Date:   2019-06-25
     */
    public function receipt_store(){
        $inputs = request()->all();
        $customer = new \App\Models\BusinessOrderCustomer;
        $income = new \App\Models\ProjectIncome;
        $receipt = new \App\Models\BusinessOrderReceipt;
        $data = array();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            //加载添加、编辑
            if(isset($inputs['receipt_id']) && is_numeric($inputs['receipt_id'])){
                $receipt_info = $receipt->where('id', $inputs['receipt_id'])->first();
                if(empty($receipt_info)){
                    return response()->json(['code' => 0, 'message' => '没有符合的数据']);
                }
                $receipt_info->receipt_id = $receipt_info->id;
                unset($receipt_info->id);
                $data['receipt_info'] = $receipt_info;
            }
            
            $data['invoice_type'] = $income->invoice_type;
            $data['invoice_content'] = $income->invoice_content;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
            //提交保存
            $rules = [
                'customer_id' => 'required|integer',
                'name' => 'required|max:100',
                'invoice_type' => 'required|integer',
                'invoice_content' => 'required|integer',
                'taxpayer' => 'required|max:100',
                'address' => 'required|max:100',
                'tel' => 'required|max:30',
                'bank' => 'required|max:10',
                'bank_account' => 'required|max:30',
                'remarks' => 'required'
            ];
            
            $attributes = [
                'customer_id' => '客户id',
                'name' => '发票抬头',
                'invoice_type' => '发票类型',
                'invoice_content' => '发票内容',
                'taxpayer' => '纳税人识别号',
                'address' => '地址',
                'tel' => '电话',
                'bank' => '银行',
                'bank_account' => '银行账户',
                'remarks' => '备注'
            ];

            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            
            $result = $receipt->updateData($inputs);
            if($result){
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $customer_info = $customer->getQueryInfo($inputs);
        $tmp = array();
        $tmp['id'] = $customer_info->id;
        $tmp['customer_name'] = $customer_info->customer_name;
        $tmp['customer_type'] = $customer_info->customer_type == 1 ? '直客' : '渠道';
        $tmp['contacts'] = $customer_info->contacts;
        $tmp['customer_tel'] = $customer_info->customer_tel;
        $tmp['customer_email'] = $customer_info->customer_email;
        $tmp['customer_qq'] = $customer_info->customer_qq;
        $tmp['sale_user'] = $customer_info->user->realname;
        $receipt_info = [];
        $invoice_types = $invoice_contents = [];
        foreach ($income->invoice_type as $key => $value) {
            $invoice_types[$value['id']] = $value['name'];
        }
        foreach ($income->invoice_content as $key => $value) {
            $invoice_contents[$value['id']] = $value['name'];
        }
        foreach ($customer_info->receipt as $key => $value) {
            $tp = [];
            $tp['receipt_id'] = $value->id;
            $tp['name'] = $value->name ? $value->name : '--';
            $tp['invoice_type'] = $value->invoice_type ? $invoice_types[$value->invoice_type] : '--';
            $tp['invoice_content'] = $value->invoice_content ? $invoice_contents[$value->invoice_content] : '--';
            $tp['taxpayer'] = $value->taxpayer ? $value->taxpayer : '--';
            $tp['address'] = $value->address ? $value->address : '--';
            $tp['tel'] = $value->tel ? $value->tel : '--';
            $tp['bank'] = $value->bank ? $value->bank : '--';
            $tp['bank_account'] = $value->bank_account ? $value->bank_account : '--';
            $tp['remarks'] = $value->remarks ? $value->remarks : '无';
            $receipt_info[] = $tp;
        }
        
        $tmp['receipt'] = $receipt_info;
        $data['customer_info'] = $tmp;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

}

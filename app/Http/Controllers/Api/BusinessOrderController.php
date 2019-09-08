<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BusinessOrderController extends Controller
{

    //商务单列表
    public function index(){
        $inputs = request()->all();
        $business_order = new \App\Models\BusinessOrder;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $settlement_type = array();
            foreach ($business_order->settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name'];
            }
            $id = $inputs['id'];
            $order_info =  $business_order->getOrderInfo($inputs);
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
            $order_info->trade_name =$order_info->trade->name;
            $order_info->test_cycle = $order_info->test_cycle;
            $order_settlement_type = array();
            foreach (explode(',', $order_info->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $order_info->settlement_type = implode(',', $order_settlement_type);
            $order_info->verify_user_id = $user_data['id_realname'][$order_info->verify_user_id];
            if($order_info->status == 0){
                $order_info->status_txt = '待审核';
            }else if($order_info->status == 1){
                $order_info->status_txt = '待上传合同';
            }else if($order_info->status == 2){
                $order_info->status_txt = '不通过';
            }else if($order_info->status == 3){
                $order_info->status_txt = '待创建项目';
            }else if($order_info->status == 4){
                $order_info->status_txt = '已创建项目';
            }
            if(!empty($order_info->other)){
                $other = unserialize($order_info->other);
                $other_arr = array();
                foreach ($other as $key => $value) {
                    $other_arr[$key]['file'] = $value['file'];
                    $other_arr[$key]['file_name'] = $value['file_name'];
                    $other_arr[$key]['file_path'] = asset($value['file']);
                }
                $order_info->other = $other_arr;
            }
            $notice_users = array();
            foreach ($order_info->hasNotices as $v) {
                $notice_users[] = $user_data['id_realname'][$v['user_id']];
            }
            $order_info->notice_users = implode(',', $notice_users);
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                $links[$key]['id'] = $value['id'];
                $links[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $links[$key]['link_name'] = $value['link_name'];
                $links[$key]['pc_link'] = $value['pc_link'];
                $links[$key]['wap_link'] = $value['wap_link'];
                $links[$key]['zi_link'] = $value['zi_link'];
                $links[$key]['remarks'] = $value['remarks'];
                $links[$key]['if_use'] = $value['if_use'];
                $links[$key]['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $links[$key]['pricing_manner'] = $value['pricing_manner'];
                $links[$key]['market_price'] = unserialize($value['market_price']);
                $links[$key]['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $links[$key]['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
            }
            $order_info->links = $links;
            unset($order_info->hasLinks);
            $verifys = [];
            if(isset($order_info->hasVerify) && !empty($order_info->hasVerify->toArray())){
                foreach ($order_info->hasVerify as $v) {
                    $tmp = array();
                    $tmp['realname'] = $user_data['id_realname'][$v->user_id];
                    $tmp['comment'] = $v->comment;
                    $verifys[] = $tmp;
                }
            }
            $order_info->verifys = $verifys;
            $data['order_info'] = $order_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'export_links'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $links_list = (new \App\Models\BusinessOrderLink)->getLinkList(['order_id'=>$inputs['id'],'export'=>1]);
            if(count($links_list['datalist']) == 0){
                return response()->json(['code' => -1, 'message' => '没有链接可以导出']);
            }
            $export_links = array();
            foreach ($links_list['datalist'] as $key => $value) {
                $tmp = array();
                $tmp['id'] = $value['id'];
                $tmp['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $tmp['link_name'] = $value['link_name'];
                $tmp['pc_link'] = $value['pc_link'];
                $tmp['wap_link'] = $value['wap_link'];
                $tmp['zi_link'] = $value['zi_link'];
                $tmp['if_use'] = $value['if_use'] ? '启用' : '未启用';
                $tmp['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $tmp['pricing_manner'] = $value['pricing_manner'];
                $market_price = [];
                foreach (unserialize($value['market_price']) as $kk => $vv) {
                    $str = '';
                    $str .= $kk.':';
                    $str .= $vv;
                    $market_price[] = $str.'元';
                }

                $tmp['market_price'] = implode(';', $market_price);
                $tmp['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $tmp['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
                $tmp['remarks'] = $value['remarks'];
                $export_links[] = $tmp;
            }
            $export_head = ['链接ID','链接类型','链接名称','cp链接','wap链接','自适应','状态','项目','计价方式','单价','创建时间','最后更新时间','备注'];
            $filedata = pExprot($export_head, $export_links, 'order_links');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        $inputs['user_id'] = auth()->user()->id;
        $data = $business_order->getDataList($inputs);
        $items = $export_data = array();
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['swd_id'] = $export_data[$key]['swd_id'] = $value->swd_id;
            $tmp['customer_name'] = $export_data[$key]['customer_name'] = $value->hasCustomer->customer_name;
            $tmp['customer_type'] = $export_data[$key]['customer_type'] = $value->hasCustomer->customer_type == 1 ? '直客' : '渠道';
            $tmp['project_name'] = $export_data[$key]['project_name'] = $value->project_name;
            $tmp['created_at'] = $export_data[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $tmp['add_user'] = $export_data[$key]['add_user'] = $user_data['id_realname'][$value->user_id];
            $tmp['project_business'] = $export_data[$key]['project_business'] = $user_data['id_realname'][$value->project_business];
            $tmp['verify_user_id'] = $export_data[$key]['verify_user_id'] = $user_data['id_realname'][$value->verify_user_id];
            $tmp['status'] = $value->status;
            $tmp['if_edit'] = 0;//1能编辑 0不能编辑
            $tmp['if_upload'] = 0;//1能上传合同 0不能
            $tmp['if_change'] = 0;//1能更改商务单 0不能
            $tmp['if_del'] = 0;//1能删除商务单 0不能
            if($value->status == 0){
                $tmp['status_txt'] = '待审核';
                $tmp['if_edit'] = 1;
                $tmp['if_del'] = 1;
            }else if($value->status == 1){
                $tmp['status_txt'] = '待上传合同';
                $tmp['if_upload'] = 1;
            }else if($value->status == 2){
                $tmp['status_txt'] = '不通过';
                $tmp['if_del'] = 1;
            }else if($value->status == 3){
                $tmp['status_txt'] = '待创建项目';
            }else if($value->status == 4){
                $tmp['status_txt'] = '已创建项目';
                $tmp['if_change'] = 1;
            }
            if($value->if_lock == 1){
                //锁定状态下不能编辑和删除
                $tmp['if_edit'] = 0;
                $tmp['if_del'] = 0;
            }
            $export_data[$key]['status_txt'] = $tmp['status_txt'];
            $items[] = $tmp;
            
        }
        if(isset($inputs['export']) && $inputs['export'] == 1){
            $export_head = ['商务单ID','客户名称','客户类型','项目名称','提交时间','提交人','商务','被指派人','状态'];
            $filedata = pExprot($export_head, $export_data, 'business_order');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        $data['customer_type'] = $business_order->customer_types;
        $data['status'] = [['id'=> 0,'name' => '待审核'], ['id'=> 1, 'name' => '待上传合同'], ['id'=> 2, 'name' => '未通过'], ['id'=> 3, 'name' => '待创建项目'], ['id'=> 4, 'name' => '已创建项目']];
        $data['datalist'] = $items;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }


    /**
     * 商务单添加
     * @Author: molin
     * @Date:   2018-12-27
     */
    public function store(){
    	$inputs = request()->all();
    	$data = array();
        $business_order = new \App\Models\BusinessOrder;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
    		$trade = new \App\Models\Trade;
    		$trade_list = $trade->select(['id','name'])->where('if_use', 1)->orderBy('sort', 'asc')->get();
            $data['trade_list'] = $trade_list;
            $data['customer_type'] = $business_order->customer_types;
            $data['project_type'] = $business_order->project_types;
            $data['settlement_list'] = $business_order->settlement_lists;
    		$data['settlement_type'] = $business_order->settlement_types;
            $data['link_type'] = $business_order->link_types;
            $user = new \App\Models\User;
            $user_list = $user->select(['id','realname'])->where('status', 1)->get();
            $data['user_list'] = $user_list;
            $customer = new \App\Models\BusinessOrderCustomer;
            $customer_list = $customer->select(['id', 'customer_name'])->get();
            $data['customer_list'] = $customer_list;//客户列表
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'customer'){
            if(!isset($inputs['customer_id']) || !is_numeric($inputs['customer_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数customer_id']);
            }
            $customer = new \App\Models\BusinessOrderCustomer;
            $customer_info = $customer->where('id', $inputs['customer_id'])->first();
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $customer_info]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'copy'){
            //复制
            if(!isset($inputs['id']) && $inputs['id']){
                return response()-json(['code' => -1, 'message' => '缺少参数id']);
            }
            $trade = new \App\Models\Trade;
            $trade_list = $trade->select(['id','name'])->where('if_use', 1)->orderBy('sort', 'asc')->get();
            $data['trade_list'] = $trade_list;
            $data['customer_type'] = $business_order->customer_types;
            $data['project_type'] = $business_order->project_types;
            $data['settlement_list'] = $business_order->settlement_lists;
            $data['settlement_type'] = $business_order->settlement_types;
            $data['link_type'] = $business_order->link_types;
            $user = new \App\Models\User;
            $user_list = $user->select(['id','realname'])->where('status', 1)->get();
            $data['user_list'] = $user_list;
            $order_info =  $business_order->getOrderInfo($inputs);
            $order_info->customer_name = $order_info->hasCustomer->customer_name;
            $order_info->customer_type = $order_info->hasCustomer->customer_type;
            $order_info->customer_qq = $order_info->hasCustomer->customer_qq;
            $order_info->customer_email = $order_info->hasCustomer->customer_email;
            $order_info->customer_address = $order_info->hasCustomer->customer_address;
            $order_info->customer_tel = $order_info->hasCustomer->customer_tel;
            $order_info->contacts = $order_info->hasCustomer->contacts;
            $order_info->bank_accounts = $order_info->hasCustomer->bank_accounts;
            if(!empty($order_info->other)){
                $order_info->other = unserialize($order_info->other);

            }
            unset($order_info['id']);
            unset($order_info['user_id']);
            unset($order_info['swd_id']);
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                $links[$key]['link_type'] = $value->link_type;
                $links[$key]['link_name'] = $value->link_name;
                $links[$key]['pc_link'] = $value->pc_link;
                $links[$key]['wap_link'] = $value->wap_link;
                $links[$key]['zi_link'] = $value->zi_link;
                $links[$key]['remarks'] = $value->remarks;
                $links[$key]['if_use'] = $value->if_use;
                $links[$key]['pricing_manner'] = $value->pricing_manner;
                $links[$key]['market_price'] = unserialize($value->market_price);
            }
            $order_info->links = $links;
            $order_info->settlement_type = explode(',', $order_info->settlement_type);
            unset($order_info->hasLinks);
            $notice_users = array();
            foreach ($order_info->hasNotices as $v) {
                $notice_users[] = $v->user_id;
            }
            $order_info->notice_users = $notice_users;
            unset($order_info->hasNotices);
            $data['order_info'] = $order_info;
            $customer = new \App\Models\BusinessOrderCustomer;
            $customer_list = $customer->select(['id', 'customer_name'])->get();
            $data['customer_list'] = $customer_list;//客户列表
            // dd($order_info->hasPrices);
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'customer_add'){
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['user_list'] = $user_list;
            $data['customer_type'] = $business_order->customer_types;
            //合同类型 1.EDM技术服务合同 2.系统开发合同 3.服务器托管合同 4.代理合同 5.购销合同 6.API
            $data['type_list'] = $business_order->type_lists;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'customer_save'){
            //保存数据
            $rules = [
                'customer_name' => 'required|max:50',
                'customer_type' => 'required|integer',
                'contacts' => 'required|max:50',
                'customer_tel' => 'required|max:30',
                'sale_user_id' => 'required|integer'
            ];
            $attributes = [
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
            if(isset($inputs['number']) && !empty($inputs['number'])){
                //合同非必填
                $rules = [
                    'type' => 'required|integer',
                    'deadline' => 'required|date_format:Y-m-d',
                    'number' => 'required',
                    'if_auto' => 'required|integer',
                    'file_url' => 'required',
                    'file_name' => 'required'
                ];
                $attributes = [
                    'type' => '合同类型',
                    'deadline' => '合同期限',
                    'number' => '合同编号',
                    'if_auto' => '是否自动生成子合同',
                    'file_url' => 'file_url',
                    'file_name' => 'file_name'
                ];
                
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
                }
            }
            $customer = new \App\Models\BusinessOrderCustomer;
            $result = $customer->storeData($inputs);
            if($result){
                $data['customer_list'] = $customer->select(['id', 'customer_name'])->get();
                systemLog('商务单', '添加了客户');
                return response()->json(['code' => 1, 'message' => '操作成功', 'data' => $data]);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
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
                    return response()->json(['code' => -1, 'message' => '只能上传10M以内的文件！！', 'data' => null]);
                }
                //重命名
                $fileName = date('YmdHis').uniqid().'.'.$ext;
                $data = array();
                $data['file_name'] = $upload_file->getClientOriginalName();//原文件名
                if(isset($inputs['type']) && $inputs['type'] == 'other'){
                    //上传素材
                    if(!\File::isDirectory(storage_path('app/public/uploads/other/'))){
                        \File::makeDirectory(storage_path('app/public/uploads/other/'),  $mode = 0777, $recursive = false);
                    }
                    $upload_file->move(storage_path('app/public/uploads/other/'), $fileName);
                    $data['file'] = '/storage/uploads/other/'.$fileName;
                    $data['file_path'] = asset('/storage/uploads/other/'.$fileName);
                }else{
                    //合同
                    if(!\File::isDirectory(storage_path('app/public/uploads/contracts/'))){
                        \File::makeDirectory(storage_path('app/public/uploads/contracts/'),  $mode = 0777, $recursive = false);
                    }
                    $upload_file->move(storage_path('app/public/uploads/contracts/'), $fileName);
                    $data['file'] = '/storage/uploads/contracts/'.$fileName;
                    $data['file_path'] = asset('/storage/uploads/contracts/'.$fileName);
                }
                return response()->json(['code' => 1, 'message' => '上传成功', 'data' => $data]);
            }else{
                return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
            }
        }
    	//保存数据
    	$rules = [
            'customer_id' => 'required|integer',
    		'project_name' => 'required|max:50',
            'project_type' => 'required|integer',
            'project_sale' => 'required|integer',
    		'project_business' => 'required|integer',
    		'trade_id' => 'required|integer',
    		'settlement_type' => 'required|array',
            'links' => 'required|array',
            'verify_user_id' => 'required|integer',
            'notice_users' => 'required|array',
    		'comment' => 'required|max:100'

    	];
        $attributes = [
            'customer_id' => '客户id',
            'project_name' => '项目名称',
            'project_type' => '项目类型',
            'project_sale' => '销售',
            'project_business' => '商务',
            'trade_id' => '行业',
            'settlement_type' => '结算周期',
            'links' => '投放链接',
            'verify_user_id' => '审核人员',
            'notice_users' => '通知人员',
            'comment' => '提交备注'
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }

        //投放链接
        foreach ($inputs['links'] as $key => $value) {
            //链接
            if($value['link_type'] == 1){
                //分链接
                if(empty($value['pc_link']) && empty($value['wap_link'])){
                    return response()->json(['code' => -1, 'message' => '请填写pc、wap链接']);
                }
            }else if($value['link_type'] == 2){
                //自适应
                if(empty($value['zi_link'])){
                    return response()->json(['code' => -1, 'message' => '请填写自适应内容']);
                }
            }
            if(empty($value['link_name'])){
                return response()->json(['code' => -1, 'message' => '请填写链接名称']);
            }
            if(empty($value['remarks'])){
                return response()->json(['code' => -1, 'message' => '请填写链接备注']);
            }
            if(!isset($value['if_use']) || !in_array($value['if_use'], [0,1])){
                return response()->json(['code' => -1, 'message' => '是否启用字段必填']);
            }
            if(!isset($value['pricing_manner']) || !in_array($value['pricing_manner'], ['CPD','CPC','CPA','CPS','CPA+CPS'])){
                return response()->json(['code' => -1, 'message' => '请填结算类型']);
            }
            //链接单价
            foreach ($value['market_price'] as $key => $val) {
                if(!in_array($key, ['CPA','CPC','CPD','CPS'])){
                    return response()->json(['code' => -1, 'message' => '项目单价类型错误']);
                }
                if(!is_numeric($val) || $val == 0){
                    return response()->json(['code' => -1, 'message' => '价格必须为大于0的数字']);
                }
            }
            
        }
        if(isset($inputs['other']) && !empty($inputs['other'])){
            if(!is_array($inputs['other'])){
                return response()->json(['code' => -1, 'message' => '上传素材格式错误']);
            }
            foreach ($inputs['other'] as $key => $value) {
                if(!isset($value['file']) || empty($value['file'])){
                    return response()->json(['code' => -1, 'message' => '素材中file不能为空']);
                }
                if(!isset($value['file_name']) || empty($value['file_name'])){
                    return response()->json(['code' => -1, 'message' => '素材中file_name不能为空']);
                }
            }
        }

        if(isset($inputs['id'])){
            unset($inputs['id']);
        }
        $result = $business_order->storeData($inputs);
        if($result){
            systemLog('商务单', '添加了一条商务单');
            addNotice($inputs['verify_user_id'], '商务单', '[添加]您有一条商务单待审核', '', 0, 'bill-examine','business_order/verify');//提醒审核人
            addNotice($inputs['notice_users'], '商务单', '[添加]'.auth()->user()->realname.'添加了一条商务单', '', 0, 'bill-index','business_order/index');//提醒通知人
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 商务单编辑
     * @Author: molin
     * @Date:   2018-12-27
     */
    public function edit(){
        $inputs = request()->all();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'upload'){
            //上传
            $upload_file = \request()->file('file');
            if(!empty($upload_file)){
                $ext = $upload_file->getClientOriginalExtension();//扩展名
                $type_arr = ['jpg','png','gif','doc','docx','pdf'];
                if(!in_array($ext, $type_arr)){
                    return response()->json(['code' => -1, 'message' => '只能上传图片、word文档、pdf文件！！', 'data' => ['type' => $ext]]);
                }
                $size = $upload_file->getSize();//文件大小  限制大小10M
                if($size > 10485760){
                    return response()->json(['code' => -1, 'message' => '只能上传10M以内的文件！！', 'data' => null]);
                }
                //重命名
                $fileName = date('YmdHis').uniqid().'.'.$ext;
                $data = array();
                $data['file_name'] = $upload_file->getClientOriginalName();//原文件名
                if(isset($inputs['type']) && $inputs['type'] == 'other'){
                    //上传素材
                    if(!\File::isDirectory(storage_path('app/public/uploads/other/'))){
                        \File::makeDirectory(storage_path('app/public/uploads/other/'),  $mode = 0777, $recursive = false);
                    }
                    $upload_file->move(storage_path('app/public/uploads/other/'), $fileName);
                    $data['file'] = '/storage/uploads/other/'.$fileName;
                    $data['file_path'] = asset('/storage/uploads/other/'.$fileName);
                }else{
                    //合同
                    if(!\File::isDirectory(storage_path('app/public/uploads/contracts/'))){
                        \File::makeDirectory(storage_path('app/public/uploads/contracts/'),  $mode = 0777, $recursive = false);
                    }
                    $upload_file->move(storage_path('app/public/uploads/contracts/'), $fileName);
                    $data['file'] = '/storage/uploads/contracts/'.$fileName;
                    $data['file_path'] = asset('/storage/uploads/contracts/'.$fileName);
                }
                return response()->json(['code' => 1, 'message' => '上传成功', 'data' => $data]);
            }else{
                return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
            }
        }
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $business_order = new \App\Models\BusinessOrder;
        $order_info =  $business_order->getOrderInfo($inputs);
        if(empty($order_info) || $order_info->if_lock == 1){
            return response()->json(['code' => 0, 'message' => '该商务单无法编辑']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            $trade = new \App\Models\Trade;
            $trade_list = $trade->select(['id','name'])->where('if_use', 1)->orderBy('sort', 'asc')->get();
            $data['trade_list'] = $trade_list;
            $data['customer_type'] = $business_order->customer_types;
            $data['project_type'] = $business_order->project_types;
            $data['settlement_list'] = $business_order->settlement_lists;
            $data['settlement_type'] = $business_order->settlement_types;
            $data['link_type'] = $business_order->link_types;
            $user = new \App\Models\User;
            $user_list = $user->select(['id','realname'])->where('status', 1)->get();
            $data['user_list'] = $user_list;
            $order_info->customer_type = $order_info->hasCustomer->customer_type == 1 ? '直客' : '渠道';
            $order_info->customer_name = $order_info->hasCustomer->customer_name;
            $order_info->customer_tel = $order_info->hasCustomer->customer_tel;
            $order_info->customer_qq = $order_info->hasCustomer->customer_qq;
            $order_info->customer_email = $order_info->hasCustomer->customer_email;
            $order_info->customer_address = $order_info->hasCustomer->customer_address;
            $order_info->contacts = $order_info->hasCustomer->contacts;
            $order_info->bank_accounts = $order_info->hasCustomer->bank_accounts;
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                $links[$key]['id'] = $value->id;
                $links[$key]['link_type'] = $value->link_type;
                $links[$key]['link_name'] = $value->link_name;
                $links[$key]['pc_link'] = $value->pc_link;
                $links[$key]['wap_link'] = $value->wap_link;
                $links[$key]['zi_link'] = $value->zi_link;
                $links[$key]['remarks'] = $value->remarks;
                $links[$key]['if_use'] = $value->if_use;
                $links[$key]['pricing_manner'] = $value->pricing_manner;
                $links[$key]['market_price'] = unserialize($value->market_price);
            }
            $order_info->links = $links;
            unset($order_info->hasLinks);
            $order_info->settlement_type = explode(',', $order_info->settlement_type);
            if(!empty($order_info->other)){
                $order_info->other = unserialize($order_info->other);

            }
            $notice_users = array();
            foreach ($order_info->hasNotices as $v) {
                $notice_users[] = $v->user_id;
            }
            $order_info->notice_users = $notice_users;
            unset($order_info->hasNotices);
            
            $data['order_info'] = $order_info;
            $customer = new \App\Models\BusinessOrderCustomer;
            $customer_list = $customer->select(['id', 'customer_name'])->get();
            $data['customer_list'] = $customer_list;//客户列表
            // dd($order_info->hasPrices);
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'customer'){
            if(!isset($inputs['customer_id']) || !is_numeric($inputs['customer_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数customer_id']);
            }
            $customer = new \App\Models\BusinessOrderCustomer;
            $customer_info = $customer->where('id', $inputs['customer_id'])->first();
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $customer_info]);
        }


        //保存数据
        $rules = [
            'customer_id' => 'required|integer',
            'project_name' => 'required|max:50',
            'project_type' => 'required|integer',
            'project_sale' => 'required|integer',
            'project_business' => 'required|integer',
            'trade_id' => 'required|integer',
            'settlement_type' => 'required|array',
            'links' => 'required|array',
            'verify_user_id' => 'required|integer',
            'notice_users' => 'required|array',
            'comment' => 'required|max:100',

        ];
        $attributes = [
            'customer_id' => '客户id',
            'project_name' => '项目名称',
            'project_type' => '项目类型',
            'project_sale' => '销售',
            'project_business' => '商务',
            'trade_id' => '行业',
            'settlement_type' => '结算周期',
            'links' => '投放链接',
            'verify_user_id' => '审核人员',
            'notice_users' => '通知人员',
            'comment' => '提交备注'
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }

        //投放链接
        foreach ($inputs['links'] as $key => $value) {
            //链接
            if(!isset($value['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            if($value['link_type'] == 1){
                //分链接
                if(empty($value['pc_link']) && empty($value['wap_link'])){
                    return response()->json(['code' => -1, 'message' => '请填写pc、wap链接']);
                }
            }else if($value['link_type'] == 2){
                //自适应
                if(empty($value['zi_link'])){
                    return response()->json(['code' => -1, 'message' => '请填写自适应内容']);
                }
            }
            if(empty($value['link_name'])){
                return response()->json(['code' => -1, 'message' => '请填写链接名称']);
            }
            if(empty($value['remarks'])){
                return response()->json(['code' => -1, 'message' => '请填写链接备注']);
            }
            if(!isset($value['if_use']) || !in_array($value['if_use'], [0,1])){
                return response()->json(['code' => -1, 'message' => '是否启用字段必填']);
            }
            if(!isset($value['pricing_manner']) || !in_array($value['pricing_manner'], ['CPD','CPC','CPA','CPS','CPA+CPS'])){
                return response()->json(['code' => -1, 'message' => '请填结算类型']);
            }
            //链接单价
            foreach ($value['market_price'] as $key => $val) {
                if(!in_array($key, ['CPA','CPC','CPD','CPS'])){
                    return response()->json(['code' => -1, 'message' => '项目单价类型错误']);
                }
                if(!is_numeric($val) || $val == 0){
                    return response()->json(['code' => -1, 'message' => '价格必须为大于0的数字']);
                }
            }
            
        }
        if(isset($inputs['other']) && !empty($inputs['other'])){
            if(!is_array($inputs['other'])){
                return response()->json(['code' => -1, 'message' => '上传素材格式错误']);
            }
            foreach ($inputs['other'] as $key => $value) {
                if(!isset($value['file']) || empty($value['file'])){
                    return response()->json(['code' => -1, 'message' => '素材中file不能为空']);
                }
                if(!isset($value['file_name']) || empty($value['file_name'])){
                    return response()->json(['code' => -1, 'message' => '素材中file_name不能为空']);
                }
            }
        }

        //投放链接
        $result = $business_order->storeData($inputs);
        if($result){
            systemLog('商务单', '编辑了商务单['.$order_info->swd_id.']');
            addNotice($inputs['verify_user_id'], '商务单', '[编辑]您有一条商务单待审核', '', 0, 'bill-examine','business_order/verify');//提醒审核人
            addNotice($inputs['notice_users'], '商务单', '[编辑]'.auth()->user()->realname.'添加了一条商务单', '', 0, 'bill-index','business_order/index');//提醒通知人
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 商务单删除
     * @Author: molin
     * @Date:   2018-12-27
     */
    public function delete(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $business_order = new \App\Models\BusinessOrder;
        $order_info =  $business_order->getOrderInfo($inputs);
        if(empty($order_info) || !in_array($order_info->status, [0,2]) || $order_info->if_lock == 1){
            return response()->json(['code' => 0, 'message' => '无法删除商务单']);
        }
        $project = new \App\Models\BusinessProject;
        $if_exist = $project->where('order_id', $inputs['id'])->first();
        if(!empty($if_exist)){
            return response()->json(['code' => 0, 'message' => '已关联项目,无法删除']);
        }
        $result = $business_order->where('id', $inputs['id'])->delete();
        if($result){
            (new \App\Models\BusinessOrderVerify)->where('order_id', $inputs['id'])->delete();
            (new \App\Models\BusinessOrderNotice)->where('order_id', $inputs['id'])->delete();
            (new \App\Models\BusinessOrderLink)->where('order_id', $inputs['id'])->delete();
            systemLog('商务单', '删除了商务单['.$order_info->swd_id.']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 我要审核的商务单
     * @Author: molin
     * @Date:   2019-01-02
     */
    public function verify(){
        $inputs = request()->all();
        $user_id = auth()->user()->id;
        $business_order =  new \App\Models\BusinessOrder;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //查看详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $settlement_type = array();
            foreach ($business_order->settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name']; 
            }
            
            $order_info =  $business_order->getOrderInfo($inputs);
            if(empty($order_info)){
                return response()->json(['code' => -1, 'message' => '无此商务单']);
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
            $order_info->trade_name =$order_info->trade->name;
            $order_info->test_cycle = $order_info->test_cycle;
            $order_settlement_type = array();
            foreach (explode(',', $order_info->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $order_info->settlement_type = implode(',', $order_settlement_type);
            $order_info->verify_user_id = $user_data['id_realname'][$order_info->verify_user_id];
            if(!empty($order_info->other)){
                $other = unserialize($order_info->other);
                $other_arr = array();
                foreach ($other as $key => $value) {
                    $other_arr[$key]['file'] = $value['file'];
                    $other_arr[$key]['file_name'] = $value['file_name'];
                    $other_arr[$key]['file_path'] = asset($value['file']);
                }
                $order_info->other = $other_arr;
            }
            if($order_info->status == 0){
                $order_info->status_txt = '待审核';
            }else if($order_info->status == 1){
                $order_info->status_txt = '待上传合同';
            }else if($order_info->status == 2){
                $order_info->status_txt = '不通过';
            }else if($order_info->status == 3){
                $order_info->status_txt = '待创建项目';
            }else if($order_info->status == 4){
                $order_info->status_txt = '已创建项目';
            }
            $notice_users = array();
            // dd($order_info);
            foreach ($order_info->hasNotices as $v) {
                $notice_users[] = $user_data['id_realname'][$v['user_id']];
            }
            $order_info->notice_users = implode(',', $notice_users);
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                $links[$key]['id'] = $value['id'];
                $links[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $links[$key]['link_name'] = $value['link_name'];
                $links[$key]['pc_link'] = $value['pc_link'];
                $links[$key]['wap_link'] = $value['wap_link'];
                $links[$key]['zi_link'] = $value['zi_link'];
                $links[$key]['remarks'] = $value['remarks'];
                $links[$key]['if_use'] = $value['if_use'];
                $links[$key]['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $links[$key]['pricing_manner'] = $value['pricing_manner'];
                $links[$key]['market_price'] = unserialize($value['market_price']);
                $links[$key]['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $links[$key]['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');

            }
            $order_info->links = $links;
            unset($order_info->hasLinks);
            $verifys = [];
            if(!empty($order_info->hasVerify->toArray())){
                foreach ($order_info->hasVerify as $v) {
                    $tmp = array();
                    $tmp['realname'] = $user_data['id_realname'][$v->user_id];
                    $tmp['comment'] = $v->comment;
                    $verifys[] = $tmp;
                }
            }
            $order_info->verifys = $verifys;
            $data['order_info'] = $order_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            //审核加载
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $settlement_type = array();
            foreach ($business_order->settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name']; 
            }
            $inputs['status'] = 0;//待审核
            $inputs['verify_user_id'] = $user_id;
            $order_info =  $business_order->getOrderInfo($inputs);
            if(empty($order_info)){
                return response()->json(['code' => 0, 'message' => '此商务单已审核或不是您审核！请勿重复操作~']);
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
            $order_info->trade_name =$order_info->trade->name;
            $order_info->test_cycle = $order_info->test_cycle;
            $order_settlement_type = array();
            foreach (explode(',', $order_info->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $order_info->settlement_type = implode(',', $order_settlement_type);
            $order_info->verify_user_id = $user_data['id_realname'][$order_info->verify_user_id];
            if(!empty($order_info->other)){
                $other = unserialize($order_info->other);
                $other_arr = array();
                foreach ($other as $key => $value) {
                    $other_arr[$key]['file'] = $value['file'];
                    $other_arr[$key]['file_name'] = $value['file_name'];
                    $other_arr[$key]['file_path'] = asset($value['file']);
                }
                $order_info->other = $other_arr;
            }
            $order_info->status_txt = '待审核';
            $notice_users = array();
            foreach ($order_info->hasNotices as $v) {
                $notice_users[] = $user_data['id_realname'][$v->user_id];
            }
            $order_info->notice_users = implode(',', $notice_users);
            
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                $links[$key]['id'] = $value['id'];
                $links[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $links[$key]['link_name'] = $value['link_name'];
                $links[$key]['pc_link'] = $value['pc_link'];
                $links[$key]['wap_link'] = $value['wap_link'];
                $links[$key]['zi_link'] = $value['zi_link'];
                $links[$key]['remarks'] = $value['remarks'];
                $links[$key]['if_use'] = $value['if_use'];
                $links[$key]['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $links[$key]['pricing_manner'] = $value['pricing_manner'];
                $links[$key]['market_price'] = unserialize($value['market_price']);
                $links[$key]['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $links[$key]['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
            }
            $order_info->links = $links;
            unset($order_info->hasLinks);
            
            $verifys = [];
            if(!empty($order_info->hasVerify->toArray())){
                foreach ($order_info->hasVerify as $v) {
                    if(!empty($v->comment)){
                        $tmp = array();
                        $tmp['realname'] = $user_data['id_realname'][$v->user_id];
                        $tmp['comment'] = $v->comment;
                        $verifys[] = $tmp;
                    }
                    
                }
            }
            $order_info->verifys = $verifys;
            $data['order_info'] = $order_info;
            $data['user_list'] = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['verify_type'] = [['id' => 1, 'name' => '指派审核'],['id' => 2, 'name' => '指派创建项目'], ['id' => 3, 'name' => '本人创建项目']];
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'submit'){
            //审核提交
            $rules = [
                'id' => 'required|integer',
                'pass' => 'required|integer',
                'comment' => 'required|max:100'
            ];
            $attributes = [
                'id' => 'id',
                'pass' => 'pass',
                'comment' => '评论必填'
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            
            $order_info = $business_order->where('id', $inputs['id'])->first();
            if($order_info->status != 0 || $order_info->verify_user_id != $user_id){
                return response()->json(['code' => 0, 'message' => '此商务单已审核或不是您审核！请勿重复操作~']);
            }
            $verify = new \App\Models\BusinessOrderVerify;
            if($inputs['pass'] == 1){
                if(!isset($inputs['verify_type']) || !in_array($inputs['verify_type'], [1,2,3])){
                    return response()->json(['code' => -1, 'message' => '请选择下一步骤']);
                }
                if(in_array($inputs['verify_type'], [1,2]) && !isset($inputs['next_user_id'])){
                    return response()->json(['code' => -1, 'message' => '请选择下一步骤人员 next_user_id']);
                }
                
                if($inputs['verify_type'] == 1){
                    //下一位审核人
                    $order_info->verify_user_id = $inputs['next_user_id'];
                }elseif($inputs['verify_type'] == 2){
                    //创建项目人员
                    $order_info->create_user_id = $inputs['next_user_id'];
                    $order_info->status = 1;//通过
                    $verify_txt = '通过';
                }elseif($inputs['verify_type'] == 3){
                    //创建项目人员为本人
                    $order_info->create_user_id = $user_id;
                    $order_info->status = 1;//通过
                    $verify_txt = '通过';
                }
            }else if($inputs['pass'] == 2){
                $order_info->status = 2;//驳回
                $verify_txt = '驳回';
            }
            $order_info->if_lock = 1;//一旦审核就锁定
            $verify_info = $verify->whereUserIdAndOrderIdAndStatus($user_id, $inputs['id'], 0)->first();
            $verify_info->status = 1;
            $verify_info->comment = $inputs['comment'];
            $result = $verify->setNextVerify($verify_info,$order_info,$inputs);
            if($result){
                systemLog('商务单', '审核了商务单['.$order_info->swd_id.']');
                if($order_info->status == 1 || $order_info->status == 2){
                    addNotice($order_info->user_id, '商务单', '您的商务单['.$order_info->swd_id.']审核已'.$verify_txt, '', 0, 'bill-index','business_order/index');//提醒申请人
                }else{
                    addNotice($inputs['next_user_id'], '商务单', '您有一条商务单待审核['.$order_info->swd_id.']', '', 0, 'bill-examine','business_order/verify');//提醒下一位审核人
                }
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'export_links'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $links_list = (new \App\Models\BusinessOrderLink)->getLinkList(['order_id'=>$inputs['id'],'export'=>1]);
            if(count($links_list['datalist']) == 0){
                return response()->json(['code' => -1, 'message' => '没有链接可以导出']);
            }
            $export_links = array();
            foreach ($links_list['datalist'] as $key => $value) {
                $tmp = array();
                $tmp['id'] = $value['id'];
                $tmp['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $tmp['link_name'] = $value['link_name'];
                $tmp['pc_link'] = $value['pc_link'];
                $tmp['wap_link'] = $value['wap_link'];
                $tmp['zi_link'] = $value['zi_link'];
                $tmp['if_use'] = $value['if_use'] ? '启用' : '未启用';
                $tmp['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $tmp['pricing_manner'] = $value['pricing_manner'];
                $market_price = [];
                foreach (unserialize($value['market_price']) as $kk => $vv) {
                    $str = '';
                    $str .= $kk.':';
                    $str .= $vv;
                    $market_price[] = $str.'元';
                }

                $tmp['market_price'] = implode(';', $market_price);
                $tmp['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $tmp['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
                $tmp['remarks'] = $value['remarks'];
                $export_links[] = $tmp;
            }
            $export_head = ['链接ID','链接类型','链接名称','cp链接','wap链接','自适应','状态','项目','计价方式','单价','创建时间','最后更新时间','备注'];
            $filedata = pExprot($export_head, $export_links, 'order_links');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        $inputs['my_verify_id'] = $user_id;
        $data = $business_order->getDataList($inputs);
        $items = $export_data = array();
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['swd_id'] = $export_data[$key]['swd_id'] = $value->swd_id;
            $tmp['customer_name'] = $export_data[$key]['customer_name'] = $value->hasCustomer->customer_name;
            if($value->hasCustomer->customer_type == 1){
                $tmp['customer_type'] = $export_data[$key]['customer_type'] = '直客';
            }else if($value->hasCustomer->customer_type == 2){
                $tmp['customer_type'] = $export_data[$key]['customer_type'] = '渠道';
            }
            $tmp['project_name'] = $export_data[$key]['project_name'] = $value->project_name;
            $tmp['created_at'] = $export_data[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $tmp['add_user'] = $export_data[$key]['add_user'] = $user_data['id_realname'][$value->user_id];
            $tmp['project_business'] = $export_data[$key]['project_business'] = $user_data['id_realname'][$value->project_business];
            $tmp['verify'] = $user_data['id_realname'][$value->verify_user_id];
            $tmp['status'] = $value->status;
            $tmp['if_verify'] = 0;//是否显示审核按钮 1显示审核按钮  0不显示
            $tmp['if_create'] = 0;//是否显示创建按钮 1显示创建按钮  0不显示
            $tmp['if_view'] = 1;//是否显示查看按钮 1显示查看按钮  0不显示
            if($value->status == 0){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '待审核';
                $tmp['if_verify'] = $value->verify_user_id == $user_id ? 1 : 0;
                $tmp['if_view'] = $tmp['if_verify'] ? 0 : 1;
            }else if($value->status == 1){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '待上传合同';
                $tmp['if_create'] = $value->create_user_id == $user_id ? 1 : 0;
            }else if($value->status == 2){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '不通过';
            }else if($value->status == 3){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '待创建项目';
                $tmp['if_create'] = $value->create_user_id == $user_id ? 1 : 0;
            }else if($value->status == 4){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '已创建项目';
            }

            $items[] = $tmp;
        }
        $data['datalist'] = $items;
        if(isset($inputs['export']) && $inputs['export'] == 1){
            $export_head = ['商务单ID','客户名称','客户类型','项目名称','提交时间','提交人','商务','状态'];
            $filedata = pExprot($export_head, $export_data, 'business_order');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        $data['customer_type'] = $business_order->customer_types;
        $data['status'] = [['id'=> 0,'name' => '待审核'], ['id'=> 1, 'name' => '待上传合同'], ['id'=> 2, 'name' => '未通过'], ['id'=> 3, 'name' => '待创建项目'], ['id'=> 4, 'name' => '已创建项目']];
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 上传合同
     * @Author: molin
     * @Date:   2019-01-02
     */
    public function contracts(){
        $inputs = request()->all();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $business_order = new \App\Models\BusinessOrder;
            $order_info = $business_order->where('id', $inputs['id'])->select(['id', 'customer_id'])->first();
            $customer = new \App\Models\BusinessOrderCustomer;
            $customer_info = $customer->where('id', $order_info->customer_id)->select(['id', 'customer_name'])->first();
            $data['id'] = $inputs['id'];
            $data['customer_info'] = $customer_info;
            //合同类型 1.EDM技术服务合同 2.系统开发合同 3.服务器托管合同 4.代理合同 5.购销合同 6.API
            $data['type_list'] = $business_order->type_lists;
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
                //重命名
                $fileName = date('YmdHis').uniqid().'.'.$ext;
                if(!\File::isDirectory(storage_path('app/public/uploads/contracts/'))){
                    \File::makeDirectory(storage_path('app/public/uploads/contracts/'),  $mode = 0777, $recursive = false);
                }
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
            'id' => 'required|integer',
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
            'id' => 'id',
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
        $business_order = new \App\Models\BusinessOrder;
        $order_info = $business_order->where('id', $inputs['id'])->first();
        $contract = new \App\Models\BusinessOrderContract;
        $result = $contract->storeData($inputs);
        if($result){
            //更改商务单状态
            $order_info->status = 3;//已上传合同  待创建项目
            $order_info->save();
            systemLog('商务单', '上传了合同['.$inputs['number'].']');
            return response()->json(['code' => 1, 'message' => '上传成功']);
        }
        return response()->json(['code' => 0, 'message' => '上传失败']);
    }

    /**
     * 更改链接
     * @Author: molin
     * @Date:   2019-05-21
     */
    public function change(){
        $inputs = request()->all();
        if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
        }
        $data = array();
        $business_order = new \App\Models\BusinessOrder;
        $link = new \App\Models\BusinessOrderLink;
        $info = $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $order_info = array();
        $order_info['order_id'] = $inputs['order_id'];
        $order_info['swd_id'] = $info->swd_id;
        $order_info['customer_name'] = $info->hasCustomer->customer_name;
        $order_info['sale_man'] = $user_data['id_realname'][$info->project_sale];
        $order_info['business'] = $user_data['id_realname'][$info->project_business];
        $data['order_info'] = $order_info;

        $links_data = $link->getLinkList($inputs);
        $links_list = [];
        foreach ($links_data['datalist'] as $key => $value) {
            $tmp = [];
            $tmp['id'] = $value['id'];
            $tmp['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
            $tmp['link_name'] = $value['link_name'];
            $tmp['link_detail'] = $value['link_type'] == 1 ? (($value['pc_link'] ? ('PC:'.$value['pc_link']) : '').($value['wap_link'] ? ( ';WAP:'.$value['wap_link']): '')) : ('自适应:'.$value['zi_link']);
            $tmp['status'] = $value['if_use'] == 1 ? '已启用' : '已禁用';
            $tmp['if_use'] = $value['if_use'];
            $tmp['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
            $tmp['remarks'] = $value['remarks'];
            $tmp['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
            $tmp['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
            $links_list[] = $tmp;
        }
        $links_data['datalist'] = $links_list;
        $data['links_list'] = $links_data;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data'=> $data]);

    }

    /**
     * 商务单列表-更改链接-查看详情
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_change_view(){
        $inputs = request()->all();
        if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
        }
        $data = array();
        $business_order = new \App\Models\BusinessOrder;
        $link = new \App\Models\BusinessOrderLink;
        $info = $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $order_info = array();
        $order_info['order_id'] = $inputs['order_id'];
        $order_info['swd_id'] = $info->swd_id;
        $order_info['customer_name'] = $info->hasCustomer->customer_name;
        $order_info['sale_man'] = $user_data['id_realname'][$info->project_sale];
        $order_info['business'] = $user_data['id_realname'][$info->project_business];
        $data['order_info'] = $order_info;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //查看详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $info = $link->getLinkInfo($inputs);
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $link_info = [];
            $link_info['id'] = $info->id;
            $link_info['project_name'] = $info->project_id ? $info->hasProject->project_name : '--';
            $link_info['link_name'] = $info->link_name;
            $link_info['link_type'] = $info->link_type == 1 ? '分链接' : '自适应';
            $link_info['pc_link'] = $info->pc_link;
            $link_info['wap_link'] = $info->wap_link;
            $link_info['zi_link'] = $info->zi_link;
            $link_info['remarks'] = $info->remarks;
            $price_log = [];
            $hasPrice = $info->hasPrice->toArray();
            if(!empty($hasPrice)){
                foreach ($hasPrice as $key => $value) {
                    $tmp = [];
                    if($value['start_time'] > 0 && $value['end_time'] > 0){
                        $tmp['date'] = date('Y-m-d',$value['start_time']).'~'.date('Y-m-d',$value['end_time']);
                    }else{
                        $tmp['date'] = '即时生效';
                    }
                    $tmp['realname'] = $user_data['id_realname'][$value['user_id']];
                    $tmp['pricing_manner'] = $value['pricing_manner'];
                    $market_price = [];
                    foreach (unserialize($value['market_price']) as $kk => $vv) {
                        $str = '';
                        $str .= $kk.':';
                        $str .= $vv;
                        $market_price[] = $str;
                    }

                    $tmp['market_price'] = implode(';', $market_price);
                    $tmp['opt_time'] = $value['created_at'];
                    $price_log[] = $tmp;
                }
            }
            $link_info['price_log'] = $price_log;
            $data['link_info'] = $link_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
    }

    /**
     * 商务单列表-更改链接-导出链接
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_change_export(){
        $inputs = request()->all();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'export_links'){
            if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $links_list = (new \App\Models\BusinessOrderLink)->getLinkList(['order_id'=>$inputs['order_id'],'export'=>1]);
            if(count($links_list['datalist']) == 0){
                return response()->json(['code' => -1, 'message' => '没有链接可以导出']);
            }
            $export_links = array();
            foreach ($links_list['datalist'] as $key => $value) {
                $tmp = array();
                $tmp['id'] = $value['id'];
                $tmp['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $tmp['link_name'] = $value['link_name'];
                $tmp['pc_link'] = $value['pc_link'];
                $tmp['wap_link'] = $value['wap_link'];
                $tmp['zi_link'] = $value['zi_link'];
                $tmp['if_use'] = $value['if_use'] ? '启用' : '未启用';
                $tmp['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $tmp['pricing_manner'] = $value['pricing_manner'];
                $market_price = [];
                foreach (unserialize($value['market_price']) as $kk => $vv) {
                    $str = '';
                    $str .= $kk.':';
                    $str .= $vv;
                    $market_price[] = $str.'元';
                }

                $tmp['market_price'] = implode(';', $market_price);
                $tmp['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $tmp['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
                $tmp['remarks'] = $value['remarks'];
                $export_links[] = $tmp;
            }
            $export_head = ['链接ID','链接类型','链接名称','cp链接','wap链接','自适应','状态','项目','计价方式','单价','创建时间','最后更新时间','备注'];
            $filedata = pExprot($export_head, $export_links, 'order_links');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
    }

    /**
     * 商务单列表-更改链接-启用/禁用
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_change_use(){
        $inputs = request()->all();
        $link = new \App\Models\BusinessOrderLink;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'if_use'){
            //启用、禁用
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            if(!isset($inputs['if_use']) || !is_numeric($inputs['if_use'])){
                return response()->json(['code' => -1, 'message' => '缺少参数if_use']);
            }
            $link_info = $link->where('id', $inputs['id'])->first();
            if(empty($link_info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $link_info->if_use = $inputs['if_use'];
            $result = $link_info->save();
            if($result){
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);

        }
    }

    public function index_change_edit(){
        $inputs = request()->all();
        if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
        }
        $data = array();
        $business_order = new \App\Models\BusinessOrder;
        $link = new \App\Models\BusinessOrderLink;
        $info = $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $order_info = array();
        $order_info['order_id'] = $inputs['order_id'];
        $order_info['swd_id'] = $info->swd_id;
        $order_info['customer_name'] = $info->hasCustomer->customer_name;
        $order_info['sale_man'] = $user_data['id_realname'][$info->project_sale];
        $order_info['business'] = $user_data['id_realname'][$info->project_business];
        $data['order_info'] = $order_info;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit_load'){
            //编辑加载
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $info = $link->getLinkInfo($inputs);
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $link_info = [];
            $link_info['id'] = $info->id;
            $link_info['project_name'] = $info->project_id ? $info->hasProject->project_name : '--';
            $link_info['link_name'] = $info->link_name;
            $link_info['link_type'] = $info->link_type == 1 ? '分链接' : '自适应';
            $link_info['pc_link'] = $info->pc_link;
            $link_info['wap_link'] = $info->wap_link;
            $link_info['zi_link'] = $info->zi_link;
            $link_info['remarks'] = $info->remarks;
            $link_info['pricing_manner'] = $info->pricing_manner;
            $link_info['market_price'] = unserialize($info->market_price);
            $data['link_info'] = $link_info;
            $data['settlement_list'] = $business_order->settlement_lists;
            $data['user_list'] = $user->where('status', 1)->select(['id','realname'])->get();
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit_save'){
            //编辑保存
            $rules = [
                'id' => 'required|integer',
                'pricing_manner' => 'required',
                'market_price' => 'required|array',
                'notice_users' => 'required|array',
                'remarks' => 'required|max:100'

            ];
            $attributes = [
                'id' => '链接id',
                'pricing_manner' => '结算类型',
                'market_price' => '单价',
                'notice_users' => '通知人员',
                'remarks' => '备注信息'
            ];

            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $info = $link->getLinkInfo($inputs);
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            foreach ($inputs['market_price'] as $key => $val) {
                if(!in_array($key, ['CPA','CPC','CPD','CPS'])){
                    return response()->json(['code' => -1, 'message' => '项目单价类型错误']);
                }
                if(!is_numeric($val) || $val == 0){
                    return response()->json(['code' => -1, 'message' => '价格必须为大于0的数字']);
                }
                
            }

            $new_price_log = new \App\Models\BusinessOrderPrice;
            $new_price_log->order_id = $inputs['order_id'];           
            $new_price_log->pricing_manner = $inputs['pricing_manner'];           
            $new_price_log->market_price = serialize($inputs['market_price']);           
            $new_price_log->remarks = $inputs['remarks'];           
            $new_price_log->link_id = $inputs['id'];   
            $new_price_log->user_id = auth()->user()->id;   
            $new_price_log->notice_user_ids = isset($inputs['notice_users']) && !empty($inputs['notice_users']) ? implode(',', $inputs['notice_users']) : '';   

            //如果没有start_time和end_time 则为即使生效
            $feedback = new \App\Models\ProjectFeedback;
            $link_feedback = new \App\Models\LinkFeedback;  
            //取得当前链接在这段时间内所分配的所有项目
            $link_project_ids = $link_feedback->where('link_id', $inputs['id'])->when(!empty($inputs['start_time']) && !empty($inputs['end_time']), function ($query) use($inputs){
                                $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                            }, function ($query) {
                                $query->where('date', date('Y-m-d'));
                            })->pluck('project_id')->toArray();
            $link_project_ids = array_unique($link_project_ids);
            if(!empty($link_project_ids)){
                $link_feedback_all = $link_feedback->whereIn('project_id', $link_project_ids)->when(!empty($inputs['start_time']) && !empty($inputs['end_time']), function ($query) use($inputs){
                                $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                            }, function ($query) {
                                $query->where('date', date('Y-m-d'));
                            })->select(['link_id','cpa_price','cps_price','cpc_price','cpd_price'])->get()->toArray();
                $types = [];
                $other_link_types = $other_link_ids = [];
                foreach ($link_feedback_all as $key => $value) {
                    if($value['cpc_price'] > 0){
                        $types[] = 'CPC';
                    }
                    if($value['cpd_price'] > 0){
                        $types[] = 'CPD';
                    }
                    if($value['cpa_price'] > 0 && $value['cps_price'] > 0){
                        $types[] = 'CPA+CPS';
                    }
                    if($value['cpa_price'] > 0 && $value['cps_price'] == 0){
                        $types[] = 'CPA';
                    }
                    if($value['cpa_price'] == 0 && $value['cps_price'] > 0){
                        $types[] = 'CPS';
                    }
                    if($inputs['id'] != $value['link_id']){
                        if($value['cpc_price'] > 0){
                            $other_link_types[] = 'CPC';
                        }
                        if($value['cpd_price'] > 0){
                            $other_link_types[] = 'CPD';
                        }
                    }
                }
                
                $types = array_unique($types);
                if(in_array('CPC', $types) && in_array('CPA', $types)){
                    return response()->json(['code' => 0, 'message'=> '【CPC】不可以与【CPA】共存']);
                }
                if(in_array('CPD', $types) && in_array('CPA', $types)){
                    return response()->json(['code' => 0, 'message'=> '【CPD】不可以与【CPA】共存']);
                }
                if(in_array('CPC', $types) && in_array('CPS', $types)){
                    return response()->json(['code' => 0, 'message'=> '【CPC】不可以与【CPS】共存']);
                }
                if(in_array('CPD', $types) && in_array('CPS', $types)){
                    return response()->json(['code' => 0, 'message'=> '【CPD】不可以与【CPS】共存']);
                }
                if(in_array('CPC', $types) && in_array('CPA+CPS', $types)){
                    return response()->json(['code' => 0, 'message'=> '【CPC】不可以与【CPA+CPS】共存']);
                }
                if(in_array('CPD', $types) && in_array('CPA+CPS', $types)){
                    return response()->json(['code' => 0, 'message'=> '【CPD】不可以与【CPA+CPS】共存']);
                }
                
                if(in_array($inputs['pricing_manner'], ['CPA','CPS','CPA+CPS'])){
                    if(in_array('CPC', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPC】不可以与【'.$inputs['pricing_manner'].'】共存']);
                    }
                    if(in_array('CPD', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPD】不可以与【'.$inputs['pricing_manner'].'】共存']);
                    }
                }
                
                if($inputs['pricing_manner'] == 'CPC'){
                    if(in_array('CPD', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPD】不可以与【CPC】共存']);
                    }
                    if(in_array('CPA', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPA】不可以与【CPC】共存']);
                    }
                    if(in_array('CPS', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPS】不可以与【CPC】共存']);
                    }
                    if(in_array('CPA+CPS', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPA+CPS】不可以与【CPC】共存']);
                    }
                    
                }
                if($inputs['pricing_manner'] == 'CPD'){
                    if(in_array('CPC', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPC】不可以与【CPD】共存']);
                    }
                    if(in_array('CPA', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPA】不可以与【CPD】共存']);
                    }
                    if(in_array('CPS', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPS】不可以与【CPD】共存']);
                    }
                    if(in_array('CPA+CPS', $types)){
                        return response()->json(['code' => 0, 'message'=> '【CPA+CPS】不可以与【CPD】共存']);
                    }
                }
                if(!empty($other_link_types)){
                    return response()->json(['code' => 0, 'message'=> '【CPC/CPD】单价只能唯一']);
                }

            }   
            if(!empty($inputs['start_time']) && !empty($inputs['end_time'])){
                if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
                    return response()->json(['code' => 0, 'message' => '开始时间必须小于或等于结束时间']);
                }
                $new_price_log->old_pricing_manner = '';           
                $new_price_log->old_market_price = '';           
                $new_price_log->start_time = strtotime($inputs['start_time']);           
                $new_price_log->end_time = strtotime($inputs['end_time']);  
                
                if(!empty($link_project_ids)){
                    
                    $update = [];
                    $update['cpa_price'] = 0;
                    $update['cps_price'] = 0;
                    $update['cpc_price'] = 0;
                    $update['cpd_price'] = 0;
                    $update['updated_at'] = date('Y-m-d H:i:s');
                    if($inputs['pricing_manner'] == 'CPA'){
                        $update['cpa_price'] = $inputs['market_price']['CPA'];
                    }elseif($inputs['pricing_manner'] == 'CPS'){
                        $update['cps_price'] = $inputs['market_price']['CPS'];
                    }elseif($inputs['pricing_manner'] == 'CPC'){
                        $update['cpc_price'] = $inputs['market_price']['CPC'];
                    }elseif($inputs['pricing_manner'] == 'CPD'){
                        $update['cpd_price'] = $inputs['market_price']['CPD'];
                    }elseif($inputs['pricing_manner'] == 'CPA+CPS'){
                        $update['cpa_price'] = $inputs['market_price']['CPA'];
                        $update['cps_price'] = $inputs['market_price']['CPS'];
                    }
                    $feedback_list = $feedback->where('project_id', $link_project_ids)->select(['project_id','if_sett','month'])->get()->toArray();
                    foreach ($feedback_list as $key => $value) {
                        if($value['if_sett'] == 1){
                            return response()->json(['code' => 0, 'message' => '项目id：'.$value['project_id'].' 在'.$value['month'].'月份已结算完成[已到账]，不能更改金额']);
                        }
                    }

                    $link_feedback->where('link_id', $inputs['id'])->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->update($update);
                    
                    $days = prDates($inputs['start_time'], $inputs['end_time']);
                    foreach ($days as $d) {
                        foreach ($link_project_ids as $pid) {
                            $feedback->updateProjectIncome($pid, $d);
                        }
                    }
                    
                }
                
            }else{
                if(empty($inputs['start_time']) && !empty($inputs['end_time'])){
                    return response()->json(['code' => 0, 'message' => '请填写完整时间']);
                }
                if(!empty($inputs['start_time']) && empty($inputs['end_time'])){
                    return response()->json(['code' => 0, 'message' => '请填写完整时间']);
                }
                $new_price_log->old_pricing_manner = $info->pricing_manner;           
                $new_price_log->old_market_price = $info->market_price;           
                //即时生效 
                $info->pricing_manner = $inputs['pricing_manner'];
                $info->market_price = serialize($inputs['market_price']);
                if(!$info->save()){
                    return response()->json(['code' => 0, 'message' => '保存失败']);
                }
                //重新计算当天的反馈数据的单价和收入
                $update = [];
                $update['cpa_price'] = 0;
                $update['cps_price'] = 0;
                $update['cpc_price'] = 0;
                $update['cpd_price'] = 0;
                $update['updated_at'] = date('Y-m-d H:i:s');
                if($inputs['pricing_manner'] == 'CPA'){
                    $update['cpa_price'] = $inputs['market_price']['CPA'];
                }elseif($inputs['pricing_manner'] == 'CPS'){
                    $update['cps_price'] = $inputs['market_price']['CPS'];
                }elseif($inputs['pricing_manner'] == 'CPC'){
                    $update['cpc_price'] = $inputs['market_price']['CPC'];
                }elseif($inputs['pricing_manner'] == 'CPD'){
                    $update['cpd_price'] = $inputs['market_price']['CPD'];
                }elseif($inputs['pricing_manner'] == 'CPA+CPS'){
                    $update['cpa_price'] = $inputs['market_price']['CPA'];
                    $update['cps_price'] = $inputs['market_price']['CPS'];
                }
                if(!empty($link_project_ids)){
                    $link_feedback->where('link_id', $inputs['id'])->where('date', date('Y-m-d'))->update($update);
                    foreach ($link_project_ids as $key => $value) {
                        $feedback->updateProjectIncome($value, date('Y-m-d'));
                    }
                }

            }
            //写入价格记录
            $new_price_log->save();
            //通知
            if(isset($inputs['notice_users']) && !empty($inputs['notice_users'])){
                if($info->project_id > 0){
                    $notice_txt = '项目['.$info->hasProject->project_name.']链接ID：'.$inputs['id'].'的单价已更改，请查看';
                }else{
                    $notice_txt = '链接ID：'.$inputs['id'].'的单价已更改，请查看';
                }
                addNotice($inputs['notice_users'], '投递链接', $notice_txt, '', 0, 'bill-index','business_order/index');//提醒通知人员
            }
            systemLog('商务单', '编辑了链接单价，链接ID：['.$inputs['id'].']');
            return response()->json(['code' => 1, 'message' => '操作成功']);

        }
    }

    /**
     * 批量操作
     * @Author: molin
     * @Date:   2019-06-19
     */
    public function index_change_opt(){
        $inputs = request()->all();
        if(!isset($inputs['link_ids']) || !is_array($inputs['link_ids'])){
            return response()->json(['code' => -1, 'message' => '缺少参数link_ids']);
        }
        if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
        }
        $data = array();
        $link = new \App\Models\BusinessOrderLink;
        $link_list = $link->whereIn('id', $inputs['link_ids'])->get()->toArray();
        if(empty($link_list)){
            return response()->json(['code' => 0, 'message' => '没有符合的数据']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'opt_save'){
            //编辑保存
            $rules = [
                'link_ids' => 'required|array',
                'pricing_manner' => 'required',
                'market_price' => 'required|array',
                'remarks' => 'required|max:100'

            ];
            $attributes = [
                'link_ids' => '链接id集',
                'pricing_manner' => '结算类型',
                'market_price' => '单价',
                'remarks' => '备注信息'
            ];

            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            
            foreach ($inputs['market_price'] as $key => $val) {
                if(!in_array($key, ['CPA','CPC','CPD','CPS'])){
                    return response()->json(['code' => -1, 'message' => '项目单价类型错误']);
                }
                if(!is_numeric($val) || $val == 0){
                    return response()->json(['code' => -1, 'message' => '价格必须为大于0的数字']);
                }
                
            }

            $new_price_log = new \App\Models\BusinessOrderPrice;
            $price_log = [];
            $opt_id = auth()->user()->id;
            foreach ($link_list as $key => $value) {
                $price_log[$key]['order_id'] = $inputs['order_id'];  
                $price_log[$key]['pricing_manner'] = $inputs['pricing_manner'];      
                $price_log[$key]['market_price'] = serialize($inputs['market_price']);     
                $price_log[$key]['remarks'] = $inputs['remarks'];    
                $price_log[$key]['link_id'] = $value['id'];  
                $price_log[$key]['user_id'] = $opt_id;
                $price_log[$key]['notice_user_ids'] = '';
                
                if(!empty($inputs['start_time']) && !empty($inputs['end_time'])){
                    $price_log[$key]['start_time'] = strtotime($inputs['start_time']);     
                    $price_log[$key]['end_time'] = strtotime($inputs['end_time']);  
                }else{
                    $price_log[$key]['old_pricing_manner'] = $value['pricing_manner'];
                    $price_log[$key]['old_market_price'] = $value['market_price'];
                    $price_log[$key]['start_time'] = 0;     
                    $price_log[$key]['end_time'] = 0;  
                }
            }
            
            $feedback = new \App\Models\ProjectFeedback;
            $link_feedback = new \App\Models\LinkFeedback;   
            //所选链接在这段时间内所分配的所有项目
            $link_project_ids = $link_feedback->whereIn('link_id', $inputs['link_ids'])->when(!empty($inputs['start_time']) && !empty($inputs['end_time']), function ($query) use($inputs){
                                $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                            }, function ($query) {
                                $query->where('date', date('Y-m-d'));
                            })->pluck('project_id')->toArray();
            $link_project_ids = array_unique($link_project_ids);
            if(!empty($link_project_ids)){
                $link_feedback_all = $link_feedback->whereIn('project_id', $link_project_ids)->when(!empty($inputs['start_time']) && !empty($inputs['end_time']), function ($query) use($inputs){
                                $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                            }, function ($query) {
                                $query->where('date', date('Y-m-d'));
                            })->select(['link_id','cpa_price','cps_price','cpc_price','cpd_price'])->get()->toArray();
                $types = [];
                $all_link_ids = [];
                foreach ($link_feedback_all as $key => $value) {
                    $all_link_ids[] = $value['link_id'];
                }
                $all_link_ids = array_unique($all_link_ids);//去重
                sort($inputs['link_ids']);
                sort($all_link_ids);
                if(implode(',', $inputs['link_ids']) != implode(',', $all_link_ids)){
                    return response()->json(['code' => 0, 'message' => '该时间内投放的链接id有：'.implode(',', $all_link_ids).';所勾选链接id有：'.implode(',', $inputs['link_ids']).';请选择完整']);
                }
            }


            if(!empty($inputs['start_time']) && !empty($inputs['end_time'])){
                if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
                    return response()->json(['code' => 0, 'message' => '开始时间必须小于或等于结束时间']);
                } 
                
                if(!empty($link_project_ids)){
                    
                    //是否存在已结算项目
                    $if_exist_sett = $feedback->whereIn('project_id', $link_project_ids)->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->where('if_sett', 1)->first();
                    if(!empty($if_exist_sett)){
                        return response()->json(['code' => 0, 'message' => '所选链接所在时间段内已结算，不能再更改价格']);
                    }
                    $update = [];
                    $update['cpa_price'] = 0;
                    $update['cps_price'] = 0;
                    $update['cpc_price'] = 0;
                    $update['cpd_price'] = 0;
                    $update['updated_at'] = date('Y-m-d H:i:s');
                    if($inputs['pricing_manner'] == 'CPA'){
                        $update['cpa_price'] = $inputs['market_price']['CPA'];
                    }elseif($inputs['pricing_manner'] == 'CPS'){
                        $update['cps_price'] = $inputs['market_price']['CPS'];
                    }elseif($inputs['pricing_manner'] == 'CPC'){
                        $update['cpc_price'] = $inputs['market_price']['CPC'];
                    }elseif($inputs['pricing_manner'] == 'CPD'){
                        $update['cpd_price'] = $inputs['market_price']['CPD'];
                    }elseif($inputs['pricing_manner'] == 'CPA+CPS'){
                        $update['cpa_price'] = $inputs['market_price']['CPA'];
                        $update['cps_price'] = $inputs['market_price']['CPS'];
                    }
                    foreach ($link_list as $key => $value) {
                        $res1 = $link_feedback->where('link_id', $value['id'])->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->update($update);
                        if(!$res1) return response()->json(['code' => 0, 'message' => '操作失败']);
                    }
                    
                    $days = prDates($inputs['start_time'], $inputs['end_time']);
                    foreach ($days as $d) {
                        foreach ($link_project_ids as $pid) {
                            $res2 = $feedback->updateProjectIncome($pid, $d);
                            if(!$res2) return response()->json(['code' => 0, 'message' => '更新统计表失败']);
                        }
                    }
                }
            }else{
                if(empty($inputs['start_time']) && !empty($inputs['end_time'])){
                    return response()->json(['code' => 0, 'message' => '请填写完整时间']);
                }
                if(!empty($inputs['start_time']) && empty($inputs['end_time'])){
                    return response()->json(['code' => 0, 'message' => '请填写完整时间']);
                }
                   
                //即时生效 
                $resUp = $link->whereIn('id', $inputs['link_ids'])->update(['pricing_manner'=>$inputs['pricing_manner'], 'market_price'=>serialize($inputs['market_price']),'updated_at' => date('Y-m-d H:i:s')]);
                if(!$resUp){
                    return response()->json(['code' => 0, 'message' => '保存失败']);
                }
                //重新计算当天的反馈数据的单价和收入
                $update = [];
                $update['cpa_price'] = 0;
                $update['cps_price'] = 0;
                $update['cpc_price'] = 0;
                $update['cpd_price'] = 0;
                $update['updated_at'] = date('Y-m-d H:i:s');
                if($inputs['pricing_manner'] == 'CPA'){
                    $update['cpa_price'] = $inputs['market_price']['CPA'];
                }elseif($inputs['pricing_manner'] == 'CPS'){
                    $update['cps_price'] = $inputs['market_price']['CPS'];
                }elseif($inputs['pricing_manner'] == 'CPC'){
                    $update['cpc_price'] = $inputs['market_price']['CPC'];
                }elseif($inputs['pricing_manner'] == 'CPD'){
                    $update['cpd_price'] = $inputs['market_price']['CPD'];
                }elseif($inputs['pricing_manner'] == 'CPA+CPS'){
                    $update['cpa_price'] = $inputs['market_price']['CPA'];
                    $update['cps_price'] = $inputs['market_price']['CPS'];
                }

                if(!empty($link_project_ids)){
                    $res1 = $link_feedback->whereIn('link_id', $inputs['link_ids'])->where('date', date('Y-m-d'))->update($update);
                    if(!$res1) return response()->json(['code' => 0, 'message' => '反馈表更新失败']);
                    foreach ($link_project_ids as $key => $value) {
                        $res2 = $feedback->updateProjectIncome($value, date('Y-m-d'));
                        if(!$res2) return response()->json(['code' => 0, 'message' => '统计表更新失败']);
                    }
                }
            }
            
            //写入价格记录
            $new_price_log->insert($price_log);
            systemLog('商务单', '批量更改了链接单价，链接ID：['.implode(',', $inputs['link_ids']).']');
            return response()->json(['code' => 1, 'message' => '操作成功']);

        }
        $data['settlement_list'] = (new \App\Models\BusinessOrder)->settlement_lists;
        $items = [];
        foreach ($link_list as $key => $value) {
            $tmp = [];
            $tmp['id'] = $value['id'];
            $tmp['link_name'] = $value['link_name'];
            if($value['link_type'] == 1){
                $pc_link = $value['pc_link'] ? 'PC:'.$value['pc_link'] : "";
                $wap_link = $value['wap_link'] ? 'WAP:'.$value['wap_link'] : "";
                $tmp['link_detail'] = $pc_link.' '.$wap_link;
            }else{
                $tmp['link_detail'] = $value['zi_link'];
            }
            $tmp['pricing_manner'] = $value['pricing_manner'];
            $market_price = unserialize($value['market_price']);
            $mp = [];
            foreach ($market_price as $v) {
                $mp[] = $v;
            }
            $tmp['market_price'] = implode('|', $mp);
            $items[] = $tmp;
        }
        $data['datalist'] = $items;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        
        
    }

    /**
     * 添加链接
     * @Author: molin
     * @Date:   2019-06-19
     */
    public function index_change_add(){
        $inputs = request()->all();
        if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
        }
        $data = array();
        $business_order = new \App\Models\BusinessOrder;
        $link = new \App\Models\BusinessOrderLink;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'add_save'){
            //保存数据
            $rules = [
                'links' => 'required|array',
                'notice_users' => 'required|array'

            ];
            $attributes = [
                'links' => '投放链接',
                'notice_users' => '通知人员'
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }

            //投放链接
            $notice_txt = [];
            foreach ($inputs['links'] as $key => $value) {
                //链接
                if($value['link_type'] == 1){
                    //分链接
                    if(empty($value['pc_link']) && empty($value['wap_link'])){
                        return response()->json(['code' => -1, 'message' => '请填写pc、wap链接']);
                    }
                }else if($value['link_type'] == 2){
                    //自适应
                    if(empty($value['zi_link'])){
                        return response()->json(['code' => -1, 'message' => '请填写自适应内容']);
                    }
                }
                if(empty($value['link_name'])){
                    return response()->json(['code' => -1, 'message' => '请填写链接名称']);
                }
                if(empty($value['remarks'])){
                    return response()->json(['code' => -1, 'message' => '请填写链接备注']);
                }
                if(!isset($value['if_use']) || !in_array($value['if_use'], [0,1])){
                    return response()->json(['code' => -1, 'message' => '是否启用字段必填']);
                }
                if(!isset($value['pricing_manner']) || !in_array($value['pricing_manner'], ['CPD','CPC','CPA','CPS','CPA+CPS'])){
                    return response()->json(['code' => -1, 'message' => '请填结算类型']);
                }
                //链接单价
                foreach ($value['market_price'] as $key => $val) {
                    if(!in_array($key, ['CPA','CPC','CPD','CPS'])){
                        return response()->json(['code' => -1, 'message' => '项目单价类型错误']);
                    }
                    if(!is_numeric($val) || $val == 0){
                        return response()->json(['code' => -1, 'message' => '价格必须为大于0的数字']);
                    }
                }
                $notice_txt[] = $value['link_name'];
            }
            $res = $link->addLink($inputs);
            if($res){
                //通知
                if(isset($inputs['notice_users']) && !empty($inputs['notice_users'])){
                    addNotice($inputs['notice_users'], '添加链接', '新增链接'.implode(',', $notice_txt), '', 0, 'bill-index','business_order/index');
                }
                systemLog('商务单', '新增了链接，链接名：['.implode(',', $notice_txt).']');
                return response()->json(['code' => 1, 'message' => '添加成功']);
            }
            return response()->json(['code' => 0, 'message' => '失败成功']);
        }
        $info = $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $order_info = array();
        $order_info['order_id'] = $inputs['order_id'];
        $order_info['swd_id'] = $info->swd_id;
        $order_info['customer_name'] = $info->hasCustomer->customer_name;
        $order_info['sale_man'] = $user_data['id_realname'][$info->project_sale];
        $order_info['business'] = $user_data['id_realname'][$info->project_business];
        $data['order_info'] = $order_info;
        $data['settlement_list'] = $business_order->settlement_lists;
        $data['link_type'] = $business_order->link_types;
        $user_list = $user->select(['id','realname'])->where('status', 1)->get();
        $data['user_list'] = $user_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 创建项目
     * @Author: molin
     * @Date:   2019-01-03
     */
    public function create(){
        $inputs = request()->all();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $business_order = new \App\Models\BusinessOrder;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $settlement_type = array();
            
            foreach ($business_order->settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name'];
            }
            $inputs['status_in'] = [1,3];//待上传合同、待创建项目
            $order_info =  $business_order->getOrderInfo($inputs);
            if(empty($order_info)){
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
            if(!empty($order_info->other)){
                $other = unserialize($order_info->other);
                $other_arr = array();
                foreach ($other as $key => $value) {
                    $other_arr[$key]['file'] = $value['file'];
                    $other_arr[$key]['file_name'] = $value['file_name'];
                    $other_arr[$key]['file_path'] = asset($value['file']);
                }
                $order_info->other = $other_arr;
            }
            $order_settlement_type = array();
            foreach (explode(',', $order_info->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $order_info->settlement_type = implode(',', $order_settlement_type);
            
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                if($value['project_id'] == 0){
                    $links[$key]['id'] = $value['id'];
                    $links[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                    $links[$key]['link_name'] = $value['link_name'];
                    $links[$key]['pc_link'] = $value['pc_link'];
                    $links[$key]['wap_link'] = $value['wap_link'];
                    $links[$key]['zi_link'] = $value['zi_link'];
                    $links[$key]['remarks'] = $value['remarks'];
                    $links[$key]['if_use'] = $value['if_use'];
                    $links[$key]['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                    $links[$key]['pricing_manner'] = $value['pricing_manner'];
                    $links[$key]['market_price'] = unserialize($value['market_price']);
                    $links[$key]['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                    $links[$key]['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
                }
                

            }
            $order_info->links = $links;
            unset($order_info->hasLinks);
            
            $data['order_info'] = $order_info;
            $user_list = $user->where('status', 1)->select(['id', 'realname'])->get();
            $data['user_list'] = $user_list;
            $data['send_group'] = (new \App\Models\ProjectGroup)->select(['id','name'])->get()->toArray();
            $data['deliver_type'] = $business_order->deliver_types;
            $data['cooperation_cycle'] = $business_order->cooperation_cycles;
            $data['resource_type'] = $business_order->resource_types;
            $data['income_main_type'] = $business_order->income_main_types;
            $allowance_date = $tmp = array();
            for($i = 1; $i <= 15; $i++){
                $tmp['id'] = $i;
                $tmp['name'] = $i;
                $allowance_date[] = $tmp;
            }
            $data['allowance_date'] = $allowance_date;
            $data['status_list'] = $business_order->project_status;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

        //保存数据
        $rules = [
            'id' => 'required|integer',
            'project_name' => 'required|max:100',
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
            'password' => 'max:20'

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
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if(isset($inputs['link_ids']) && is_array($inputs['link_ids']) && !empty($inputs['link_ids']) && count($inputs['link_ids']) > 1){
            //分配多条链接的时候   CPA和CPS可以共存   CPC、CPD只能单独存在
            $res = (new \App\Models\BusinessOrderLink)->ifConflict($inputs);
            if($res['code'] != 1){
                return response()->json($res);
            }

        }
        $business_order = new \App\Models\BusinessOrder;
        $inputs['status_in'] = [1,3];//待上传合同、待创建项目
        $order_info =  $business_order->getOrderInfo($inputs);
        if(empty($order_info)){
            return response()->json(['code' => 0, 'message' => '当前商务单还不能创建项目']);
        }
        $project = new \App\Models\BusinessProject;
        $inputs['customer_id'] = $order_info->customer_id;//客户id
        $if_exist_project_name = $project->where('project_name', $inputs['project_name'])->select(['id'])->first();
        if(!empty($if_exist_project_name)){
            return response()->json(['code' => 0, 'message' => '已存在项目名称,请重新命名']);
        }   
        $inputs['trade_id'] = $order_info->trade_id;//行业
        $inputs['project_sale'] = $order_info->project_sale;//销售
        $inputs['project_type'] = $order_info->project_type;//项目类别
        $result = $project->storeData($inputs);
        if($result){
            //更改商务单状态
            $order_info->status = 4;//已创建项目
            if(isset($inputs['project_name']) && !empty($inputs['project_name'])){
                $order_info->project_name = $inputs['project_name'];
            }
            $order_info->save();
            //推送到V3  测试环境屏蔽  正式环境开启
            $msg = $project->doRequestSyn();
            if($msg['code'] != 1) return response()->json(['code' => 0, 'message' => $msg['message']]);
            systemLog('商务单', '创建了项目['.$order_info->project_name.']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /**
     * 商务单汇总
     * @Author: molin
     * @Date:   2019-01-03
     */
    public function collect(){
        $inputs = request()->all();
        $user_id = auth()->user()->id;
        if(isset($inputs['if_mine']) && $inputs['if_mine'] == 1){
            $inputs['verify_user_id'] = $user_id;//我处理的商务单
        }
        $business_order =  new \App\Models\BusinessOrder;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //查看详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $settlement_type = array();
            
            foreach ($business_order->settlement_types as $key => $value) {
                $settlement_type[$value['id']] = $value['name'];
            }
            $id = $inputs['id'];
            $order_info =  $business_order->getOrderInfo($inputs);
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
            $order_info->trade_name =$order_info->trade->name;
            $order_info->test_cycle = $order_info->test_cycle;
            $order_settlement_type = array();
            foreach (explode(',', $order_info->settlement_type) as $t) {
                $order_settlement_type[] = $settlement_type[$t];
            }
            $order_info->settlement_type = implode(',', $order_settlement_type);
            $order_info->verify_user_id = $user_data['id_realname'][$order_info->verify_user_id];
            if(!empty($order_info->other)){
                $other = unserialize($order_info->other);
                $other_arr = array();
                foreach ($other as $key => $value) {
                    $other_arr[$key]['file'] = $value['file'];
                    $other_arr[$key]['file_name'] = $value['file_name'];
                    $other_arr[$key]['file_path'] = asset($value['file']);
                }
                $order_info->other = $other_arr;
            }
            if($order_info->status == 0){
                $order_info->status_txt = '待审核';
            }else if($order_info->status == 1){
                $order_info->status_txt = '待上传合同';
            }else if($order_info->status == 2){
                $order_info->status_txt = '不通过';
            }else if($order_info->status == 3){
                $order_info->status_txt = '待创建项目';
            }else if($order_info->status == 4){
                $order_info->status_txt = '已创建项目';
            }
            $notice_users = array();
            foreach ($order_info->hasNotices as $v) {
                $notice_users[] = $user_data['id_realname'][$v['user_id']];
            }
            $order_info->notice_users = implode(',', $notice_users);
            $links = array();
            foreach ($order_info->hasLinks as $key => $value) {
                $links[$key]['id'] = $value['id'];
                $links[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $links[$key]['link_name'] = $value['link_name'];
                $links[$key]['pc_link'] = $value['pc_link'];
                $links[$key]['wap_link'] = $value['wap_link'];
                $links[$key]['zi_link'] = $value['zi_link'];
                $links[$key]['remarks'] = $value['remarks'];
                $links[$key]['if_use'] = $value['if_use'];
                $links[$key]['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $links[$key]['pricing_manner'] = $value['pricing_manner'];
                $links[$key]['market_price'] = unserialize($value['market_price']);
                $links[$key]['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $links[$key]['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
            }
            $order_info->links = $links;
            unset($order_info->hasLinks);
            $verifys = [];
            if(isset($order_info->hasVerify) && !empty($order_info->hasVerify->toArray())){
                foreach ($order_info->hasVerify as $v) {
                    $tmp = array();
                    $tmp['realname'] = $user_data['id_realname'][$v->user_id];
                    $tmp['comment'] = $v->comment;
                    $verifys[] = $tmp;
                }
            }
            $order_info->verifys = $verifys;
            $data['order_info'] = $order_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'export_links'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $links_list = (new \App\Models\BusinessOrderLink)->getLinkList(['order_id'=>$inputs['id'],'export'=>1]);
            if(count($links_list['datalist']) == 0){
                return response()->json(['code' => -1, 'message' => '没有链接可以导出']);
            }
            $export_links = array();
            foreach ($links_list['datalist'] as $key => $value) {
                $tmp = array();
                $tmp['id'] = $value['id'];
                $tmp['link_type'] = $value['link_type'] == 1 ? '分链接' : '自适应';
                $tmp['link_name'] = $value['link_name'];
                $tmp['pc_link'] = $value['pc_link'];
                $tmp['wap_link'] = $value['wap_link'];
                $tmp['zi_link'] = $value['zi_link'];
                $tmp['if_use'] = $value['if_use'] ? '启用' : '未启用';
                $tmp['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $tmp['pricing_manner'] = $value['pricing_manner'];
                $market_price = [];
                foreach (unserialize($value['market_price']) as $kk => $vv) {
                    $str = '';
                    $str .= $kk.':';
                    $str .= $vv;
                    $market_price[] = $str.'元';
                }

                $tmp['market_price'] = implode(';', $market_price);
                $tmp['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $tmp['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
                $tmp['remarks'] = $value['remarks'];
                $export_links[] = $tmp;
            }
            $export_head = ['链接ID','链接类型','链接名称','cp链接','wap链接','自适应','状态','项目','计价方式','单价','创建时间','最后更新时间','备注'];
            $filedata = pExprot($export_head, $export_links, 'order_links');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        
        $data = $business_order->getDataList($inputs);
        $items = $export_data = array();
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['swd_id'] = $export_data[$key]['swd_id'] = $value->swd_id;
            $tmp['customer_name'] = $export_data[$key]['customer_name'] = $value->hasCustomer->customer_name;
            if($value->hasCustomer->customer_type == 1){
                $tmp['customer_type'] = $export_data[$key]['customer_type'] =  '直客';
            }else if($value->hasCustomer->customer_type == 2){
                $tmp['customer_type'] = $export_data[$key]['customer_type'] = '渠道';
            }
            $tmp['project_name'] = $export_data[$key]['project_name'] = $value->project_name;
            $tmp['created_at'] = $export_data[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $tmp['add_user'] = $export_data[$key]['add_user'] = $user_data['id_realname'][$value->user_id];
            $tmp['project_business'] = $export_data[$key]['project_business'] = $user_data['id_realname'][$value->project_business];
            $tmp['verify'] = $user_data['id_realname'][$value->verify_user_id];
            

            if($value->status == 0){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '待审核';
            }else if($value->status == 1){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '待上传合同';
            }else if($value->status == 2){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '不通过';
            }else if($value->status == 3){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '待创建项目';
            }else if($value->status == 4){
                $tmp['status_txt'] = $export_data[$key]['status_txt'] = '已创建项目';
            }

            $items[] = $tmp;
        }
        $data['datalist'] = $items;
        if(isset($inputs['export']) && $inputs['export'] == 1){
            $export_head = ['商务单ID','客户名称','客户类型','项目名称','提交时间','提交人','商务','状态'];
            $filedata = pExprot($export_head, $export_data, 'business_order');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
        $data['customer_type'] = $business_order->customer_types;
        $data['status'] = [['id'=> 0,'name' => '待审核'], ['id'=> 1, 'name' => '待上传合同'], ['id'=> 2, 'name' => '未通过'], ['id'=> 3, 'name' => '待创建项目'], ['id'=> 4, 'name' => '已创建项目']];
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

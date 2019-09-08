<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BusinessOrderLinkController extends Controller
{
    //链接管理
    /**
     * 链接汇总
     * @Author: molin
     * @Date:   2019-02-22
     */
   	public function index(){
        $inputs = request()->all();
        //$business_order = new \App\Models\BusinessOrder;
        $project = new \App\Models\BusinessProject;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        
        $inputs['charge_or_business_all'] = auth()->user()->id;//只显示当前用户为销售、商务、商务助理和项目负责人的项目
        // $inputs['link_count'] = 1;
        $data = $project->getDataList($inputs);
        // dd($data['datalist']->toArray());
        //$data = $business_order->getDataList($inputs);
        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['order_id'] = $value->order_id;
            $tmp['project_name'] = $value->project_name;
            $tmp['customer_name'] = $value->hasCustomer->customer_name;
            $tmp['saleman'] = $user_data['id_realname'][$value->sale_man];
            $tmp['business'] = $user_data['id_realname'][$value->business_id];
            $tmp['assistant'] = $user_data['id_realname'][$value->assistant_id];
            $tmp['charge'] = $user_data['id_realname'][$value->charge_id];
            $tmp['links_num'] = $value->hasLink[0]['count'] ?? 0;
            $items[] = $tmp;
        }
        
        $data['datalist'] = $items;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 链接汇总-查看链接
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_view(){
        $inputs = request()->all();
        $business_order = new \App\Models\BusinessOrder;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
            }
            $data = array();
            $link = new \App\Models\BusinessOrderLink;
            $info = $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
            if(empty($info)){
                return response()->json(['code' => -1, 'message' => '数据不存在']);
            }
            $order_info = array();
            $order_info['order_id'] = $inputs['order_id'];
            $order_info['swd_id'] = $info->swd_id;
            $order_info['customer_name'] = $info->hasCustomer->customer_name;
            $order_info['sale_man'] = $user_data['id_realname'][$info->project_sale];
            $order_info['business'] = $user_data['id_realname'][$info->project_business];
            $data['order_info'] = $order_info;

            if(isset($inputs['type']) && $inputs['type'] == 'view'){
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
                $tmp['project_id'] = $value['project_id'];
                $tmp['project_name'] = $value['project_id'] > 0 ? $value['hasProject']['project_name'] : '--';
                $tmp['remarks'] = $value['remarks'];
                $tmp['created_at'] = $value['created_at']->format('Y-m-d H:i:s');
                $tmp['updated_at'] = $value['updated_at']->format('Y-m-d H:i:s');
                $links_list[] = $tmp;
            }
            $links_data['datalist'] = $links_list;
            $data['links_list'] = $links_data;
            $data['project_list'] = (new \App\Models\BusinessProject)->where('order_id', $inputs['order_id'])->select(['id','project_name'])->get();
            return response()->json(['code' => 1, 'message' => '获取成功', 'data'=> $data]);

        }
    }

    /**
     * 链接汇总-查看链接-查看详情
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_view_view(){
        $inputs = request()->all();
        $business_order = new \App\Models\BusinessOrder;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $data = array();
        if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
        }
        $data = array();
        $link = new \App\Models\BusinessOrderLink;
        $info = $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
        if(empty($info)){
            return response()->json(['code' => -1, 'message' => '数据不存在']);
        }
        $order_info = array();
        $order_info['order_id'] = $inputs['order_id'];
        $order_info['swd_id'] = $info->swd_id;
        $order_info['customer_name'] = $info->hasCustomer->customer_name;
        $order_info['sale_man'] = $user_data['id_realname'][$info->project_sale];
        $order_info['business'] = $user_data['id_realname'][$info->project_business];
        $data['order_info'] = $order_info;

        if(isset($inputs['type']) && $inputs['type'] == 'view'){
            //查看详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $link = new \App\Models\BusinessOrderLink;
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
     * 链接汇总-查看链接-导出链接
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_view_export(){
        $inputs = request()->all();
        if(isset($inputs['type']) && $inputs['type'] == 'export_links'){
            //导出链接
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
            $export_head = ['链接ID','链接类型','链接名称','pc链接','wap链接','自适应','状态','项目','计价方式','单价','创建时间','最后更新时间','备注'];
            $filedata = pExprot($export_head, $export_links, 'order_links');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
    }

    /**
     * 链接汇总-查看链接-启用/禁用
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_view_use(){
        $inputs = request()->all();
        $link = new \App\Models\BusinessOrderLink;
        if(isset($inputs['type']) && $inputs['type'] == 'if_use'){
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
                systemLog('链接管理', ($inputs['if_use'] = 1 ? "启用了":"禁用了").'链接:'.$link_info->link_name.' 链接id：'.$link_info->id);
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);

        }
    }

    /**
     * 链接汇总-查看链接-编辑
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_view_edit(){
        $inputs = request()->all();
        $business_order = new \App\Models\BusinessOrder;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $link = new \App\Models\BusinessOrderLink;
        if(isset($inputs['type']) && $inputs['type'] == 'edit_load'){
            //编辑加载
            $data = array();
            $info = $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
            if(empty($info)){
                return response()->json(['code' => -1, 'message' => '数据不存在']);
            }
            $order_info = array();
            $order_info['order_id'] = $inputs['order_id'];
            $order_info['swd_id'] = $info->swd_id;
            $order_info['customer_name'] = $info->hasCustomer->customer_name;
            $order_info['sale_man'] = $user_data['id_realname'][$info->project_sale];
            $order_info['business'] = $user_data['id_realname'][$info->project_business];
            $data['order_info'] = $order_info;
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $info = $link->getLinkInfo($inputs);
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
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
        if(isset($inputs['type']) && $inputs['type'] == 'edit_save'){
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

                //重新计算这段时间内的反馈数据的单价和收入

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

                    $res1 = $link_feedback->where('link_id', $inputs['id'])->whereBetween('date', [$inputs['start_time'], $inputs['end_time']])->update($update);
                    if(!$res1) return response()->json(['code' => 0, 'message' => '反馈表更新失败']);
                    
                    $days = prDates($inputs['start_time'], $inputs['end_time']);
                    foreach ($days as $d) {
                        foreach ($link_project_ids as $pid) {
                            $res2 = $feedback->updateProjectIncome($pid, $d);
                            if(!$res2) return response()->json(['code' => 0, 'message' => '统计表更新失败']);
                        }
                    }
                    
                }
                
            }else{
                $new_price_log->old_pricing_manner = $info->pricing_manner;           
                $new_price_log->old_market_price = $info->market_price;         
                //即时生效  不需要更新反馈数据表数据
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
                    $res1 = $link_feedback->where('link_id', $inputs['id'])->where('date', date('Y-m-d'))->update($update);
                    if(!$res1) return response()->json(['code' => 0, 'message' => '反馈表更新失败']);
                    foreach ($link_project_ids as $key => $value) {
                        $res2 = $feedback->updateProjectIncome($value, date('Y-m-d'));
                        if(!$res2) return response()->json(['code' => 0, 'message' => '统计表更新失败']);
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
                addNotice($inputs['notice_users'], '投递链接', $notice_txt, '', 0, 'project-link-index','links/index');//提醒通知人员
            }
            systemLog('链接管理', '更改了链接单价:'.$info->link_name.' 链接id：'.$info->id);
            return response()->json(['code' => 1, 'message' => '操作成功']);

        }
    }

    /**
     * 链接汇总-查看链接-分配项目
     * @Author: molin
     * @Date:   2019-06-06
     */
    public function index_view_assign(){
        $inputs = request()->all();
        $link = new \App\Models\BusinessOrderLink;
        if(isset($inputs['type']) && $inputs['type'] == 'assign'){
          if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
          }
          if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数project_id']);
          }
          $link_info = $link->where('id', $inputs['id'])->first();
          if(empty($link_info)){
            return response()->json(['code' => 0, 'message' => '链接不存在']);
          }
          $old_project_id = $link_info->project_id;
          if($inputs['project_id'] > 0){
            $link_ids = $link->where('id', $inputs['id'])->orWhere('project_id', $inputs['project_id'])->pluck('id')->toArray();
            if(count($link_ids) > 1){
              //分配多条链接时  需要做判断
              $res = $link->ifConflict(['link_ids' => $link_ids, 'pid'=>$inputs['project_id']]);
              if($res['code'] != 1){
                  return response()->json($res);
              }
            }
          }
          
          $link_info->project_id = $inputs['project_id'];
          $result = $link_info->save();
          if($result){
            //创建分配日志 注： 取消分配不需要创建日志  新分配或者切换分配的时候再创建 （取消分配的时候 这条链接还有这个项目的反馈余量）
            if($inputs['project_id'] > 0 && $inputs['project_id'] != $old_project_id){
                (new \App\Models\BusinessProject)->createLinkLog([$link_info->id],$inputs['project_id']);
            }
            //更改链接反馈表当天绑定的项目  更改前的项目和更改后的项目反馈要重新统计
            (new \App\Models\LinkFeedback)->where('link_id',$inputs['id'])->where('date', date('Y-m-d'))->update(['project_id'=>$inputs['project_id']]);
            $feedback = new \App\Models\ProjectFeedback;
            if($old_project_id != $inputs['project_id'] && $old_project_id > 0){
                $feedback->updateProjectIncome($old_project_id, date('Y-m-d'));//重新计算之前绑定的项目
            }
            if($old_project_id != $inputs['project_id'] && $inputs['project_id'] > 0){
                $feedback->updateProjectIncome($inputs['project_id'], date('Y-m-d'));//计算新绑定的项目
            }
            systemLog('链接管理', ($inputs['project_id'] > 0 ? "分配链接id：":"取消分配链接id：").$link_info->id.'给项目id：'.$inputs['project_id']);
            return response()->json(['code' => 1, 'message' => '操作成功']);
          }
          return response()->json(['code' => 0, 'message' => '操作失败']);

        }
    }

   	/**
     * 链接汇总-申请链接
     * @Author: molin
     * @Date:   2019-02-22
     */
   	public function apply(){
   		$inputs = request()->all();
   		
   		$business_order = new \App\Models\BusinessOrder;
   		if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
            //保存数据
            $rules = [
                'order_id' => 'required|integer',
                'degree_id' => 'required|integer',
                'remarks' => 'required'
            ];
            $attributes = [
                'order_id' => 'order_id',
                'degree_id' => '紧急情况',
                'remarks' => '申请说明'
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $apply_link = new \App\Models\BusinessOrderLinkApply;
            $order_info =  $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
            $link = new \App\Models\BusinessOrderLink;
            $old_links_list = $link->where('order_id', $inputs['order_id'])->get();
            $old_links_data = array();
            foreach ($old_links_list as $key => $value) {
              $tmp = array();
              $tmp['id'] = $value->id;
              $tmp['link_type'] = $value->link_type;
              $tmp['link_name'] = $value->link_name;
              $tmp['pc_link'] = $value->pc_link;
              $tmp['wap_link'] = $value->wap_link;
              $tmp['zi_link'] = $value->zi_link;
              $tmp['remarks'] = $value->remarks;
              $tmp['if_use'] = $value->if_use;
              $tmp['project_name'] = $value->project_id > 0 ? $value->hasProject->project_name : '--';
              $tmp['pricing_manner'] = $value->pricing_manner;
              $tmp['market_price'] = unserialize($value->market_price);
              $tmp['created_at'] = $value->created_at->format('Y-m-d H:i:s');
              $tmp['updated_at'] = $value->updated_at->format('Y-m-d H:i:s');
              $old_links_data[] = $tmp;
            }
            $insert = array();
            $insert['project_id'] = 0;
            $insert['order_id'] = $inputs['order_id'];
            $insert['degree_id'] = $inputs['degree_id'];
            $insert['remarks'] = $inputs['remarks'];
            $insert['business_id'] = $order_info->project_business;
            $insert['old_links'] = !empty($old_links_data) ? serialize($old_links_data) : '';
            $result = $apply_link->storeData($insert);
            if ($result) {
              systemLog('链接申请', '提交了链接申请');
              $user_data = (new \App\Models\User)->getIdToData();
              $uid = auth()->user()->id;
              $to_user = $user_data['id_realname'][$uid];
              addNotice($order_info->project_business, '链接申请', $to_user.'提交了一条链接申请，请及时查看', '', 0, 'project-link-audit','links/verify');//提醒商务
              return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);

   		}

      if(!isset($inputs['order_id']) || !is_numeric($inputs['order_id'])){
        return response()->json(['code' => -1, 'message' => '缺少参数order_id']);
      }
      $user = new \App\Models\User;
      $user_data = $user->getIdToData();
      $order_info =  $business_order->getOrderInfo(['id'=>$inputs['order_id']]);
      $items = array();
      $items['order_id'] = $inputs['order_id'];
      $items['customer_name'] = $order_info->hasCustomer->customer_name;
      $items['project_name'] = $order_info->project_name;
      $items['business'] = $user_data['id_realname'][$order_info->project_business];
      
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
          $links[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
          $links[$key]['pricing_manner'] = $value->pricing_manner;
          $links[$key]['market_price'] = unserialize($value->market_price);
          $links[$key]['updated_at'] = $value->updated_at->format('Y-m-d H:i:s');
      }
      $items['links'] = $links;
      $data['order_info'] = $items;
      $data['degree_list'] = [['id'=>1, 'name'=>'紧急'],['id'=>2, 'name'=>'一般']];
      return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
   	}

   	/**
     * 我的链接申请
     * @Author: molin
     * @Date:   2019-02-22
     */
   	public function list(){
   		$inputs = request()->all();
   		$inputs['user_id'] = auth()->user()->id;
   		$link = new \App\Models\BusinessOrderLinkApply;
   		if(isset($inputs['request_type']) && $inputs['request_type'] == 'return'){
   			if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
   				return response()->json(['code' => -1, 'message' => '缺少参数id']);
   			}
   			$link_info = $link->where('id', $inputs['id'])->where('status', 0)->first();
            if(empty($link_info)){
              return response()->json(['code' => 0, 'message' => '这条申请无法撤回']);
            }
   			$link_info->status = 3;//撤回
   			$result = $link_info->save();
   			if($result){
   				systemLog('链接申请', '撤回了链接申请');
   				return response()->json(['code' => 1, 'message' => '操作成功']);
   			}
   			return response()->json(['code' => 0, 'message' => '操作失败']);
   		}
   		if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
   			if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
   				return response()->json(['code' => -1, 'message' => '缺少参数id']);
   			}
   			$link_info = $link->where('id', $inputs['id'])->first();
            if(empty($link_info)){
              return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
   			$data = array();
   			$user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $business_order = new \App\Models\BusinessOrder;
            $order_info =  $business_order->getOrderInfo(['id'=>$link_info->order_id]);
            $items = array();
            $items['id'] = $order_info->id;
            $items['customer_name'] = $order_info->hasCustomer->customer_name;
            $items['project_name'] = $order_info->project_name;
            $items['business'] = $user_data['id_realname'][$order_info->project_business];
            $links = array();
            if(!empty($link_info->old_links)){
              $links = unserialize($link_info->old_links);
              $links_items = [];
              foreach ($links as $key => $value) {
                $links_items[$key]['id'] = $value['id'];
                $links_items[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自定义';
                $links_items[$key]['link_name'] = $value['link_name'];
                $links_items[$key]['pc_link'] = $value['pc_link'];
                $links_items[$key]['wap_link'] = $value['wap_link'];
                $links_items[$key]['zi_link'] = $value['zi_link'];
                $links_items[$key]['remarks'] = $value['remarks'];
                $links_items[$key]['if_use'] = $value['if_use'];
                $links_items[$key]['project_name'] = $value['project_name'] ?? '--';
                $links_items[$key]['pricing_manner'] = $value['pricing_manner'];
                $links_items[$key]['market_price'] = $value['market_price'];
                $links_items[$key]['created_at'] = $value['created_at'];
                $links_items[$key]['updated_at'] = $value['updated_at'];
              }
              $links = $links_items;
            }
            $items['links'] = $links;
            $data['order_info'] = $items;
            $apply_info = array();
            if($link_info->status == 1){
                $apply_info['status'] = '已完成';
   			}elseif($link_info->status == 2){
   				$apply_info['status'] = '已驳回';
   			}elseif($link_info->status == 3){
                $apply_info['status'] = '已撤回';
            }else{
                $apply_info['status'] = '未处理';
            }
            $apply_info['degree'] = $link_info->degree_id == 1 ? '紧急':'一般';
            $apply_info['remarks'] = $link_info->remarks;
            $apply_info['feedback'] = $link_info->feedback ? $link_info->feedback : '';
            $apply_info['links'] = !empty($link_info->new_links) ? unserialize($link_info->new_links) : array();
            $data['apply_info'] = $apply_info;
       		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
       	}
   		$data = $link->getLinkList($inputs);
   		$user = new \App\Models\User;
        $user_data = $user->getIdToData();
   		$items = array();
   		foreach ($data['datalist'] as $key => $value) {
   			$items[$key]['id'] = $value->id;
            $items[$key]['swd_id'] = $value->hasOrder->swd_id;
   			$items[$key]['project_name'] = $value->hasOrder->project_name;
   			$items[$key]['customer_name'] = $value->hasOrder->hasCustomer->customer_name;
   			$items[$key]['remarks'] = $value->remarks;
   			$items[$key]['business'] = $user_data['id_realname'][$value->hasOrder->project_business];
            $items[$key]['sale_man'] = $user_data['id_realname'][$value->hasOrder->project_sale];
   			if($value->degree_id == 1){
   				$items[$key]['degree'] = '紧急';
   			}else{
   				$items[$key]['degree'] = '一般';
   			}
   			$items[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $items[$key]['if_return'] = 0;//隐藏撤回按钮
   			if($value->status == 1){
   				$items[$key]['status'] = '已完成';
   			}elseif($value->status == 2){
   				$items[$key]['status'] = '已驳回';
   			}elseif($value->status == 3){
                $items[$key]['status'] = '已撤回';
            }else{
                $items[$key]['status'] = '未处理';
                $items[$key]['if_return'] = 1;//显示撤回按钮
            }
   			
   		}
        $data['datalist'] = $items;
   		$data['status_list'] = [['id'=>0, 'name'=>'未处理'],['id'=>1, 'name'=>'已完成'], ['id'=>2, 'name'=>'已驳回'], ['id'=>2, 'name'=>'已撤回']];
   		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
   	}

    /**
     * 我要处理的链接申请
     * @Author: molin
     * @Date:   2019-03-20
     */
    public function verify(){
      $inputs = request()->all();
      $link = new \App\Models\BusinessOrderLinkApply;
      $user = new \App\Models\User;
      $user_data = $user->getIdToData();
      if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
          return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $link_info = $link->where('id', $inputs['id'])->first();
        if(empty($link_info)){
          return response()->json(['code' => 0, 'message' => '数据不存在']);
        }
        $data = array();
        $business_order = new \App\Models\BusinessOrder;
        $order_info =  $business_order->getOrderInfo(['id'=>$link_info->order_id]);
        $items = array();
        $items['id'] = $order_info->id;
        $items['customer_name'] = $order_info->hasCustomer->customer_name;
        $items['project_name'] = $order_info->project_name;
        $items['business'] = $user_data['id_realname'][$order_info->project_business];
        
        $links = array();
        if(!empty($link_info->old_links)){
          $links = unserialize($link_info->old_links);
          $links_items = [];
          foreach ($links as $key => $value) {
            $links_items[$key]['id'] = $value['id'];
            $links_items[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自定义';
            $links_items[$key]['link_name'] = $value['link_name'];
            $links_items[$key]['pc_link'] = $value['pc_link'];
            $links_items[$key]['wap_link'] = $value['wap_link'];
            $links_items[$key]['zi_link'] = $value['zi_link'];
            $links_items[$key]['remarks'] = $value['remarks'];
            $links_items[$key]['if_use'] = $value['if_use'];
            $links_items[$key]['project_name'] = $value['project_name'] ?? '--';
            $links_items[$key]['pricing_manner'] = $value['pricing_manner'];
            $links_items[$key]['market_price'] = $value['market_price'];
            $links_items[$key]['created_at'] = $value['created_at'];
            $links_items[$key]['updated_at'] = $value['updated_at'];
          }
          $links = $links_items;
        }
        $items['links'] = $links;
        $data['order_info'] = $items;
        $apply_info = array();
        if($link_info->status == 1){
          $apply_info['status'] = '已完成';
        }elseif($link_info->status == 2){
          $apply_info['status'] = '已驳回';
        }elseif($link_info->status == 3){
          $apply_info['status'] = '已撤回';
        }else{
          $apply_info['status'] = '未处理';
        }
        $apply_info['degree'] = $link_info->degree_id == 1 ? '紧急':'一般';
        $apply_info['remarks'] = $link_info->remarks;
        $apply_info['feedback'] = $link_info->feedback ? $link_info->feedback : '';
        $apply_info['links'] = !empty($link_info->new_links) ? unserialize($link_info->new_links) : array();
        $data['apply_info'] = $apply_info;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
      }
      $inputs['business_id'] = auth()->user()->id;
      $data = $link->getLinkList($inputs);
      $items = array();
      foreach ($data['datalist'] as $key => $value) {
        $items[$key]['id'] = $value->id;
        $items[$key]['swd_id'] = $value->hasOrder->swd_id;
        $items[$key]['project_name'] = $value->hasOrder->project_name;
        $items[$key]['customer_name'] = $value->hasOrder->hasCustomer->customer_name;
        $items[$key]['remarks'] = $value->remarks;
        $items[$key]['business'] = $user_data['id_realname'][$value->hasOrder->project_business];
        if($value->degree_id == 1){
          $items[$key]['degree'] = '紧急';
        }else{
          $items[$key]['degree'] = '一般';
        }
        $items[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
        $items[$key]['if_deal'] = 0;//隐藏处理按钮 
        if($value->status == 1){
          $items[$key]['status'] = '已完成';
        }elseif($value->status == 2){
          $items[$key]['status'] = '已驳回';
        }elseif($value->status == 3){
          $items[$key]['status'] = '已撤回';
        }else{
          $items[$key]['status'] = '未处理';
          $items[$key]['if_deal'] = 1;//显示处理按钮 
        }

      }
      $data['datalist'] = $items;
      $data['degree_list'] = [['id'=>1, 'name'=>'紧急'],['id'=>2, 'name'=>'一般']];
      $data['status_list'] = [['id'=>1, 'name'=>'待处理'],['id'=>2, 'name'=>'已完成'], ['id'=>3, 'name'=>'已撤销']];
      return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

   	/**
     * 链接申请汇总
     * @Author: molin
     * @Date:   2019-02-25
     */
   	public function summary(){
   		$inputs = request()->all();
   		$link = new \App\Models\BusinessOrderLinkApply;
   		$user = new \App\Models\User;
        $user_data = $user->getIdToData();
   		if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
   			if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
   				return response()->json(['code' => -1, 'message' => '缺少参数id']);
   			}
   			$link_info = $link->where('id', $inputs['id'])->first();
        if(empty($link_info)){
          return response()->json(['code' => 0, 'message' => '数据不存在']);
        }
   			$data = array();
        $business_order = new \App\Models\BusinessOrder;
        $order_info =  $business_order->getOrderInfo(['id'=>$link_info->order_id]);
        $items = array();
        $items['id'] = $order_info->id;
        $items['customer_name'] = $order_info->hasCustomer->customer_name;
        $items['project_name'] = $order_info->project_name;
        $items['business'] = $user_data['id_realname'][$order_info->project_business];
        
        $links = array();
        if(!empty($link_info->old_links)){
          $links = unserialize($link_info->old_links);
          $links_items = [];
          foreach ($links as $key => $value) {
            $links_items[$key]['id'] = $value['id'];
            $links_items[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自定义';
            $links_items[$key]['link_name'] = $value['link_name'];
            $links_items[$key]['pc_link'] = $value['pc_link'];
            $links_items[$key]['wap_link'] = $value['wap_link'];
            $links_items[$key]['zi_link'] = $value['zi_link'];
            $links_items[$key]['remarks'] = $value['remarks'];
            $links_items[$key]['if_use'] = $value['if_use'];
            $links_items[$key]['project_name'] = $value['project_name'] ?? '--';
            $links_items[$key]['pricing_manner'] = $value['pricing_manner'];
            $links_items[$key]['market_price'] = $value['market_price'];
            $links_items[$key]['created_at'] = $value['created_at'];
            $links_items[$key]['updated_at'] = $value['updated_at'];
          }
          $links = $links_items;
        }
        $items['links'] = $links;
        $data['order_info'] = $items;
        $apply_info = array();
        if($link_info->status == 1){
          $apply_info['status'] = '已完成';
        }elseif($link_info->status == 2){
          $apply_info['status'] = '已驳回';
        }elseif($link_info->status == 3){
          $apply_info['status'] = '已撤回';
        }else{
          $apply_info['status'] = '未处理';
        }
        $apply_info['degree'] = $link_info->degree_id == 1 ? '紧急':'一般';
        $apply_info['remarks'] = $link_info->remarks;
        $apply_info['feedback'] = $link_info->feedback ? $link_info->feedback : '';
        $apply_info['links'] = !empty($link_info->new_links) ? unserialize($link_info->new_links) : array();
        $data['apply_info'] = $apply_info;
   			return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
   		}
   		$data = $link->getLinkList($inputs);
   		$items = array();
   		foreach ($data['datalist'] as $key => $value) {
   			$items[$key]['id'] = $value->id;
            $items[$key]['swd_id'] = $value->hasOrder->swd_id;
   			$items[$key]['project_name'] = $value->hasOrder->project_name;
   			$items[$key]['customer_name'] = $value->hasOrder->hasCustomer->customer_name;
   			$items[$key]['remarks'] = $value->remarks;
   			$items[$key]['business'] = $user_data['id_realname'][$value->hasOrder->project_business];
   			if($value->degree_id == 1){
   				$items[$key]['degree'] = '紧急';
   			}else{
   				$items[$key]['degree'] = '一般';
   			}
   			$items[$key]['created_at'] = $value->created_at->format('Y-m-d H:i:s');
        if($value->status == 1){
          $items[$key]['status'] = '已完成';
        }elseif($value->status == 2){
          $items[$key]['status'] = '已驳回';
        }elseif($value->status == 3){
          $items[$key]['status'] = '已撤回';
        }else{
          $items[$key]['status'] = '未处理';
        }

   		}
   		$data['datalist'] = $items;
   		$data['degree_list'] = [['id'=>1, 'name'=>'紧急'],['id'=>2, 'name'=>'一般']];
   		$data['status_list'] = [['id'=>0, 'name'=>'待处理'],['id'=>1, 'name'=>'已完成'],['id'=>2, 'name'=>'已驳回'], ['id'=>3, 'name'=>'已撤销']];
   		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
   	}

		/**
     * 链接申请汇总-处理
     * @Author: molin
     * @Date:   2019-02-25
     */
	public function deal(){
		$inputs = request()->all();
		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
			return response()->json(['code' => -1, 'message' => '缺少参数id']);
		}
		$link = new \App\Models\BusinessOrderLinkApply;
   	    $user = new \App\Models\User;
        $user_data = $user->getIdToData();
		$link_info = $link->where('id', $inputs['id'])->where('status', 0)->first();
		if(empty($link_info)){
			return response()->json(['code' => 0, 'message' => '这条申请无法处理']);
		}
		if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
			//保存数据
            $rules = [
                'feedback' => 'required|max:250',
                'links' => 'required|array'
            ];
            $attributes = [
                'feedback' => '反馈',
                'links' => '新连接'
            ];
          
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
              return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            //投放链接
            $new_links = array();
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
                  return response()->json(['code' => -1, 'message' => '请填写备注']);
              }
              if(!isset($value['if_use']) || !in_array($value['if_use'], [0,1])){
                  return response()->json(['code' => -1, 'message' => '请填写备注']);
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
              $tmp = array();
              $tmp['link_type'] = $value['link_type'] == 1 ? '分链接':'自适应';
              $tmp['pc_link'] = $value['pc_link'] ?? '';
              $tmp['wap_link'] = $value['wap_link'] ?? '';
              $tmp['zi_link'] = $value['zi_link'] ?? '';
              $tmp['link_name'] = $value['link_name'];
              $tmp['remarks'] = $value['remarks'];
              $tmp['pricing_manner'] = $value['pricing_manner'];
              $tmp['market_price'] = $value['market_price'];
              $tmp['if_use'] = $value['if_use'];
              $tmp['updated_at'] = date('Y-m-d H:i:s');
              $tmp['created_at'] = date('Y-m-d H:i:s');
              $new_links[] = $tmp;
            }
            $business_order_link = new \App\Models\BusinessOrderLink;
            $inputs['project_id'] = $link_info->project_id;
            $inputs['order_id'] = $link_info->order_id;
            $result = $business_order_link->addLink($inputs);
            if($result){
            	$link_info->status = 1;//已完成
            	$link_info->feedback = $inputs['feedback'];
            	$link_info->new_links = serialize($new_links);
            	$link_info->save();
                systemLog('链接管理', '填写了链接[链接申请]');
                addNotice($link_info->user_id, '链接申请', '您的链接申请已处理，请及时查看', '', 0, 'project-link-approval','links/list');
            	return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'reject'){
            //驳回
            $rules = [
              'feedback' => 'required|max:250',
            ];
            $attributes = [
              'feedback' => '反馈说明',
            ];

            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
              return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $link_info->status = 2;//驳回
            $link_info->feedback = $inputs['feedback'];
            $result = $link_info->save();
            if($result){
                systemLog('链接管理', '驳回了链接申请');
                addNotice($link_info->user_id, '链接申请', '您的链接申请已处理，请及时查看', '', 0, 'project-link-approval','links/list');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
		$data = array();
        $business_order = new \App\Models\BusinessOrder;
        $order_info =  $business_order->getOrderInfo(['id'=>$link_info->order_id]);
        $items = array();
        $items['id'] = $order_info->id;
        $items['customer_name'] = $order_info->hasCustomer->customer_name;
        $items['project_name'] = $order_info->project_name;
        $items['business'] = $user_data['id_realname'][$order_info->project_business];
            
        $links = array();
        if(!empty($link_info->old_links)){
          $links = unserialize($link_info->old_links);
          $links_items = [];
          foreach ($links as $key => $value) {
            $links_items[$key]['id'] = $value['id'];
            $links_items[$key]['link_type'] = $value['link_type'] == 1 ? '分链接' : '自定义';
            $links_items[$key]['link_name'] = $value['link_name'];
            $links_items[$key]['pc_link'] = $value['pc_link'];
            $links_items[$key]['wap_link'] = $value['wap_link'];
            $links_items[$key]['zi_link'] = $value['zi_link'];
            $links_items[$key]['remarks'] = $value['remarks'];
            $links_items[$key]['if_use'] = $value['if_use'];
            $links_items[$key]['project_name'] = $value['project_name'] ?? '--';
            $links_items[$key]['pricing_manner'] = $value['pricing_manner'];
            $links_items[$key]['market_price'] = $value['market_price'];
            $links_items[$key]['created_at'] = $value['created_at'];
            $links_items[$key]['updated_at'] = $value['updated_at'];
          }
          $links = $links_items;
        }
        $items['links'] = $links;
        $data['id'] = $inputs['id'];
        $data['order_info'] = $items;
        $apply_info = array();
        $apply_info['status'] = '未处理';
        $apply_info['degree'] = $link_info->degree_id == 1 ? '紧急':'一般';
        $apply_info['remarks'] = $link_info->remarks;
        $data['apply_info'] = $apply_info;
        $data['link_types'] = $business_order->link_types;
        $data['settlement_list'] = $business_order->settlement_lists;
		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
	}

    //导入链接
    public function import(){
        $file = request()->file('file');
        if ($file->isValid()) {
            $original_name = $file->getClientOriginalName(); // 文件原名
            $ext = $file->getClientOriginalExtension();  // 扩展名
            if (!in_array($ext, ['xls', 'xlsx'])) {
                return ['code' => -1, 'message' => '只能上传合法的Excel文件'];
            }
        }
        // 读取上传的Excel文件字段格式是否符合要求
        $file_name = $file->getPathname();
        if (!file_exists($file_name)) {
            return ['code' => 0, 'message' => '文件不存在，请检查'];
        }
        $sheets_data = importExcel($file_name, [0], 1);
        $first_sheet = $sheets_data['data']['sheets'];
        if (count($first_sheet) < 1) {
            return ['code' => 0, 'message' => '链接文档没有任何行信息，请检查'];
        }
        // 验证字段有效性
        $link = new \App\Models\BusinessOrderLink;
        $link_log = new \App\Models\ProjectLinkLog;
        $project = new \App\Models\BusinessProject;
        foreach ($first_sheet as $key => $val) {
            if(empty($val[0]) || $val[0] == 0) continue;
            $vdata = [
                'project_id' => trim($val[0]),
                'link_type' => trim($val[1]),
                'link_name' => trim($val[2]),
                'pc_link' => trim($val[3]),
                'wap_link' => trim($val[4]),
                'zi_link' => trim($val[5]),
                'pricing_manner' => trim($val[6]),
                'market_price' => trim($val[7]),
            ];
            $rules = [
                'project_id' => 'required|min:1',
                'link_type' => 'required',
                'link_name' => 'required|max:100',
                'pricing_manner' => 'required',
                'market_price' => 'required'
            ];
            $attributes = [
                'project_id' => '项目id',
                'link_type' => '链接类型',
                'link_name' => '链接名称',
                'pricing_manner' => '结算方式',
                'market_price' => '单价'
            ];
            $validator = validator($vdata, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if($link->where('link_name',$vdata['link_name'])->first()){
                return response()->json(['code' => 0, 'message' => '链接名称已存在，请勿重复导入']);
            }
            $link_insert = [];
            $link_insert['project_id'] = $vdata['project_id'];
            $link_insert['link_name'] = $vdata['link_name'];
            if(!in_array($vdata['link_type'], ['分链接','自适应'])){
                return response()->json(['code' => 0, 'message' => '链接类型只能是分链接或者自适应']);
            }
            if($vdata['link_type'] == '分链接'){
                if(empty($vdata['pc_link']) && empty($vdata['wap_link'])){
                    return response()->json(['code' => 0, 'message' => '当为分链接时，必须填写一条PC或WAP链接，请检查']);
                }
                if(!empty($vdata['pc_link']) && !empty($vdata['wap_link'])){
                    return response()->json(['code' => 0, 'message' => '当为分链接时，只能填写一条PC或WAP链接，请检查']);
                }
                $link_insert['link_type'] = 1;
                $link_insert['pc_link'] = $vdata['pc_link'];
                $link_insert['wap_link'] = $vdata['wap_link'];
                $link_insert['zi_link'] = '';
            }else{
                if(empty($vdata['zi_link'])){
                    return response()->json(['code' => 0, 'message' => '当为自适应时，必须填写一条自适应内容，请检查']);
                }
                $link_insert['link_type'] = 2;
                $link_insert['pc_link'] = '';
                $link_insert['wap_link'] = '';
                $link_insert['zi_link'] = $vdata['zi_link'];
            }
            $vdata['pricing_manner'] = strtoupper($vdata['pricing_manner']);//如果存在小写  着转换成大写
            if(!in_array($vdata['pricing_manner'], ['CPA','CPS','CPC','CPD','CPA+CPS'])){
                return response()->json(['code' => 0, 'message' => '结算方式不正确，请检查']);
            }
            $link_insert['pricing_manner'] = $vdata['pricing_manner'];
            if($vdata['pricing_manner'] == 'CPA+CPS'){
                $market_price = str_replace('，', ',', $vdata['market_price']);//如果有中文逗号  则替换成英文逗号
                $market_price = explode(',', $market_price);
                foreach ($market_price as $key => $value) {
                    if(!is_numeric($value)){
                        return response()->json(['code' => 0, 'message' => '项目id为'.$vdata['project_id'].'的单价设置错误，请检查']);
                    }
                }
                $link_insert['market_price'] = serialize(['CPA'=>$market_price[0],'CPS'=>$market_price[1]]);
            }else{
                if(!is_numeric($vdata['market_price'])){
                    return response()->json(['code' => 0, 'message' => '项目id为'.$vdata['project_id'].'的单价设置错误，请检查']);
                }
                $market_price = [$vdata['pricing_manner']=>$vdata['market_price']];
                $link_insert['market_price'] = serialize($market_price);
            }
            $project_info = $project->select(['order_id','created_at'])->where('id', $vdata['project_id'])->first();
            if(empty($project_info)){
                return response()->json(['code' => 0, 'message' => '系统中不存在项目id：'.$vdata['project_id']]);
            }
            $link_insert['order_id'] = $project_info->order_id;
            $link_insert['if_use'] = 1;
            $link_insert['created_at'] = $project_info->created_at->format('Y-m-d H:i:s');
            $link_insert['updated_at'] = date('Y-m-d H:i:s');
            //
            $link_id = $link->insertGetId($link_insert);
            $log_insert = [];
            $log_insert['link_id'] = $link_id;
            $log_insert['project_id'] = $vdata['project_id'];
            $log_insert['start_time'] = strtotime($project_info->created_at->format('Y-m-d H:i:s'));
            $log_insert['end_time'] = 0;
            $log_insert['created_at'] = date('Y-m-d H:i:s');
            $log_insert['updated_at'] = date('Y-m-d H:i:s');
            $log_id = $link_log->insertGetId($log_insert);
            $error_message = [];
            if(!$link_id || !$log_id){
                $error_message[] = '请检查项目id：'.$vdata['project_id'].'，链接名：'.$vdata['link_name'].'，这一行的格式是否正确';
            }
        }
        if(!empty($error_message)){
            return response()->json(['code' => 1, 'message' => implode(';', $error_message)]);
        }
        return response()->json(['code' => 1, 'message' => '全部导入成功']);
    }

}

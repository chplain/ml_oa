<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProjectIncomeController extends Controller
{
    /**
     *  项目收入-项目汇总
     * @author molin
     * @date 2019-03-13
     */
    public function index(){
    	$inputs = request()->all();
    	$income = new \App\Models\ProjectIncome;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $project = new \App\Models\BusinessProject;
            if(isset($inputs['type']) && $inputs['type'] == 'links'){
                //明细
                if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数id']);
                }
                
                $income_info = $income->getIncomeInfo(['id'=>$inputs['id']]);
                if (empty($income_info)) {
                    return response()->json(['code' => 0, 'message' => '数据不存在']);
                }
                //查出这个项目在这个月的所有链接
                $start_date = date('Y-m-01', strtotime($income_info->month.'-01'));
                $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
                //链接反馈表里面的链接
                $project_feedbacks = new \App\Models\ProjectFeedback;
                $project_feedbacks_list = $project_feedbacks->getFeedbackByProjectIdDay($income_info->project_id, $start_date, $end_date);
                $project_feedbacks_data = [];
                foreach ($project_feedbacks_list as $key => $value) {
                    $project_feedbacks_data['CPC']['amount'] = $project_feedbacks_data['CPC']['amount'] ?? 0;
                    $project_feedbacks_data['CPC']['money'] = $project_feedbacks_data['CPC']['money'] ?? 0;
                    if($value['cpc_amount'] > 0){
                        $project_feedbacks_data['CPC']['amount'] += $value['cpc_amount'];
                        $project_feedbacks_data['CPC']['money'] += $value['money'];
                    }
                    $project_feedbacks_data['CPD']['amount'] = $project_feedbacks_data['CPD']['amount'] ?? 0;
                    $project_feedbacks_data['CPD']['money'] = $project_feedbacks_data['CPD']['money'] ?? 0;
                    if($value['cpd_amount'] > 0){
                        $project_feedbacks_data['CPD']['amount'] += $value['cpd_amount'];
                        $project_feedbacks_data['CPD']['money'] += $value['money'];
                    }
                }
                $cpc_cpd_data = [];
                foreach (['CPC','CPD'] as $key => $value) {
                    $tmp = [];
                    $tmp['type'] = $value;
                    $tmp['amount'] = $project_feedbacks_data[$value]['amount'] ?? 0;
                    $tmp['money'] = isset($project_feedbacks_data[$value]['money']) ? sprintf('%.2f', $project_feedbacks_data[$value]['money']) : 0;
                    $cpc_cpd_data[] = $tmp;
                }

                $link_feedbacks = new \App\Models\LinkFeedback;
                $query_where = [];
                $query_where['project_id'] = $income_info->project_id;
                $query_where['start_time'] = $start_date;
                $query_where['end_time'] = $end_date;
                $data = $link_feedbacks->getLinkFeedbackByProjectId($query_where);
                $items = [];
                foreach ($data['datalist'] as $key => $value) {
                    $tmp = [];
                    $tmp['link_id'] = $value->link_id; 
                    $tmp['link_name'] = $value->hasLink->link_name; 
                    $tmp['CPA'] = 0;
                    $tmp['CPS'] = 0;
                    $pricing_manner = $amount = [];
                    $tmp['pricing_manner'] = $value->hasLink->pricing_manner; 
                    if($value->cpa_price > 0){
                        $pricing_manner[] = 'CPA';
                        $tmp['CPA'] += $value->cpa_amount;
                    }
                    if($value->cps_price > 0){
                        $pricing_manner[] = 'CPS';
                        $tmp['CPS'] += $value->cps_amount;
                    }
                    if(count($pricing_manner) > 1){
                        $tmp['pricing_manner'] .= '(多种结算方式)'; 
                    }
                    $tmp['income'] = $value->money; 
                    $items[] = $tmp;
                }
                $data['id'] = $inputs['id'];
                $data['datalist'] = $items;
                $data['CPC/CPD'] = $cpc_cpd_data;
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            if(isset($inputs['type']) && $inputs['type'] == 'cpc_cpd_detail'){
                //当前项目在这个月的cpc/cpd明细
                if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数id']);
                }
                if(!isset($inputs['model']) || !in_array($inputs['model'], ['CPC','CPD'])){
                    return response()->json(['code' => -1, 'message' => '请传入model[CPC/CPD]']);
                }
                $income_info = $income->getIncomeInfo(['id'=>$inputs['id']]);
                if (empty($income_info)) {
                    return response()->json(['code' => 0, 'message' => '数据不存在']);
                }
                //查出这个项目在这个月的所有链接
                $start_date = date('Y-m-01', strtotime($income_info->month.'-01'));
                $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
                //链接反馈表里面的链接
                $project_feedbacks = new \App\Models\ProjectFeedback;
                $project_feedbacks_list = $project_feedbacks->getFeedbackByProjectIdDay($income_info->project_id, $start_date, $end_date);
                $project_feedbacks_data = [];
                foreach ($project_feedbacks_list as $key => $value) {
                    $project_feedbacks_data[$value['date']]['cpc_price'] = $value['cpc_price'] > 0 ? $value['cpc_price'] : 0;
                    $project_feedbacks_data[$value['date']]['cpd_price'] = $value['cpd_price'] > 0 ? $value['cpd_price'] : 0;
                    $project_feedbacks_data[$value['date']]['cpc_amount'] = $value['cpc_amount'];
                    $project_feedbacks_data[$value['date']]['cpd_amount'] = $value['cpd_amount'];
                }
                $days = prDates($start_date, $end_date);
                $data = [];
                foreach ($days as $d) {
                    $tmp = [];
                    if($inputs['model'] == 'CPC'){
                        $tmp['price'] = '单价：CPC-'.($project_feedbacks_data[$d]['cpc_price'] ?? 0);
                        $tmp['amount'] = '反馈：CPC:'.($project_feedbacks_data[$d]['cpc_amount'] ?? 0);
                    }elseif($inputs['model'] == 'CPD'){
                        $tmp['price'] = '单价：CPD-'.($project_feedbacks_data[$d]['cpd_price'] ?? 0);
                        $tmp['amount'] = '反馈：CPD:'.($project_feedbacks_data[$d]['cpd_amount'] ?? 0);
                    }else{
                        $tmp['price'] = '单价：0';
                        $tmp['amount'] = '反馈：0';
                    }
                    $data[$d] = $tmp;
                }
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            if(isset($inputs['type']) && $inputs['type'] == 'detail'){
            	//明细
            	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            		return response()->json(['code' => -1, 'message' => '缺少参数id']);
            	}
                if(!isset($inputs['link_id']) || !is_numeric($inputs['link_id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数link_id']);
                }
            	
            	$income_info = $income->getIncomeInfo(['id'=>$inputs['id']]);
            	if (empty($income_info)) {
	            	return response()->json(['code' => 0, 'message' => '数据不存在']);
	            }
            	//查出这个月的所有价格
	            $start_date = date('Y-m-01', strtotime($income_info->month.'-01'));
	            $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
	            $price_log = new \App\Models\BusinessOrderPrice;//单价更改记录
	            $inputs['link_id'] = $inputs['link_id'];
	            $inputs['start_date'] = strtotime($start_date.' 00:00:00');
                $inputs['end_date'] = strtotime($end_date.' 23:59:59');
                $inputs['all'] = 1;
	            $price_list = $price_log->getDataList($inputs);
                $items = [];
                if(!empty($price_list['datalist']->toArray())){
                    foreach ($price_list['datalist'] as $key => $value) {
                        $tmp = [];
                        $tmp['opt_time'] = $value->created_at->format('Y-m-d H:i:s');
                        if($value->start_time > 0 && $value->end_time > 0){
                            $tmp['date'] = date('Y-m-d',$value->start_time).'~'.date('Y-m-d', $value->end_time);
                        }else{
                            $tmp['date'] = '即时生效';
                        }
                        $tmp['realname'] = $user_data['id_realname'][$value->user_id];
                        $tmp['old_market_price'] = $value->old_market_price ? unserialize($value->old_market_price) : '--';
                        $tmp['market_price'] = $value->market_price ? unserialize($value->market_price) : '--';
                        $items[] = $tmp;
                    }
                    $price_list['datalist'] = $items;
                }else{
                    $price_list = [];
                    $price_list['records_total'] = 1;
                    $price_list['records_filtered'] = 1;
                    //无价格变更记录
                    $link_info = (new \App\Models\BusinessOrderLink)->where('id', $inputs['link_id'])->first();
                    if(empty($link_info)){
                        return response()->json(['code' => 0, 'message' => '没有该链接信息']);
                    }
                    $tmp = [];
                    $tmp['opt_time'] = $link_info->created_at->format('Y-m-d H:i:s');
                    $tmp['date'] = '即时生效';
                    $tmp['realname'] = '--';
                    $tmp['old_market_price'] = '--';
                    $tmp['market_price'] = unserialize($link_info->market_price);
                    $items[] = $tmp;
                    $price_list['datalist'] = $items;
                }
	            $data['price_list'] = $price_list;
	            
	            //每一天的反馈
	            $link_feedbacks = new \App\Models\LinkFeedback;
                $link_where = [];
                $link_where['project_id'] = $income_info->project_id;
                $link_where['link_id'] = $inputs['link_id'];
                $link_where['start_time'] = $start_date;
                $link_where['end_time'] = $end_date;
	            $link_feedback_list = $link_feedbacks->getLinkFeedbackByLinkId($link_where);
                $link_feedback_data = [];
                foreach ($link_feedback_list as $key => $value) {
                    $link_feedback_data[$value->date]['cpa_price'] = $value->cpa_price;
                    $link_feedback_data[$value->date]['cpa_amount'] = $value->cpa_amount;
                    $link_feedback_data[$value->date]['cps_price'] = $value->cps_price;
                    $link_feedback_data[$value->date]['cps_amount'] = $value->cps_amount;
                    $link_feedback_data[$value->date]['money'] = $value->money;
                }

	            $days = prDates($start_date, $end_date);
                $items = [];
	            foreach ($days as $d) {
	                $tmp = [];
                    $price = [];
                    if(isset($link_feedback_data[$d]['cpa_price']) && $link_feedback_data[$d]['cpa_price'] > 0){
                        $price[] = 'CPA-'.$link_feedback_data[$d]['cpa_price'];
                    }
                    if(isset($link_feedback_data[$d]['cps_price']) && $link_feedback_data[$d]['cps_price'] > 0){
                        $price[] = 'CPS-'.$link_feedback_data[$d]['cps_price'];
                    }
	                $tmp['price'] = !empty($price) ? implode(' ', $price) : '--';
                    $amount = [];
                    if(isset($link_feedback_data[$d]['cpa_amount']) && $link_feedback_data[$d]['cpa_amount'] > 0){
                        $amount[] = 'CPA:'.$link_feedback_data[$d]['cpa_amount'];
                    }
                    if(isset($link_feedback_data[$d]['cps_amount']) && $link_feedback_data[$d]['cps_amount'] > 0){
                        $amount[] = 'CPS:'.$link_feedback_data[$d]['cps_amount'];
                    }
                    $tmp['amount'] = !empty($amount) ? implode(' ', $amount) : '--';
	                $items[$d] = $tmp;
	            }
	            $data['feedback_info'] = $items;
	            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数project_id']);
            }
            if($inputs['project_id'] == 0){
                //新增记录-查看详情
                if(!isset($inputs['income_id']) || !is_numeric($inputs['income_id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数income_id']);
                }
                $income_info = $income->find($inputs['income_id']);
                $data = $income_detail = [];
                $income_detail['income_id'] = $income_info->id;
                $income_detail['project_name'] = $income_info->project_name;
                $income_detail['customer_name'] = $income_info->customer_name;
                $income_detail['sale_man'] = $income_info->sale_man;
                $income_detail['charge'] = $income_info->charge;
                $income_detail['business'] = $income_info->business;
                $income_detail['trade_name'] = $income_info->trade_name;
                $income_detail['resource'] = $income_info->resource;
                $income_detail['income_main'] = $income_info->income_main;
                $income_detail['month'] = $income_info->month;
                $income_detail['succ_count'] = $income_info->succ_count;
                $income_detail['all_income'] = $income_info->all_income;
                $income_detail['danfeng'] = sprintf('%.4f', ($income_info->all_income/$income_info->succ_count));//单封 = 收入/实际到达数
                
                $data['income_info'] = $income_detail;
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            $project_info = $project->getProjectInfo(['id'=>$inputs['project_id']]);
            if (empty($project_info)) {
            	return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $income_list = $income->getIncomeList($inputs);
            $data = array();
            $base_info = array();
            $base_info['sale_man'] = $user_data['id_realname'][$project_info->sale_man];
            $base_info['charge'] = $user_data['id_realname'][$project_info->charge_id];
            $base_info['business'] = $user_data['id_realname'][$project_info->business_id];
            $data['base_info'] = $base_info;
            $income_list_list = array();
            foreach ($income_list['datalist'] as $key => $value) {
            	$tmp = array();
            	$tmp['id'] = $value->id;
            	$tmp['month'] = $value->month;
                if($value->if_invoice == 1){
                    $tmp['if_invoice'] = '部分已开票';
                }elseif($value->if_invoice == 2){
                    $tmp['if_invoice'] = '已开票';
                }else{
                    $tmp['if_invoice'] = '未开票';
                }
            	$tmp['succ_count'] = $value->succ_count;
            	$tmp['real_income'] = $value->real_income;
            	$tmp['all_income'] = $value->all_income;
            	$tmp['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
            	$tmp['invoice_date'] = $value->invoice_date ? $value->invoice_date : '--';
            	$tmp['arrival_date'] = $value->arrival_date ? $value->arrival_date : '--';
            	$tmp['business'] = $value->business;
            	$income_list_list[] = $tmp;
            }
            $data['income_list_list'] = $income_list_list;
            
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'remarks'){
            //备注
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            if(!isset($inputs['remarks']) || empty($inputs['remarks'])){
                return response()->json(['code' => -1, 'message' => '缺少参数remarks']);
            }
            $income_info = $income->getIncomeInfo($inputs);
            if (empty($income_info)) {
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $income_info->remarks = $inputs['remarks'];
            $result = $income_info->save();
            if($result){
                systemLog('收入到账表', '更新了备注-'.$inputs['id']);
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'all_income'){
            //执行确认金额
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            if(!isset($inputs['all_income']) || empty($inputs['all_income'])){
                return response()->json(['code' => -1, 'message' => '缺少参数all_income']);
            }
            $income_info = $income->getIncomeInfo($inputs);
            if (empty($income_info)) {
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            if($income_info->if_edit == 2){
                return response()->json(['code' => 0, 'message' => '已经申请开票，无法修改金额']);
            }
            $income_info->all_income = $inputs['all_income'];
            $result = $income_info->save();
            if($result){
                systemLog('收入到账表', '更新了执行确认金额-'.$inputs['id'].'['.$inputs['all_income'].']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit_load'){
            //编辑加载
            if(!isset($inputs['income_id']) || !is_numeric($inputs['income_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数income_id']);
            }
            $income_info = $income->find($inputs['income_id']);
            if(empty($income_info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }   
            if($income_info->project_id > 0){
                return response()->json(['code' => 0, 'message' => '不能编辑']);
            }
            $customer = new \App\Models\BusinessOrderCustomer;
            $user = new \App\Models\User;
            $trade = new \App\Models\Trade;
            $order = new \App\Models\BusinessOrder;
            $data = $income_detail = [];
            $income_detail['income_id'] = $income_info->id;
            $income_detail['project_name'] = $income_info->project_name;
            $income_detail['customer_id'] = $income_info->customer_id;
            $income_detail['sale_man'] = $income_info->sale_user_id;
            $income_detail['charge_id'] = $income_info->charge_id;
            $income_detail['business_id'] = $income_info->business_id;
            $income_detail['trade_id'] = $income_info->trade_id;
            foreach($order->resource_types as $val){
                if($income_info->resource == $val['name']){
                    $income_detail['resource'] = $val['id'];
                }
            }
            foreach($order->income_main_types as $val){
                if($income_info->income_main == $val['name']){
                    $income_detail['income_main'] = $val['id'];
                }
            }
            $income_detail['month'] = $income_info->month;
            $income_detail['succ_count'] = $income_info->succ_count;
            $income_detail['all_income'] = $income_info->all_income;
            
            $data['income_info'] = $income_detail;
            $data['customer_list'] = $customer->select(['id','customer_name'])->get();
            $data['user_list'] = $user->where('status', 1)->select(['id','realname'])->get();
            $data['trade_list'] = $trade->select(['id', 'name'])->get();
            $data['resource'] = $order->resource_types;
            $data['income_main'] = $order->income_main_types;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit_save'){
            //编辑保存
            $rules = [
                'income_id' => 'required|integer',
                'project_name' => 'required|max:100',
                'customer_id' => 'required|integer',
                'sale_man' => 'required|integer',
                'trade_id' => 'required|integer',
                'resource' => 'required|integer',
                'income_main' => 'required|integer',
                'charge_id' => 'required|integer',
                'business_id' => 'required|integer',
                'month' => 'required|date_format:Y-m',
                'succ_count' => 'required|integer',
                'all_income' => 'required|numeric',
            ];
            $attributes = [
                'income_id' => 'income_id',
                'project_name' => '项目名称',
                'customer_id' => '客户',
                'sale_man' => '销售',
                'trade_id' => '行业',
                'resource' => '资源类型',
                'income_main' => '主体',
                'charge_id' => '项目负责人',
                'business_id' => '商务',
                'month' => '月份',
                'succ_count' => '实际到达数',
                'all_income' => '执行确认',
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if($inputs['month'] == date('Y-m')){
                return response()->json(['code' => 0, 'message' => '不能提交这个月的记录']);
            }
            $if_exist = $income->where('project_name', $inputs['project_name'])->where('month', $inputs['month'])->first();
            if(!empty($if_exist)) return response()->json(['code' => 0, 'message' => '已存在']);
            $income_info = $income->find($inputs['income_id']);
            if(empty($income_info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }   
            if($income_info->project_id > 0){
                return response()->json(['code' => 0, 'message' => '不能编辑']);
            }
            if($income_info->if_edit == 2){
                return response()->json(['code' => 0, 'message' => '已经申请开票,不能修改']);
            }
            $customer = new \App\Models\BusinessOrderCustomer;
            $user = new \App\Models\User;
            $trade = new \App\Models\Trade;
            $order = new \App\Models\BusinessOrder;

            $user_data = $user->getIdToData();
            $income_info->user_id = auth()->user()->id;
            $income_info->customer_id = $inputs['customer_id'];
            $income_info->project_name = $inputs['project_name'];
            $income_info->month = $inputs['month'];
            $income_info->succ_count = $inputs['succ_count'];
            $income_info->all_income = $inputs['all_income'];
            $income_info->trade_id = $inputs['trade_id'];
            $income_info->trade_name = $trade->find($inputs['trade_id'],['name'])->name;
            $income_info->customer_name = $customer->find($inputs['customer_id'],['customer_name'])->customer_name;
            $income_info->sale_user_id = $inputs['sale_man'];
            $income_info->sale_man = $user_data['id_realname'][$inputs['sale_man']];
            $income_info->business_id = $inputs['business_id'];
            $income_info->business = $user_data['id_realname'][$inputs['business_id']];
            $income_info->charge_id = $inputs['charge_id'];
            $income_info->charge = $user_data['id_realname'][$inputs['charge_id']];
            foreach ($order->resource_types as $key => $value) {
                if($value['id'] == $inputs['resource']){
                    $income_info->resource = $value['name'];
                }
            }
            foreach ($order->income_main_types as $key => $value) {
                if($value['id'] == $inputs['income_main']){
                    $income_info->income_main = $value['name'];
                }
            }

            $result = $income_info->save();
            if($result){
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'invoice'){
            //开票记录
            if(!isset($inputs['income_id']) || !is_numeric($inputs['income_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数income_id']);
            }
            $data = [];
            $income_info = $income->getIncomeInfo(['id'=>$inputs['income_id']]);
            if (empty($income_info)) {
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $income_invoice_list = (new \App\Models\ProjectIncomeInvoice)->getInvoiceListByIncomeId($inputs);
            $invoice_types = $invoice_contents = [];
            foreach ($income->invoice_type as $key => $value) {
                $invoice_types[$value['id']] = $value['name'];
            }
            foreach ($income->invoice_content as $key => $value) {
                $invoice_contents[$value['id']] = $value['name'];
            }
            $items = [];
            foreach ($income_invoice_list as $key => $value) {
                $tmp = [];
                $tmp['apply_time'] = $value->created_at->format('Y-m-d H:i');
                $tmp['real_income'] = $income_info->real_income;
                $tmp['invoice_money'] = $value->money;
                $tmp['invoice_date'] = $value->invoice_date ? date('Y-m-d H:i',strtotime($value->invoice_date)) : '--';
                if($value->status == 1){
                    $tmp['status_txt'] = '已开票未到账';
                }elseif($value->status == 2){
                    $tmp['status_txt'] = '已开票已到账';
                }elseif($value->status == 3){
                    $tmp['status_txt'] = '已作废';
                }elseif($value->status == 4){
                    $tmp['status_txt'] = '已撤回';
                }else{
                    $tmp['status_txt'] = '未开票';
                }
                $tmp['arrival_date'] = $value->arrival_date ? $value->arrival_date : '--';
                $tmp['arrival_bank'] = $value->arrival_bank ? $value->arrival_bank : '--';
                $tmp2 = [];
                $tmp2['company'] = $value->hasInvoice->company;
                $tmp2['invoice_type'] = $invoice_types[$value->hasInvoice->invoice_type];
                $tmp2['invoice_content'] = $invoice_contents[$value->hasInvoice->invoice_content];
                $tmp2['taxpayer'] = $value->hasInvoice->taxpayer;
                $tmp2['address'] = $value->hasInvoice->address;
                $tmp2['tel'] = $value->hasInvoice->tel;
                $tmp2['bank'] = $value->hasInvoice->bank;
                $tmp2['bank_account'] = $value->hasInvoice->bank_account;
                $tmp['invoice_info'] = $tmp2;
                $items[] = $tmp;
            }
            $data['income_invoice_list'] = $items;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

        if(isset($inputs['export']) && $inputs['export'] == 1){
            //导出
            $inputs['all'] = 1;
        }
    	$data = $income->getIncomeList($inputs);
    	$items = $export_body = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$tmp = array();
    		$tmp['id'] = $value->id;
    		$tmp['project_id'] = $value->project_id;
    		$tmp['customer_name'] = $export_body[$key]['customer_name'] = $value->customer_name;
    		$tmp['project_name'] = $export_body[$key]['project_name'] = $value->project_name;
            $tmp['sale_man'] = $export_body[$key]['sale_man'] = $value->sale_man;
            $tmp['trade_name'] = $export_body[$key]['trade_name'] = $value->trade_name;
            $tmp['income_main'] = $export_body[$key]['income_main'] = $value->income_main;
            $tmp['resource'] = $export_body[$key]['resource'] = $value->resource;
    		$tmp['month'] = $export_body[$key]['month'] = $value->month;
            $tmp['succ_count'] = $export_body[$key]['succ_count'] = $value->succ_count;
            $tmp['danfeng'] = $export_body[$key]['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
            $tmp['all_income'] = $export_body[$key]['all_income'] = $value->all_income;
            $tmp['real_income'] = $export_body[$key]['real_income'] = $value->real_income;
            $tmp['invoice_money'] = $export_body[$key]['invoice_money'] = $value->invoice_money;
            if($value->if_invoice == 2){
                $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '已开票';
            }elseif($value->if_invoice == 1){
                $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '部分已开票';
            }else{
                if(!empty($value->delay_remarks)){
                    $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '延迟开票';
                }else{
                    $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '未开票';
                }
            }
            
    		$tmp['arrival_date'] = $value->arrival_date ? $value->arrival_date : '/';
            $tmp['arrival_amount'] = abs($value->arrival_amount) ? $value->arrival_amount : '/';//到账金额
            $tmp['arrival_bank'] = $value->arrival_bank ? $value->arrival_bank : '/';//收支银行
    		$tmp['invoice_date'] = $value->invoice_date ? $value->invoice_date:'/';
            if($tmp['arrival_amount'] > 0 && $tmp['arrival_amount'] < $tmp['invoice_money']){
                $tmp['if_finance'] = '部分已到账';
            }else{
                $tmp['if_finance'] = $value->if_finance ? '已确认':'未确认';
            }
            $export_body[$key]['if_finance'] = $tmp['if_finance'];
            $export_body[$key]['arrival_date'] = $tmp['arrival_date'];
            $export_body[$key]['arrival_amount'] = $tmp['arrival_amount'];
            $export_body[$key]['arrival_bank'] = $tmp['arrival_bank'];
            $export_body[$key]['invoice_date'] = $tmp['invoice_date'];
            if(empty($value->hasIncomeInvoice->toArray())){
                $tmp['arrival_content'] = [];
            }else{
                $arrival_date_content = [];
                $arrival_amount_content = [];
                $arrival_bank_content = [];
                $invoice_date_content = [];
                $arrival_content = [];
                foreach ($value->hasIncomeInvoice as $val) {
                    $arrival_date_content[] = $val->arrival_date ? $val->arrival_date : '/';
                    $arrival_amount_content[] = $val->arrival_money ? $val->arrival_money : '/';
                    $arrival_bank_content[] = $val->arrival_bank ? $val->arrival_bank : '/';
                    $invoice_date_content[] = $val->invoice_date ? $val->invoice_date : '/';
                    $con = [];
                    $con['arrival_date_content'] = $val->arrival_date ? $val->arrival_date : '/';
                    $con['arrival_amount_content'] = $val->arrival_money ? $val->arrival_money : '/';
                    $con['arrival_bank_content'] = $val->arrival_bank ? $val->arrival_bank : '/';
                    $con['invoice_date_content'] = $val->invoice_date ? $val->invoice_date : '/';
                    $arrival_content[] = $con;
                }
                $tmp['arrival_content'] = $arrival_content;
                $export_body[$key]['arrival_date'] = implode(',', $arrival_date_content);
                $export_body[$key]['arrival_amount'] = implode(',', $arrival_amount_content);
                $export_body[$key]['arrival_bank'] = implode(',', $arrival_bank_content);
                $export_body[$key]['invoice_date'] = implode(',', $invoice_date_content);
            }
            $export_body[$key]['remarks'] = ($value->remarks ? '备注：'.$value->remarks : '') . ($value->delay_remarks ? '延迟说明：'.$value->delay_remarks : '');
            $tmp['charge'] = $value->charge;
            $tmp['remarks'] = $value->remarks;
            $tmp['delay_remarks'] = $value->delay_remarks;
            $tmp['if_edit'] = 2;
            $tmp['if_del'] = $value->if_edit;
            if($value->project_id == 0){
                //新增记录部分（手动录入）
                $tmp['if_edit'] = $value->if_edit;//是否能编辑   1可以编辑  2不可以编辑(申请开票就不能编辑了)
            }
            $tmp['if_has_record'] = 0;
            if($value->if_invoice > 0){
                $tmp['if_has_record'] = 1;
            }
    		$items[] = $tmp;
    	}
        if(isset($inputs['export']) && $inputs['export'] == 1){
            //导出
            set_time_limit(0);
            $export_head = ['customer_name'=>'客户名称','project_name'=>'项目名称','sale_man'=>'销售','trade_name'=>'行业','income_main'=>'收入主体','resource'=>'资源','month'=>'月份','succ_count'=>'实际到达数','danfeng'=>'单封','all_income'=>'执行确认','real_income'=>'商务确认','invoice_money'=>'开票金额','if_invoice'=>'发票情况','if_finance'=>'财务情况','arrival_date'=>'到帐日期','arrival_amount'=>'到账金额','arrival_bank'=>'收支银行','invoice_date'=>'开票日期','remarks'=>'备注'];
            $filedata = pExprot($export_head, $export_body, 'income');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        }
    	$data['datalist'] = $items;
    	$data['trade_list'] = (new \App\Models\Trade)->select(['id','name'])->get();
    	$data['if_invoice'] = [['id'=>0,'name'=>'未开票'],['id'=>1,'name'=>'部分已开票'],['id'=>2,'name'=>'已开票']];
        $resource_types = $income_main_types = [];
        foreach ((new \App\Models\BusinessOrder)->resource_types as $key => $value) {
            $resource_types[$value['name']] = $value['name'];
        }
        foreach ((new \App\Models\BusinessOrder)->income_main_types as $key => $value) {
            $income_main_types[$value['name']] = $value['name'];
        }
        $data['resource_type'] = $resource_types;
        $data['income_main_types'] = $income_main_types;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     *  项目收入-项目汇总-新增记录
     * @author molin
     * @date 2019-06-22
     */
    public function add(){
        $inputs = request()->all();
        $data = [];
        $customer = new \App\Models\BusinessOrderCustomer;
        $user = new \App\Models\User;
        $trade = new \App\Models\Trade;
        $order = new \App\Models\BusinessOrder;
        $income = new \App\Models\ProjectIncome;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
            //保存数据
            $rules = [
                'project_name' => 'required|max:100',
                'customer_id' => 'required|integer',
                'sale_man' => 'required|integer',
                'trade_id' => 'required|integer',
                'resource' => 'required|integer',
                'income_main' => 'required|integer',
                'charge_id' => 'required|integer',
                'business_id' => 'required|integer',
                'month' => 'required|date_format:Y-m',
                'succ_count' => 'required|integer',
                'all_income' => 'required|numeric',
            ];
            $attributes = [
                'project_name' => '项目名称',
                'customer_id' => '客户',
                'sale_man' => '销售',
                'trade_id' => '行业',
                'resource' => '资源类型',
                'income_main' => '主体',
                'charge_id' => '项目负责人',
                'business_id' => '商务',
                'month' => '月份',
                'succ_count' => '实际到达数',
                'all_income' => '执行确认',
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if($inputs['month'] == date('Y-m')){
                return response()->json(['code' => 0, 'message' => '不能提交这个月的记录']);
            }
            $if_exist = $income->where('project_name', $inputs['project_name'])->where('month', $inputs['month'])->first();
            if(!empty($if_exist)) return response()->json(['code' => 0, 'message' => '已存在']);
            $user_data = $user->getIdToData();
            $income->user_id = auth()->user()->id;
            $income->order_id = 0;
            $income->customer_id = $inputs['customer_id'];
            $income->project_id = 0;
            $income->project_name = $inputs['project_name'];
            $income->month = $inputs['month'];
            $income->send_count = 0;
            $income->succ_count = $inputs['succ_count'];
            $income->real_income = 0;
            $income->all_income = $inputs['all_income'];
            $income->trade_id = $inputs['trade_id'];
            $income->trade_name = $trade->find($inputs['trade_id'],['name'])->name;
            $income->customer_name = $customer->find($inputs['customer_id'],['customer_name'])->customer_name;
            $income->sale_user_id = $inputs['sale_man'];
            $income->sale_man = $user_data['id_realname'][$inputs['sale_man']];
            $income->business_id = $inputs['business_id'];
            $income->business = $user_data['id_realname'][$inputs['business_id']];
            $income->charge_id = $inputs['charge_id'];
            $income->charge = $user_data['id_realname'][$inputs['charge_id']];
            foreach ($order->resource_types as $key => $value) {
                if($value['id'] == $inputs['resource']){
                    $income->resource = $value['name'];
                }
            }
            foreach ($order->income_main_types as $key => $value) {
                if($value['id'] == $inputs['income_main']){
                    $income->income_main = $value['name'];
                }
            }

            $result = $income->save();
            if($result){
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);

        }
        $data['customer_list'] = $customer->select(['id','customer_name'])->get();
        $data['user_list'] = $user->where('status', 1)->select(['id','realname'])->get();
        $data['trade_list'] = $trade->select(['id', 'name'])->get();
        $data['resource'] = $order->resource_types;
        $data['income_main'] = $order->income_main_types;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }
    /**
     *  我的项目收入到账表
     * @author molin
     * @date 2019-03-14
     */
    public function mine(){
    	$inputs = request()->all();
    	$inputs['business_id'] = auth()->user()->id;
    	$income = new \App\Models\ProjectIncome;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $project = new \App\Models\BusinessProject;
            if(isset($inputs['type']) && $inputs['type'] == 'links'){
                //明细
                if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数id']);
                }
                
                $income_info = $income->getIncomeInfo(['id'=>$inputs['id']]);
                if (empty($income_info)) {
                    return response()->json(['code' => 0, 'message' => '数据不存在']);
                }
                //查出这个项目在这个月的所有链接
                $start_date = date('Y-m-01', strtotime($income_info->month.'-01'));
                $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
                //链接反馈表里面的链接
                $project_feedbacks = new \App\Models\ProjectFeedback;
                $project_feedbacks_list = $project_feedbacks->getFeedbackByProjectIdDay($income_info->project_id, $start_date, $end_date);
                $project_feedbacks_data = [];
                foreach ($project_feedbacks_list as $key => $value) {
                    $project_feedbacks_data['CPC']['amount'] = $project_feedbacks_data['CPC']['amount'] ?? 0;
                    $project_feedbacks_data['CPC']['money'] = $project_feedbacks_data['CPC']['money'] ?? 0;
                    if($value['cpc_amount'] > 0){
                        $project_feedbacks_data['CPC']['amount'] += $value['cpc_amount'];
                        $project_feedbacks_data['CPC']['money'] += $value['money'];
                    }
                    $project_feedbacks_data['CPD']['amount'] = $project_feedbacks_data['CPD']['amount'] ?? 0;
                    $project_feedbacks_data['CPD']['money'] = $project_feedbacks_data['CPD']['money'] ?? 0;
                    if($value['cpd_amount'] > 0){
                        $project_feedbacks_data['CPD']['amount'] += $value['cpd_amount'];
                        $project_feedbacks_data['CPD']['money'] += $value['money'];
                    }
                }
                $cpc_cpd_data = [];
                foreach (['CPC','CPD'] as $key => $value) {
                    $tmp = [];
                    $tmp['type'] = $value;
                    $tmp['amount'] = $project_feedbacks_data[$value]['amount'] ?? 0;
                    $tmp['money'] = isset($project_feedbacks_data[$value]['money']) ? sprintf('%.2f', $project_feedbacks_data[$value]['money']) : 0;
                    $cpc_cpd_data[] = $tmp;
                }

                $link_feedbacks = new \App\Models\LinkFeedback;
                $query_where = [];
                $query_where['project_id'] = $income_info->project_id;
                $query_where['start_time'] = $start_date;
                $query_where['end_time'] = $end_date;
                $data = $link_feedbacks->getLinkFeedbackByProjectId($query_where);
                $items = [];
                foreach ($data['datalist'] as $key => $value) {
                    $tmp = [];
                    $tmp['link_id'] = $value->link_id; 
                    $tmp['link_name'] = $value->hasLink->link_name; 
                    $tmp['CPA'] = 0;
                    $tmp['CPS'] = 0;
                    $pricing_manner = $amount = [];
                    $tmp['pricing_manner'] = $value->hasLink->pricing_manner; 
                    if($value->cpa_price > 0){
                        $pricing_manner[] = 'CPA';
                        $tmp['CPA'] += $value->cpa_amount;
                    }
                    if($value->cps_price > 0){
                        $pricing_manner[] = 'CPS';
                        $tmp['CPS'] += $value->cps_amount;
                    }
                    if(count($pricing_manner) > 1){
                        $tmp['pricing_manner'] .= '(多种结算方式)'; 
                    }
                    $tmp['income'] = $value->money; 
                    $items[] = $tmp;
                }
                $data['id'] = $inputs['id'];
                $data['datalist'] = $items;
                $data['CPC/CPD'] = $cpc_cpd_data;
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            if(isset($inputs['type']) && $inputs['type'] == 'cpc_cpd_detail'){
                //当前项目在这个月的cpc/cpd明细
                if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数id']);
                }
                if(!isset($inputs['model']) || !in_array($inputs['model'], ['CPC','CPD'])){
                    return response()->json(['code' => -1, 'message' => '请传入model[CPC/CPD]']);
                }
                $income_info = $income->getIncomeInfo(['id'=>$inputs['id']]);
                if (empty($income_info)) {
                    return response()->json(['code' => 0, 'message' => '数据不存在']);
                }
                //查出这个项目在这个月的所有链接
                $start_date = date('Y-m-01', strtotime($income_info->month.'-01'));
                $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
                //链接反馈表里面的链接
                $project_feedbacks = new \App\Models\ProjectFeedback;
                $project_feedbacks_list = $project_feedbacks->getFeedbackByProjectIdDay($income_info->project_id, $start_date, $end_date);
                $project_feedbacks_data = [];
                foreach ($project_feedbacks_list as $key => $value) {
                    $project_feedbacks_data[$value['date']]['cpc_price'] = $value['cpc_price'] > 0 ? $value['cpc_price'] : 0;
                    $project_feedbacks_data[$value['date']]['cpd_price'] = $value['cpd_price'] > 0 ? $value['cpd_price'] : 0;
                    $project_feedbacks_data[$value['date']]['cpc_amount'] = $value['cpc_amount'];
                    $project_feedbacks_data[$value['date']]['cpd_amount'] = $value['cpd_amount'];
                }
                $days = prDates($start_date, $end_date);
                $data = [];
                foreach ($days as $d) {
                    $tmp = [];
                    if($inputs['model'] == 'CPC'){
                        $tmp['price'] = '单价：CPC-'.($project_feedbacks_data[$d]['cpc_price'] ?? 0);
                        $tmp['amount'] = '反馈：CPC:'.($project_feedbacks_data[$d]['cpc_amount'] ?? 0);
                    }elseif($inputs['model'] == 'CPD'){
                        $tmp['price'] = '单价：CPD-'.($project_feedbacks_data[$d]['cpd_price'] ?? 0);
                        $tmp['amount'] = '反馈：CPD:'.($project_feedbacks_data[$d]['cpd_amount'] ?? 0);
                    }else{
                        $tmp['price'] = '单价：0';
                        $tmp['amount'] = '反馈：0';
                    }
                    $data[$d] = $tmp;
                }
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            if(isset($inputs['type']) && $inputs['type'] == 'detail'){
                //明细
                if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数id']);
                }
                if(!isset($inputs['link_id']) || !is_numeric($inputs['link_id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数link_id']);
                }
                
                $income_info = $income->getIncomeInfo(['id'=>$inputs['id']]);
                if (empty($income_info)) {
                    return response()->json(['code' => 0, 'message' => '数据不存在']);
                }
                //查出这个月的所有价格
                $start_date = date('Y-m-01', strtotime($income_info->month.'-01'));
                $end_date = date('Y-m-d', strtotime("$start_date +1 month -1 day"));
                $price_log = new \App\Models\BusinessOrderPrice;//单价更改记录
                $inputs['link_id'] = $inputs['link_id'];
                $inputs['start_date'] = strtotime($start_date.' 00:00:00');
                $inputs['end_date'] = strtotime($end_date.' 23:59:59');
                $inputs['all'] = 1;
                $price_list = $price_log->getDataList($inputs);
                $items = [];
                if(!empty($price_list['datalist']->toArray())){
                    foreach ($price_list['datalist'] as $key => $value) {
                        $tmp = [];
                        $tmp['opt_time'] = $value->created_at->format('Y-m-d H:i:s');
                        if($value->start_time > 0 && $value->end_time > 0){
                            $tmp['date'] = date('Y-m-d',$value->start_time).'~'.date('Y-m-d', $value->end_time);
                        }else{
                            $tmp['date'] = '即时生效';
                        }
                        $tmp['realname'] = $user_data['id_realname'][$value->user_id];
                        $tmp['old_market_price'] = $value->old_market_price ? unserialize($value->old_market_price) : '--';
                        $tmp['market_price'] = $value->market_price ? unserialize($value->market_price) : '--';
                        $items[] = $tmp;
                    }
                    $price_list['datalist'] = $items;
                }else{
                    $price_list = [];
                    $price_list['records_total'] = 1;
                    $price_list['records_filtered'] = 1;
                    //无价格变更记录
                    $link_info = (new \App\Models\BusinessOrderLink)->where('id', $inputs['link_id'])->first();
                    if(empty($link_info)){
                        return response()->json(['code' => 0, 'message' => '没有该链接信息']);
                    }
                    $tmp = [];
                    $tmp['opt_time'] = $link_info->created_at->format('Y-m-d H:i:s');
                    $tmp['date'] = '即时生效';
                    $tmp['realname'] = '--';
                    $tmp['old_market_price'] = '--';
                    $tmp['market_price'] = unserialize($link_info->market_price);
                    $items[] = $tmp;
                    $price_list['datalist'] = $items;
                }
                $data['price_list'] = $price_list;
                
                //每一天的反馈
                $link_feedbacks = new \App\Models\LinkFeedback;
                $link_where = [];
                $link_where['project_id'] = $income_info->project_id;
                $link_where['link_id'] = $inputs['link_id'];
                $link_where['start_time'] = $start_date;
                $link_where['end_time'] = $end_date;
                $link_feedback_list = $link_feedbacks->getLinkFeedbackByLinkId($link_where);
                $link_feedback_data = [];
                foreach ($link_feedback_list as $key => $value) {
                    $link_feedback_data[$value->date]['cpa_price'] = $value->cpa_price;
                    $link_feedback_data[$value->date]['cpa_amount'] = $value->cpa_amount;
                    $link_feedback_data[$value->date]['cps_price'] = $value->cps_price;
                    $link_feedback_data[$value->date]['cps_amount'] = $value->cps_amount;
                    $link_feedback_data[$value->date]['money'] = $value->money;
                }

                $days = prDates($start_date, $end_date);
                $items = [];
                foreach ($days as $d) {
                    $tmp = [];
                    $price = [];
                    if(isset($link_feedback_data[$d]['cpa_price']) && $link_feedback_data[$d]['cpa_price'] > 0){
                        $price[] = 'CPA-'.$link_feedback_data[$d]['cpa_price'];
                    }
                    if(isset($link_feedback_data[$d]['cps_price']) && $link_feedback_data[$d]['cps_price'] > 0){
                        $price[] = 'CPS-'.$link_feedback_data[$d]['cps_price'];
                    }
                    $tmp['price'] = !empty($price) ? implode(' ', $price) : '--';
                    $amount = [];
                    if(isset($link_feedback_data[$d]['cpa_amount']) && $link_feedback_data[$d]['cpa_amount'] > 0){
                        $amount[] = 'CPA:'.$link_feedback_data[$d]['cpa_amount'];
                    }
                    if(isset($link_feedback_data[$d]['cps_amount']) && $link_feedback_data[$d]['cps_amount'] > 0){
                        $amount[] = 'CPS:'.$link_feedback_data[$d]['cps_amount'];
                    }
                    $tmp['amount'] = !empty($amount) ? implode(' ', $amount) : '--';
                    $items[$d] = $tmp;
                }
                $data['feedback_info'] = $items;
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数project_id']);
            }
            if($inputs['project_id'] == 0){
                //新增记录-查看详情
                if(!isset($inputs['income_id']) || !is_numeric($inputs['income_id'])){
                    return response()->json(['code' => -1, 'message' => '缺少参数income_id']);
                }
                $income_info = $income->find($inputs['income_id']);
                $data = $income_detail = [];
                $income_detail['income_id'] = $income_info->id;
                $income_detail['project_name'] = $income_info->project_name;
                $income_detail['customer_name'] = $income_info->customer_name;
                $income_detail['sale_man'] = $income_info->sale_man;
                $income_detail['charge'] = $income_info->charge;
                $income_detail['business'] = $income_info->business;
                $income_detail['trade_name'] = $income_info->trade_name;
                $income_detail['resource'] = $income_info->resource;
                $income_detail['income_main'] = $income_info->income_main;
                $income_detail['month'] = $income_info->month;
                $income_detail['succ_count'] = $income_info->succ_count;
                $income_detail['all_income'] = $income_info->all_income;
                $income_detail['danfeng'] = sprintf('%.4f', ($income_info->all_income/$income_info->succ_count));//单封 = 收入/实际到达数
                
                $data['income_info'] = $income_detail;
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
            }
            $project_info = $project->getProjectInfo(['id'=>$inputs['project_id']]);
            if (empty($project_info)) {
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $income_list = $income->getIncomeList($inputs);
            $data = array();
            $base_info = array();
            $base_info['sale_man'] = $user_data['id_realname'][$project_info->sale_man];
            $base_info['charge'] = $user_data['id_realname'][$project_info->charge_id];
            $base_info['business'] = $user_data['id_realname'][$project_info->business_id];
            $data['base_info'] = $base_info;
            $income_list_list = array();
            foreach ($income_list['datalist'] as $key => $value) {
                $tmp = array();
                $tmp['id'] = $value->id;
                $tmp['month'] = $value->month;
                if($value->if_invoice == 1){
                    $tmp['if_invoice'] = '部分已开票';
                }elseif($value->if_invoice == 2){
                    $tmp['if_invoice'] = '已开票';
                }else{
                    $tmp['if_invoice'] = '未开票';
                }
                $tmp['succ_count'] = $value->succ_count;
                $tmp['real_income'] = $value->real_income;
                $tmp['all_income'] = $value->all_income;
                $tmp['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
                $tmp['invoice_date'] = $value->invoice_date ? $value->invoice_date : '--';
                $tmp['arrival_date'] = $value->arrival_date ? $value->arrival_date : '--';
                $tmp['business'] = $value->business;
                $income_list_list[] = $tmp;
            }
            $data['income_list_list'] = $income_list_list;
            
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
        	
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'delay_remarks'){
        	//延迟开票
        	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数id']);
        	}
        	if(!isset($inputs['delay_remarks']) || empty($inputs['delay_remarks'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数delay_remarks']);
        	}
        	$income_info = $income->getIncomeInfo($inputs);
        	if (empty($income_info)) {
            	return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
        	$income_info->delay_remarks = $inputs['delay_remarks'];
        	$result = $income_info->save();
        	if($result){
        		systemLog('收入到账表', '更新了延迟开票备注-'.$inputs['id']);
        		return response()->json(['code' => 1, 'message' => '操作成功']);
        	}
        	return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'real_income'){
        	//商务确认金额
        	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数id']);
        	}
        	if(!isset($inputs['real_income']) || empty($inputs['real_income'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数real_income']);
        	}
        	$income_info = $income->getIncomeInfo($inputs);
        	if (empty($income_info)) {
            	return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            if($income_info->if_edit == 2){
            	return response()->json(['code' => 0, 'message' => '已经申请开票，无法修改金额']);
            }
        	$income_info->real_income = $inputs['real_income'];
        	$result = $income_info->save();
        	if($result){
        		systemLog('收入到账表', '更新了商务确认金额-'.$inputs['id'].'['.$inputs['real_income'].']');
        		return response()->json(['code' => 1, 'message' => '操作成功']);
        	}
        	return response()->json(['code' => 0, 'message' => '操作失败']);
        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'invoice'){
            //开票记录
            if(!isset($inputs['income_id']) || !is_numeric($inputs['income_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数income_id']);
            }
            $data = [];
            $income_info = $income->getIncomeInfo(['id'=>$inputs['income_id']]);
            if (empty($income_info)) {
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $income_invoice_list = (new \App\Models\ProjectIncomeInvoice)->getInvoiceListByIncomeId($inputs);
            $invoice_types = $invoice_contents = [];
            foreach ($income->invoice_type as $key => $value) {
                $invoice_types[$value['id']] = $value['name'];
            }
            foreach ($income->invoice_content as $key => $value) {
                $invoice_contents[$value['id']] = $value['name'];
            }
            $items = [];
            foreach ($income_invoice_list as $key => $value) {
                $tmp = [];
                $tmp['apply_time'] = $value->created_at->format('Y-m-d H:i');
                $tmp['real_income'] = $income_info->real_income;
                $tmp['invoice_money'] = $value->money;
                $tmp['invoice_date'] = $value->invoice_date ? date('Y-m-d H:i', strtotime($value->invoice_date)) : '--';
                if($value->status == 1){
                    $tmp['status_txt'] = '已开票未到账';
                }elseif($value->status == 2){
                    $tmp['status_txt'] = '已开票已到账';
                }elseif($value->status == 3){
                    $tmp['status_txt'] = '已作废';
                }elseif($value->status == 4){
                    $tmp['status_txt'] = '已撤回';
                }else{
                    $tmp['status_txt'] = '未开票';
                }
                $tmp['arrival_date'] = $value->arrival_date ? $value->arrival_date : '--';
                $tmp['arrival_bank'] = $value->arrival_bank ? $value->arrival_bank : '--';
                $tmp2 = [];
                $tmp2['company'] = $value->hasInvoice->company;
                $tmp2['invoice_type'] = $invoice_types[$value->hasInvoice->invoice_type];
                $tmp2['invoice_content'] = $invoice_contents[$value->hasInvoice->invoice_content];
                $tmp2['taxpayer'] = $value->hasInvoice->taxpayer;
                $tmp2['address'] = $value->hasInvoice->address;
                $tmp2['tel'] = $value->hasInvoice->tel;
                $tmp2['bank'] = $value->hasInvoice->bank;
                $tmp2['bank_account'] = $value->hasInvoice->bank_account;
                $tmp['invoice_info'] = $tmp2;
                $items[] = $tmp;
            }
            $data['income_invoice_list'] = $items;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        
        if(isset($inputs['export']) && $inputs['export'] == 1){
        	//导出
        	$inputs['all'] = 1;
        }
    	$data = $income->getIncomeList($inputs);
    	$items = $export_body = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$tmp = array();
    		$tmp['id'] = $value->id;
    		$tmp['customer_id'] = $value->customer_id;
    		$tmp['project_id'] = $value->project_id;
    		$tmp['customer_name'] = $export_body[$key]['customer_name'] = $value->customer_name;
    		$tmp['project_name'] = $export_body[$key]['project_name'] = $value->project_name;
    		$tmp['sale_man'] = $export_body[$key]['sale_man'] = $value->sale_man;
    		$tmp['trade_name'] = $export_body[$key]['trade_name'] = $value->trade_name;
            $tmp['income_main'] = $export_body[$key]['income_main'] = $value->income_main;
            $tmp['resource'] = $export_body[$key]['resource'] = $value->resource;
    		$tmp['month'] = $export_body[$key]['month'] = $value->month;
    		$tmp['succ_count'] = $export_body[$key]['succ_count'] = $value->succ_count;
    		$tmp['danfeng'] = $export_body[$key]['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
    		$tmp['if_edit'] = $value->if_edit;//是否能修改商务确认 1能修改 2不能修改
    		$tmp['all_income'] = $export_body[$key]['all_income'] = $value->all_income;
    		$tmp['real_income'] = $export_body[$key]['real_income'] = $value->real_income;
            $tmp['invoice_money'] = $export_body[$key]['invoice_money'] = $value->invoice_money;
            if($value->if_invoice == 1){
                $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '部分已开票';
            }elseif($value->if_invoice == 2){
                $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '已开票';
            }else{
                if(!empty($value->delay_remarks)){
                    $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '延迟开票';
                }else{
                    $tmp['if_invoice'] = $export_body[$key]['if_invoice'] = '未开票';
                }
            }
    		$tmp['arrival_date'] = $value->arrival_date ? $value->arrival_date : '/';
    		$tmp['arrival_amount'] = abs($value->arrival_amount) ? $value->arrival_amount : '/';//到帐金额
            $tmp['arrival_bank'] = $value->arrival_bank ? $value->arrival_bank : '/';//收支银行
    		$tmp['invoice_date'] = $value->invoice_date ? $value->invoice_date : '/';
            if($tmp['arrival_amount'] > 0 && $tmp['arrival_amount'] < $tmp['invoice_money']){
                $tmp['if_finance'] = '部分已到账';
            }else{
                $tmp['if_finance'] = $value->if_finance ? '已确认':'未确认';
            }
            $export_body[$key]['if_finance'] = $tmp['if_finance'];
            $export_body[$key]['arrival_date'] = $tmp['arrival_date'];
            $export_body[$key]['arrival_amount'] = $tmp['arrival_amount'];
            $export_body[$key]['arrival_bank'] = $tmp['arrival_bank'];
            $export_body[$key]['invoice_date'] = $tmp['invoice_date'];
            if(empty($value->hasIncomeInvoice->toArray())){
                $tmp['arrival_content'] = [];
            }else{
                $arrival_date_content = [];
                $arrival_amount_content = [];
                $arrival_bank_content = [];
                $invoice_date_content = [];
                $arrival_content = [];
                foreach ($value->hasIncomeInvoice as $val) {
                    $arrival_date_content[] = $val->arrival_date ? $val->arrival_date : '/';
                    $arrival_amount_content[] = $val->arrival_money ? $val->arrival_money : '/';
                    $arrival_bank_content[] = $val->arrival_bank ? $val->arrival_bank : '/';
                    $invoice_date_content[] = $val->invoice_date ? $val->invoice_date : '/';
                    $con = [];
                    $con['arrival_date_content'] = $val->arrival_date ? $val->arrival_date : '/';
                    $con['arrival_amount_content'] = $val->arrival_money ? $val->arrival_money : '/';
                    $con['arrival_bank_content'] = $val->arrival_bank ? $val->arrival_bank : '/';
                    $con['invoice_date_content'] = $val->invoice_date ? $val->invoice_date : '/';
                    $arrival_content [] = $con;
                }
                $tmp['arrival_content'] = $arrival_content;
                $export_body[$key]['arrival_date'] = implode(',', $arrival_date_content);
                $export_body[$key]['arrival_amount'] = implode(',', $arrival_amount_content);
                $export_body[$key]['arrival_bank'] = implode(',', $arrival_bank_content);
                $export_body[$key]['invoice_date'] = implode(',', $invoice_date_content);
            }
    		$tmp['remarks'] = $value->remarks;
    		$tmp['delay_remarks'] = $value->delay_remarks;
    		$export_body[$key]['remarks'] = ($value->remarks ? '备注：'.$value->remarks : '') . ($value->delay_remarks ? '延迟说明：'.$value->delay_remarks : '');
    		$tmp['charge'] = $value->charge;
            $tmp['if_edit'] = 0;
            $tmp['if_has_record'] = 0;
            if($value->if_invoice > 0){
                $tmp['if_has_record'] = 1;
            }
    		$items[] = $tmp;
    	}
    	if(isset($inputs['export']) && $inputs['export'] == 1){
    		//导出
            set_time_limit(0);
    		$export_head = ['customer_name'=>'客户名称','project_name'=>'项目名称','sale_man'=>'销售','trade_name'=>'行业','income_main'=>'主体','resource'=>'资源','month'=>'月份','succ_count'=>'实际到达数','danfeng'=>'单封','all_income'=>'执行确认','real_income'=>'商务确认','invoice_money'=>'开票金额','if_invoice'=>'发票情况','if_finance'=>'财务情况','arrival_date'=>'到帐日期','arrival_amount'=>'到账金额','arrival_bank'=>'收支银行','invoice_date'=>'开票日期','remarks'=>'备注'];
            $filedata = pExprot($export_head, $export_body, 'income');
            $filepath = 'storage/exports/' . $filedata['file'];//下载链接
            $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
            return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
    	}
    	$data['datalist'] = $items;
    	$data['trade_list'] = (new \App\Models\Trade)->select(['id','name'])->get();
    	$data['if_invoice'] = [['id'=>0,'name'=>'未开票'],['id'=>1,'name'=>'部分已开票'],['id'=>2,'name'=>'已开票']];
        $resource_types = $income_main_types = [];
        foreach ((new \App\Models\BusinessOrder)->resource_types as $key => $value) {
            $resource_types[$value['name']] = $value['name'];
        }
        foreach ((new \App\Models\BusinessOrder)->income_main_types as $key => $value) {
            $income_main_types[$value['name']] = $value['name'];
        }
        $data['resource_type'] = $resource_types;
        $data['income_main_types'] = $income_main_types;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     *  按月份生成项目收入表
     * @author molin
     * @date 2019-03-13
     *
     */
    public function create(){
    	set_time_limit(0);
    	$inputs = request()->all();
    	$income = new \App\Models\ProjectIncome;
    	$project = new \App\Models\BusinessProject;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
    		if(!isset($inputs['ids']) || !is_array($inputs['ids'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数ids']);
    		}
            if(empty($inputs['ids'])){
                $inputs['ids'] = $project->when(isset($inputs['project_name']) && !empty($inputs['project_name']), function ($query) use ($inputs){
                                    $query->where('project_name', 'like', '%'.$inputs['project_name'].'%');
                                })
                                ->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs){
                                    $query->where('status', $inputs['status']);
                                })
                                ->when(isset($inputs['trade_id']) && is_numeric($inputs['trade_id']), function($query)use($inputs){
                                    $query->where('trade_id', $inputs['trade_id']);;
                                })
                                ->when(isset($inputs['sale_user']) && !empty($inputs['sale_user']), function ($query) use ($inputs){
                                    $query->whereHas('saleUser', function($query)use($inputs){
                                        $query->where('realname', 'like', '%'.$inputs['sale_user'].'%');
                                    });
                                })->pluck('id')->toArray();
            }
    		if(isset($inputs['month']) && !empty($inputs['month'])){
                $start_date = date('Ym01', strtotime($inputs['month']));
            }else{
                $cur_date = date('Y-m-d');
                $start_date = date('Ym01', strtotime("$cur_date -1 month"));
            }
	    	$end_date = date('Ymd', strtotime("$start_date +1 month -1 day"));
	    	$pre_month = date('Y-m', strtotime($start_date));
	    	if(isset($inputs['if_cover']) && $inputs['if_cover'] == 1 && !empty($inputs['exist_ids'])){
	    		//覆盖之前生成的数据
	    		$income->where('month', $pre_month)->whereIn('id', $inputs['exist_ids'])->delete();
	    	}
	    	$exist_income = $income->where('month', $pre_month)->whereIn('project_id', $inputs['ids'])->select(['id','project_name','if_edit'])->get()->toArray();
	    	if(!empty($exist_income)){
	    		//已经生成过 则提示
	    		$exist_ids = $exist_projects = array();
	    		foreach ($exist_income as $key => $value) {
	    			$exist_ids[] = $value['id'];
	    			$exist_projects[] = $value['project_name'];
                    if($value['if_edit'] == 2){
                        //锁定状态  已经申请开票
                        return response()->json(['code' => 0, 'message' => $value['project_name'].'-已经申请开票，不能再次生成']);
                    }
	    		}
	    		$exist_projects = implode('、', $exist_projects);
	    		return response()->json(['code' => 1, 'message' => $exist_projects.'上个月的收入已经存在，是否要覆盖？', 'if_cover' => 1, 'exist_ids' => $exist_ids]);
	    	}
	    	
	        $feedbacks = new \App\Models\ProjectFeedback;
	    	$inputs['all'] = 1;
	    	$data = $project->getDataList($inputs);
	        //反馈数据  拿到cpa、cpc、cps量
	        $sql_start_date = date('Y-m-d', strtotime($start_date));
	        $sql_end_date = date('Y-m-d', strtotime($end_date));
	        $feedbacks_list = $feedbacks->getProjectTotal($sql_start_date, $sql_end_date,$inputs['ids']);
	        $feedbacks_data = array();
            $feedback_data_total_money = [];
	        foreach ($feedbacks_list as $key => $value) {
	            $feedbacks_data[$value->project_id]['send_amount'] = $value->send_amount;
                $feedbacks_data[$value->project_id]['real_succ_amount'] = $value->succ_amount - $value->intercept + $value->real_psend;//实际到达数 = 到达数 - 拦截数 + 实际补量
                $feedback_data_total_money[$value->project_id] = $feedback_data_total_money[$value->project_id] ?? 0;
                $feedback_data_total_money[$value->project_id] += sprintf('%.2f', $value->money);
	        }
	        //---end 反馈数据 
	        $order_ids = array();
	        foreach ($data['datalist'] as $key => $value) {
	        	$order_ids[] = $value->order_id;
	        }
	        $days = prDates($sql_start_date, $sql_end_date);
	        $insert_data = array();
	        foreach ($data['datalist'] as $key => $value) {
	            $tmp = array();
	            $tmp['user_id'] = auth()->user()->id;
                $tmp['order_id'] = $value->order_id;
	            $tmp['customer_id'] = $value->customer_id;
	            $tmp['project_id'] = $value->id;
	            $tmp['project_name'] = $value->project_name;
	            $tmp['month'] = date('Y-m', strtotime($start_date));//月份
	            $tmp['trade_id'] = $value->trade_id;
	            $tmp['trade_name'] = $value->trade->name;
	            $tmp['customer_name'] = $value->hasCustomer->customer_name;
	            $tmp['sale_man'] = $user_data['id_realname'][$value->sale_man];
	            $tmp['sale_user_id'] = $value->sale_man;
	            $tmp['charge_id'] = $value->charge_id;
	            $tmp['charge'] = $user_data['id_realname'][$value->charge_id];
	            $tmp['business_id'] = $value->business_id;
	            $tmp['business'] = $user_data['id_realname'][$value->business_id];
	            // $tmp['project_type'] = $value->project_type == 1 ? '平台' : '非平台';
	            // $tmp['if_check'] = $value->if_check == 1 ? '考核' : '非考核';
                if($value->resource_type == 1){
                    $tmp['resource'] = '正常投递';
                }elseif($value->resource_type == 2){
                    $tmp['resource'] = '触发';
                }elseif($value->resource_type == 3){
                    $tmp['resource'] = '特殊组段';
                }else{
                    $tmp['resource'] = '未知';
                }
                if($value->income_main_type == 1){
                    $tmp['income_main'] = '神灯';
                }elseif($value->income_main_type == 2){
                    $tmp['income_main'] = '技术';
                }else{
                    $tmp['income_main'] = '未知';
                }

	            $tmp['send_count'] = $feedbacks_data[$value->id]['send_amount'] ?? 0;//发送量
	            $tmp['succ_count'] = $feedbacks_data[$value->id]['real_succ_amount'] ?? 0;//实际到达量
	            $tmp['real_income'] = 0;//实际收入 -由商务来填写
	            $tmp['all_income'] = $feedback_data_total_money[$value->id] ?? 0;//收入汇总
	            $tmp['created_at'] = date('Y-m-d H:i:s');
	            $tmp['updated_at'] = date('Y-m-d H:i:s');
	            $insert_data[] = $tmp;
	        }
	        // dd($insert_data);
	    	$result = $income->storeData($insert_data);
	    	if($result){
	    		systemLog('收入到账表', '生成上月到账表数据-'.$pre_month);
	    		return response()->json(['code' => 1, 'message' => '操作成功']);
	    	}
	    	return response()->json(['code' => 0, 'message' => '操作失败']);
    	}

    	$data = $project->getDataList($inputs);
    		$items = array();
    		foreach ($data['datalist'] as $key => $value) {
    			$tmp = array();
    			$tmp['id'] = $value->id;
    			// $tmp['project_type'] = $value->project_type == 1 ? '平台':'非平台';
    			$tmp['customer_name'] = $value->hasCustomer->customer_name;
    			$tmp['project_name'] = $value->project_name;
    			$tmp['sale_man'] = $user_data['id_realname'][$value->sale_man];
    			switch ($value->status) {
    				case 1:
    					$status_txt = '投递中';
    					break;
    				case 2:
    					$status_txt = '投递完毕';
    					break;
    				case 3:
    					$status_txt = '投递暂停';
    					break;
    				default:
    					$status_txt = '待投递';
    					break;
    			}
    			$tmp['status_txt'] = $status_txt;//投递状态 0待投递 1投递中 2投递完毕3投递暂停
    			$items[] = $tmp;
    		}
    		$data['datalist'] = $items;
    		$data['trade_list'] = (new \App\Models\Trade)->select(['id', 'name'])->get();
    		$data['status_list'] = [['id'=>0,'name'=>'待投递'],['id'=>1,'name'=>'投递中'],['id'=>2,'name'=>'投递完毕'],['id'=>3,'name'=>'投递暂停']];
            $data['resource_type'] = (new \App\Models\BusinessOrder)->resource_types;
            $data['income_main_type'] = (new \App\Models\BusinessOrder)->income_main_types;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     *  申请开票
     * @author molin
     * @date 2019-03-15
     *
     */
    public function apply(){
    	$inputs = request()->all();
    	$income = new \App\Models\ProjectIncome;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
    		if(!isset($inputs['receipt_data']) || !is_array($inputs['receipt_data']) || empty($inputs['receipt_data'])){
    			return response()->json(['code' => -1, 'message' => '请提交数据receipt_data']);
    		}
    		if(!isset($inputs['ids']) || !is_array($inputs['ids']) || empty($inputs['ids'])){
    			return response()->json(['code' => -1, 'message' => '请提交数据ids']);
    		}
    		if(!isset($inputs['customer_id']) || !is_numeric($inputs['customer_id'])){
    			return response()->json(['code' => -1, 'message' => '请提交数据customer_id']);
    		}
    		$customer_info = (new \App\Models\BusinessOrderCustomer)->where('id', $inputs['customer_id'])->select(['id', 'customer_name', 'sale_user_id'])->first();
    		if(empty($customer_info)){
    			return response()->json(['code' => 0, 'message' => '客户不存在']);
    		}
    		$user_data = (new \App\Models\User)->getIdToData();
    		$inputs['sale_man'] = $user_data['id_realname'][$customer_info->sale_user_id] ?? '--';
    		$inputs['customer_name'] = $customer_info->customer_name;
    		$receipt_insert = $invoice_money = $ids = array();
    		foreach ($inputs['receipt_data'] as $key => $value) {
    			if(!isset($value['receipt_id']) || !is_numeric($value['receipt_id'])){
    				return response()->json(['code' => -1, 'message' => 'receipt_id发票抬头不能为空']);
    			}
                if(!isset($value['name']) || empty($value['name'])){
                    return response()->json(['code' => -1, 'message' => 'name发票抬头不能为空']);
                }
    			if(!isset($value['invoice_type']) || !is_numeric($value['invoice_type'])){
    				return response()->json(['code' => -1, 'message' => 'invoice_type发票类型不能为空']);
    			}
    			if(!isset($value['invoice_content']) || !is_numeric($value['invoice_content'])){
    				return response()->json(['code' => -1, 'message' => 'invoice_content发票内容不能为空']);
    			}
    			if(!isset($value['taxpayer']) || empty($value['taxpayer'])){
    				return response()->json(['code' => -1, 'message' => 'taxpayer纳税人识别号不能为空']);
    			}
    			if(!isset($value['address']) || empty($value['address'])){
    				return response()->json(['code' => -1, 'message' => 'address地址不能为空']);
    			}
    			if(!isset($value['tel']) || empty($value['tel'])){
    				return response()->json(['code' => -1, 'message' => 'tel电话不能为空']);
    			}
    			if(!isset($value['bank']) || empty($value['bank'])){
    				return response()->json(['code' => -1, 'message' => 'bank开户行不能为空']);
    			}
    			if(!isset($value['bank_account']) || empty($value['bank_account'])){
    				return response()->json(['code' => -1, 'message' => 'bank_account账号不能为空']);
    			}
    			if(!isset($value['income_ids']) || empty($value['income_ids']) || !is_array($value['income_ids'])){
    				return response()->json(['code' => -1, 'message' => 'income_ids不能为空']);
    			}
                foreach ($value['income_ids'] as $vv) {
                    if(!isset($vv['id']) || !is_numeric($vv['id'])){
                        return response()->json(['code' => -1, 'message' => 'income_ids里面缺少参数id']);
                    }
                    if(!isset($vv['money']) || !is_numeric($vv['money'])){
                        return response()->json(['code' => -1, 'message' => 'income_ids里面缺少参数money']);
                    }
                }
                $income_ids = [];
                $total_amount = 0;
                foreach($value['income_ids'] as $k => $v){
                    $invoice_money[$v['id']] = $invoice_money[$v['id']] ?? 0;
                    $invoice_money[$v['id']] += $v['money'];
                    if(isset($income_ids[$v['id']])){
                        return response()->json(['code' => -1, 'message' => '一个发票不能开两个相同的项目']);
                    }
                    $income_ids[$v['id']] = $v['id'];
                    $ids[$v['id']] = $v['id'];
                    $total_amount += $v['money'];//发票总金额
                }

    			$if_month = $income->select(['customer_id','month'])->whereIn('id', $income_ids)->groupBy('month')->groupBy('customer_id')->get()->toArray();
    			if(count($if_month) > 1){
    				return response()->json(['code' => -1, 'message' => '同一个发票只能开同一个月份的项目']);
    			}
    			$inputs['receipt_data'][$key]['month'] = $if_month[0]['month'];
    			$inputs['receipt_data'][$key]['total_amount'] = sprintf('%.2f', $total_amount);
                if($value['receipt_id'] == 0){
                    //新增发票公司
                    $receipt_insert[$key]['name'] = $value['name'];
                    $receipt_insert[$key]['invoice_type'] = $value['invoice_type'];
                    $receipt_insert[$key]['invoice_content'] = $value['invoice_content'];
                    $receipt_insert[$key]['taxpayer'] = $value['taxpayer'];
                    $receipt_insert[$key]['address'] = $value['address'];
                    $receipt_insert[$key]['tel'] = $value['tel'];
                    $receipt_insert[$key]['bank'] = $value['bank'];
                    $receipt_insert[$key]['bank_account'] = $value['bank_account'];
                }

    		}
    		foreach ($inputs['ids'] as $val) {
    			if(!in_array($val, $ids)){
    				return response()->json(['code' => -1, 'message' => '还有剩余项目没选完，请选择']);
    			}
    		}
            $income_list = $income->whereIn('id', $ids)->select(['id','project_name','real_income','invoice_money'])->get()->toArray();
            /*foreach ($income_list as $key => $value) {
                $invoice_total_money = $invoice_money[$value['id']] + $value['invoice_money'];//准备开票的金额+已申请开票的总金额 <= 商务确认金额
                if($invoice_total_money > $value['real_income']){
                    return response()->json(['code' => 0, 'message' => '['.$value['project_name'].']开票总金额已经超出商务确认金额,请重新确认']);
                }
            }*/
    		$invoice = new \App\Models\ProjectInvoice;
            if(!empty($receipt_insert)){
                $receipt = new \App\Models\BusinessOrderReceipt;
                $res = $receipt->storeData(['id'=>$inputs['customer_id'],'receipt'=> $receipt_insert]);//添加开票公司
                if(!$res) return response()->json(['code' => 0, 'message' => '添加开票公司失败']);
            }
    		$result = $invoice->storeData($inputs);
    		if($result){
    			//修改收入表  禁止修改金额
    			$income->whereIn('id', $ids)->update(['if_edit'=>2, 'updated_at'=>date('Y-m-d H:i:s', time())]);
    			systemLog('收入到账表', '申请开票');
    			return response()->json(['code' => 1, 'message' => '操作成功']);
    		}
    		return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
    	if(!isset($inputs['ids']) || !is_array($inputs['ids'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数ids']);
    	}
    	if(empty($inputs['ids'])){
    		return response()->json(['code' => -1, 'message' => '请选择要开票的项目']);
    	}
    	
    	$inputs['all'] = 1;
    	$income_list = $income->getIncomeList($inputs);
    	$data = $project_list = array();
    	$customer_id = 0;
        $yanchi_data = [];
    	foreach ($income_list['datalist'] as $key => $value) {
    		if($customer_id > 0 && $customer_id != $value->customer_id){
    			return response()->json(['code' => 0, 'message' => '请选择相同客户的项目']);
    		}
    		$customer_id = $value->customer_id;
            if($value->if_invoice == 2){
                return response()->json(['code' => 0, 'message' => $value->id.'-'.$value->project_name.'开票金额已超，不能再申请']);
            }
    		$tmp = array();
    		$tmp['id'] = $value->id;
    		$tmp['project_name'] = $value->project_name;
    		$tmp['month'] = $value->month;
    		$tmp['succ_count'] = $value->succ_count;
    		$tmp['real_income'] = $value->real_income;
    		$tmp['all_income'] = $value->all_income;
    		$tmp['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
    		$tmp['business'] = $value->business;
            $tmp['invoice_money'] = $value->invoice_money;
    		$sale_man = $value->sale_man;
    		$project_list[] = $tmp;
            //延迟开票弹窗
            if(!empty(trim($value->delay_remarks))){
                $yanchi_data[] = $value->project_name;
            }
    	}
        if(!empty($yanchi_data) && (!isset($inputs['if_continue']) || (isset($inputs['if_continue']) && $inputs['if_continue'] != 1))){
            return response()->json(['code' => 1, 'message' => implode('、', $yanchi_data).'需要延迟开票，是否确认？', 'data' => ['if_continue' => 0]]);
        }
    	$data['ids'] = $inputs['ids'];
    	$data['contracts_info'] = (new \App\Models\BusinessOrderContract)->select(['customer_id','customer_name','deadline','number'])->where('customer_id', $customer_id)->orderBy('created_at','desc')->first();
    	$data['contracts_info']->sale_man = $sale_man;
    	$data['project_list'] = $project_list;
    	$data['receipt_list'] = (new \App\Models\BusinessOrderReceipt)->where('customer_id', $customer_id)->get();
    	$data['invoice_type'] = $income->invoice_type;
    	$data['invoice_content'] = $income->invoice_content;
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     *  删除
     * @author molin
     * @date 2019-06-27
     *
     */
    public function delete(){
        $inputs = request()->all();
        if(!isset($inputs['income_id']) || !is_numeric($inputs['income_id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数income_id']);
        }
        $income = new \App\Models\ProjectIncome;
        $income_info = $income->find($inputs['income_id']);
        if($income_info->if_edit != 1){
            return response()->json(['code' => 0, 'message' => '已经申请开票的数据不能删除噢！']);
        }
        $result = $income_info->delete();
        if($result){
            return response()->json(['code' => 1, 'message' => '删除成功']);
        }
        return response()->json(['code' => 0, 'message' => '删除失败']);
    }
    
}

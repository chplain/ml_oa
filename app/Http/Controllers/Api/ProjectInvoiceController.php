<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class ProjectInvoiceController extends Controller
{
    /**
     *  我的开票申请
     * @author molin
     * @date 2019-03-18
     *
     */
    public function index(){
    	$inputs = request()->all();
    	$invoice = new \App\Models\ProjectInvoice;
    	$inputs['user_id'] = auth()->user()->id;
    	$income = new \App\Models\ProjectIncome;
    	$invoice_type = $invoice_content = array();
    	foreach ($income->invoice_type as $key => $value) {
    		$invoice_type[$value['id']] = $value['name'];
    	}
    	foreach ($income->invoice_content as $key => $value) {
    		$invoice_content[$value['id']] = $value['name'];
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$data = $info = array();
    		$invoice_info = $invoice->where('id', $inputs['id'])->where('user_id', $inputs['user_id'])->first();
    		if(empty($invoice_info)){
    			return response()->json(['code' => 0, 'message' => '数据不存在']);
    		}
    		$info['id'] = $invoice_info->id;
    		$info['company'] = $invoice_info->company;
    		$info['invoice_type'] = $invoice_type[$invoice_info->invoice_type];
    		$info['invoice_content'] = $invoice_content[$invoice_info->invoice_content];
    		$info['month'] = $invoice_info->month;
    		$info['taxpayer'] = $invoice_info->taxpayer;
    		$info['address'] = $invoice_info->address;
    		$info['tel'] = $invoice_info->tel;
    		$info['bank'] = $invoice_info->bank;
    		$info['bank_account'] = $invoice_info->bank_account;
    		$info['arrival_date'] = $invoice_info->arrival_date ? $invoice_info->arrival_date : '--';
    		$info['arrival_bank'] = $invoice_info->arrival_bank ? $invoice_info->arrival_bank : '';
    		$info['total_amount'] = $invoice_info->total_amount;
    		$info['remarks'] = $invoice_info->remarks ? $invoice_info->remarks : '';
            $income_data = unserialize($invoice_info->income_ids);
    		$income_ids = $invoice_money = [];
            foreach ($income_data as $key => $value) {
                $income_ids[] = $value['id'];
                $invoice_money[$value['id']] = $value['money'];
            }
    		$income_list = $income->select(['id','project_name','month','send_count','succ_count','real_income','all_income','business'])->whereIn('id', $income_ids)->get();
    		$items = array();
    		foreach ($income_list as $key => $value) {
    			$items[$key]['project_name'] = $value->project_name;
    			$items[$key]['month'] = $value->month;
    			$items[$key]['succ_count'] = $value->succ_count;
    			$items[$key]['real_income'] = $value->real_income;
    			$items[$key]['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
    			$items[$key]['all_income'] = $value->all_income;
    			$items[$key]['business'] = $value->business;
                $items[$key]['invoice_money'] = sprintf('%.2f', $invoice_money[$value->id]);
    		}
    		$info['income_list'] = $items;
    		$data['invoice_info'] = $info;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'recall_load'){
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$data = $info = array();
    		$invoice_info = $invoice->where('id', $inputs['id'])->where('status', 0)->first();
    		if(empty($invoice_info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据或该数据无法撤回']);
    		}
    		$info['id'] = $invoice_info->id;
    		$info['company'] = $invoice_info->company;
    		$info['invoice_type'] = $invoice_type[$invoice_info->invoice_type];
    		$info['invoice_content'] = $invoice_content[$invoice_info->invoice_content];
    		$info['month'] = $invoice_info->month;
    		$info['taxpayer'] = $invoice_info->taxpayer;
    		$info['address'] = $invoice_info->address;
    		$info['tel'] = $invoice_info->tel;
    		$info['bank'] = $invoice_info->bank;
    		$info['bank_account'] = $invoice_info->bank_account;
    		$info['arrival_date'] = $invoice_info->arrival_date ? $invoice_info->arrival_date : '--';
    		$info['arrival_bank'] = $invoice_info->arrival_bank ? $invoice_info->arrival_bank : '';
    		$info['total_amount'] = $invoice_info->total_amount;
    		$info['remarks'] = $invoice_info->remarks ? $invoice_info->remarks : '';
    		$income_data = unserialize($invoice_info->income_ids);
            $income_ids = $invoice_money = [];
            foreach ($income_data as $key => $value) {
                $income_ids[] = $value['id'];
                $invoice_money[$value['id']] = $value['money'];
            }
    		$income_list = $income->select(['id','project_name','month','send_count','succ_count','real_income','all_income','business'])->whereIn('id', $income_ids)->get();
    		$items = array();
    		foreach ($income_list as $key => $value) {
    			$items[$key]['project_name'] = $value->project_name;
    			$items[$key]['month'] = $value->month;
    			$items[$key]['succ_count'] = $value->succ_count;
    			$items[$key]['real_income'] = $value->real_income;
    			$items[$key]['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
    			$items[$key]['all_income'] = $value->all_income;
    			$items[$key]['business'] = $value->business;
                $items[$key]['invoice_money'] = sprintf('%.2f', $invoice_money[$value->id]);
    		}
    		$info['income_list'] = $items;
    		$data['invoice_info'] = $info;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'recall_save'){
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$data = $info = array();
    		$invoice_info = $invoice->where('id', $inputs['id'])->where('status', 0)->first();
    		if(empty($invoice_info)){
    			return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
    		}
    		if(!isset($inputs['remarks']) || empty($inputs['remarks'])){
    			return response()->json(['code' => -1, 'message' => '请填写备注']);
    		}
            $income_data = unserialize($invoice_info->income_ids);
            $income_ids = [];
            foreach ($income_data as $key => $value) {
                $income_ids[] = $value['id'];
            }
    		$invoice_info->remarks = $inputs['remarks'];
    		$invoice_info->status = 4;//已撤回
    		$result = $invoice_info->save();
    		if($result){
                $income_invoice = new \App\Models\ProjectIncomeInvoice;
                $others_invoice = $income_invoice->whereIn('status', [0,1,2])->where('invoice_id', $inputs['id'])->first();
                if(!$others_invoice){
                    //没有其它发票的时候 变为可编辑金额
                    $income->whereIn('id', $income_ids)->update(['if_edit'=>1, 'updated_at'=>date('Y-m-d H:i:s')]);
                }
                $income_invoice->where('invoice_id', $inputs['id'])->update(['status'=>4, 'updated_at'=>date('Y-m-d H:i:s')]);
    			systemLog('开票记录', '撤回了开票申请-'.$inputs['id']);
    			return response()->json(['code' => 1, 'message' => '操作成功']);
    		}
    		return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
    	$data = $invoice->getInvoiceList($inputs);
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$tmp = array();
    		$tmp['id'] = $value->id;
    		$tmp['customer_name'] = $value->customer_name;
    		$tmp['sale_man'] = $value->sale_man;
    		$tmp['company'] = $value->company;
    		$tmp['invoice_type'] = $invoice_type[$value->invoice_type];
    		$tmp['invoice_content'] = $invoice_content[$value->invoice_content];
    		$tmp['month'] = $value->month;
    		$tmp['total_amount'] = $value->total_amount;
    		$tmp['if_recall'] = 0;
    		if($value->status == 1){
    			$tmp['status_txt'] = '已开票未到帐';
    		}else if($value->status == 2){
    			$tmp['status_txt'] = '已开票已到帐';
    		}else if($value->status == 3){
    			$tmp['status_txt'] = '已作废';
    		}else if($value->status == 4){
    			$tmp['status_txt'] = '已撤回';
    		}else{
    			$tmp['status_txt'] = '未开票';
    			$tmp['if_recall'] = 1;
    		}
    		$tmp['remarks'] = $value->remarks;
    		$items[] = $tmp;
    	}
    	$data['datalist'] = $items;
    	$data['status_list'] = [['id'=>0,'name'=>'未开票'],['id'=>1,'name'=>'已开票未到帐'],['id'=>2,'name'=>'已开票已到帐'],['id'=>3,'name'=>'已作废'],['id'=>4,'name'=>'已撤回']];
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     *  开票申请汇总
     * @author molin
     * @date 2019-03-18
     *
     */
    public function summary(){
    	$inputs = request()->all();
    	$invoice = new \App\Models\ProjectInvoice;
    	$income = new \App\Models\ProjectIncome;
    	$invoice_type = $invoice_content = array();
    	foreach ($income->invoice_type as $key => $value) {
    		$invoice_type[$value['id']] = $value['name'];
    	}
    	foreach ($income->invoice_content as $key => $value) {
    		$invoice_content[$value['id']] = $value['name'];
    	}
    	
    	$data = $invoice->getInvoiceList($inputs);
    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$tmp = array();
    		$tmp['id'] = $value->id;
    		$tmp['customer_name'] = $value->customer_name;
    		$tmp['sale_man'] = $value->sale_man;
    		$tmp['company'] = $value->company;
    		$tmp['invoice_type'] = $invoice_type[$value->invoice_type];
    		$tmp['invoice_content'] = $invoice_content[$value->invoice_content];
    		$tmp['month'] = $value->month;
    		$tmp['total_amount'] = $value->total_amount;
    		$tmp['if_zuofei'] = 0;//是否显示作废按钮  1显示  0隐藏
    		$tmp['if_kaipiao'] = 0;//是否显示开票按钮  1显示  0隐藏
    		$tmp['if_daozhang'] = 0;//是否显示到账按钮  1显示  0隐藏
            $tmp['if_del'] = 0;//是否显示删除按钮  1显示  0隐藏
    		if($value->status == 1){
    			$tmp['status_txt'] = '已开票未到帐';
    			$tmp['if_zuofei'] = 1;
    			$tmp['if_daozhang'] = 1;
    		}else if($value->status == 2){
    			$tmp['status_txt'] = '已开票已到帐';
    		}else if($value->status == 3){
    			$tmp['status_txt'] = '已作废';
                $tmp['if_del'] = 1;
    		}else if($value->status == 4){
    			$tmp['status_txt'] = '已撤回';
                $tmp['if_del'] = 1;
    		}else{
    			$tmp['status_txt'] = '未开票';
    			$tmp['if_zuofei'] = 1;
    			$tmp['if_kaipiao'] = 1;
                $tmp['if_del'] = 1;
    		}
    		$tmp['remarks'] = $value->remarks ? $value->remarks : '--';
    		$items[] = $tmp;
    	}
    	
    	$data['datalist'] = $items;
    	$data['status_list'] = [['id'=>0,'name'=>'未开票'],['id'=>1,'name'=>'已开票未到帐'],['id'=>2,'name'=>'已开票已到帐'],['id'=>3,'name'=>'已作废'],['id'=>4,'name'=>'已撤回']];
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     *  开票申请汇总-查看详情
     * @author molin
     * @date 2019-06-10
     *
     */
    public function show(){
        $inputs = request()->all();
        $invoice = new \App\Models\ProjectInvoice;
        $income = new \App\Models\ProjectIncome;
        $invoice_type = $invoice_content = array();
        foreach ($income->invoice_type as $key => $value) {
            $invoice_type[$value['id']] = $value['name'];
        }
        foreach ($income->invoice_content as $key => $value) {
            $invoice_content[$value['id']] = $value['name'];
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $data = $info = array();
            $invoice_info = $invoice->where('id', $inputs['id'])->first();
            if(empty($invoice_info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $info['id'] = $invoice_info->id;
            $info['company'] = $invoice_info->company;
            $info['invoice_type'] = $invoice_type[$invoice_info->invoice_type];
            $info['invoice_content'] = $invoice_content[$invoice_info->invoice_content];
            $info['month'] = $invoice_info->month;
            $info['taxpayer'] = $invoice_info->taxpayer;
            $info['address'] = $invoice_info->address;
            $info['tel'] = $invoice_info->tel;
            $info['bank'] = $invoice_info->bank;
            $info['bank_account'] = $invoice_info->bank_account;
            $info['arrival_date'] = $invoice_info->arrival_date ? $invoice_info->arrival_date : '--';
            $info['arrival_bank'] = $invoice_info->arrival_bank ? $invoice_info->arrival_bank : '';
            $info['total_amount'] = $invoice_info->total_amount;
            $info['remarks'] = $invoice_info->remarks ? $invoice_info->remarks : '';
            $income_data = unserialize($invoice_info->income_ids);
            $income_ids = $invoice_money = [];
            foreach ($income_data as $key => $value) {
                $income_ids[] = $value['id'];
                $invoice_money[$value['id']] = $value['money'];
            }
            $income_list = $income->select(['id','project_name','month','send_count','succ_count','real_income','all_income','business'])->whereIn('id', $income_ids)->get();
            $items = array();
            foreach ($income_list as $key => $value) {
                $items[$key]['project_name'] = $value->project_name;
                $items[$key]['month'] = $value->month;
                $items[$key]['succ_count'] = $value->succ_count;
                $items[$key]['real_income'] = $value->real_income;
                $items[$key]['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
                $items[$key]['all_income'] = $value->all_income;
                $items[$key]['business'] = $value->business;
                $items[$key]['invoice_money'] = sprintf('%.2f',$invoice_money[$value->id]);
            }
            $info['income_list'] = $items;
            $data['invoice_info'] = $info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }

    }

    /**
     *  开票申请汇总-作废
     * @author molin
     * @date 2019-06-10
     *
     */
    public function cancel(){
        $inputs = request()->all();
        $invoice = new \App\Models\ProjectInvoice;
        $income = new \App\Models\ProjectIncome;
        $invoice_type = $invoice_content = array();
        foreach ($income->invoice_type as $key => $value) {
            $invoice_type[$value['id']] = $value['name'];
        }
        foreach ($income->invoice_content as $key => $value) {
            $invoice_content[$value['id']] = $value['name'];
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'zuofei_load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $data = $info = array();
            $invoice_info = $invoice->where('id', $inputs['id'])->whereIn('status', [0,1])->first();
            if(empty($invoice_info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $info['id'] = $invoice_info->id;
            $info['company'] = $invoice_info->company;
            $info['invoice_type'] = $invoice_type[$invoice_info->invoice_type];
            $info['invoice_content'] = $invoice_content[$invoice_info->invoice_content];
            $info['month'] = $invoice_info->month;
            $info['taxpayer'] = $invoice_info->taxpayer;
            $info['address'] = $invoice_info->address;
            $info['tel'] = $invoice_info->tel;
            $info['bank'] = $invoice_info->bank;
            $info['bank_account'] = $invoice_info->bank_account;
            $info['arrival_date'] = $invoice_info->arrival_date ? $invoice_info->arrival_date : '--';
            $info['arrival_bank'] = $invoice_info->arrival_bank ? $invoice_info->arrival_bank : '';
            $info['total_amount'] = $invoice_info->total_amount;
            $info['remarks'] = $invoice_info->remarks ? $invoice_info->remarks : '';
            $income_data = unserialize($invoice_info->income_ids);
            $income_ids = $invoice_money = [];
            foreach ($income_data as $key => $value) {
                $income_ids[] = $value['id'];
                $invoice_money[$value['id']] = $value['money'];
            }
            $income_list = $income->select(['id','project_name','month','send_count','succ_count','real_income','all_income','business'])->whereIn('id', $income_ids)->get();
            $items = array();
            foreach ($income_list as $key => $value) {
                $items[$key]['project_name'] = $value->project_name;
                $items[$key]['month'] = $value->month;
                $items[$key]['succ_count'] = $value->succ_count;
                $items[$key]['real_income'] = $value->real_income;
                $items[$key]['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
                $items[$key]['all_income'] = $value->all_income;
                $items[$key]['business'] = $value->business;
                $items[$key]['invoice_money'] = $invoice_money[$value->id];
            }
            $info['income_list'] = $items;
            $data['invoice_info'] = $info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'zuofei_save'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $data = $info = array();
            $invoice_info = $invoice->where('id', $inputs['id'])->whereIn('status', [0,1])->first();
            if(empty($invoice_info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            if(!isset($inputs['remarks']) || empty($inputs['remarks'])){
                return response()->json(['code' => -1, 'message' => '请填写备注']);
            }
            $income_data = unserialize($invoice_info->income_ids);
            $income_ids = $income_update = [];
            foreach ($income_data as $k => $v) {
                $income_ids[] = $v['id'];
            }
            $ids_invoice_money = $income->whereIn('id', $income_ids)->pluck('invoice_money', 'id')->toArray();
            foreach ($income_data as $k => $v) {
                $income_update[$k]['id'] = $v['id'];
                $income_update[$k]['invoice_money'] = $int = $ids_invoice_money[$v['id']] - $v['money'];// 已开票金额 = 已开票金额 - 作废金额
                if($int == 0){
                    //退回到未开票状态  可编辑
                    $income_update[$k]['if_edit'] = 1;
                    $income_update[$k]['if_invoice'] = 0;
                }else{
                    //退回到部分开票状态
                    $income_update[$k]['if_invoice'] = 1;
                }
                $income_update[$k]['updated_at'] = date('Y-m-d H:i:s');
            }
            $invoice_info->remarks = $inputs['remarks'];
            $invoice_info->status = 3;//作废
            $income_invoice_where = $income_invoice_update = [];
            $income_invoice_where['invoice_id'] = $inputs['id'];
            $income_invoice_update['status'] = 3;
            $income_invoice_update['updated_at'] = date('Y-m-d H:i:s');
            $result = $invoice->setAccountEntry($income, $invoice_info, $income_update, $income_invoice_where, $income_invoice_update);
            if($result){
                systemLog('开票记录', '作废操作-'.$inputs['id']);
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }

    }

    /**
     *  开票申请汇总-开票
     * @author molin
     * @date 2019-06-10
     *
     */
    public function open(){
        $inputs = request()->all();
        $invoice = new \App\Models\ProjectInvoice;
        $income = new \App\Models\ProjectIncome;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'kaipiao_save'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $data = $info = array();
            $invoice_info = $invoice->where('id', $inputs['id'])->where('status', 0)->first();
            if(empty($invoice_info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $invoice_info->status = 1;//开票
            $invoice_info->invoice_date = date('Y-m-d', time());//开票日期
            $income_data = unserialize($invoice_info->income_ids);
            $income_ids = $income_update = [];
            foreach ($income_data as $k => $v) {
                $income_ids[] = $v['id'];
            }
            $income_list = $income->whereIn('id', $income_ids)->select(['id','real_income','project_name','invoice_money'])->get()->toArray();
            $id_real_income = $id_invoice_money = $id_project_name = [];
            foreach ($income_list as $key => $value) {
                $id_real_income[$value['id']] = $value['real_income'];
                $id_invoice_money[$value['id']] = $value['invoice_money'];
                $id_project_name[$value['id']] = $value['project_name'];
            }
            $invoice_money = [];
            foreach ($income_data as $k => $v) {
                $invoice_money[$v['id']] = $invoice_money[$v['id']] ?? 0;
                $invoice_money[$v['id']] += $v['money'];
            }

            foreach ($income_data as $k => $v) {
                $income_update[$v['id']]['id'] = $v['id'];
                $income_update[$v['id']]['invoice_date'] = date('Y-m-d');//开票日期
                $income_update[$v['id']]['updated_at'] = date('Y-m-d H:i:s');
                $total_invoice_money = $id_invoice_money[$v['id']] + $invoice_money[$v['id']];//已开票金额= 已开票金额 + 开票金额
                $income_update[$v['id']]['invoice_money'] = sprintf('%.2f', $total_invoice_money);
                /*if($total_invoice_money > $id_real_income[$v['id']]){
                    return response()->json(['code' => 0, 'message' => '['.$id_project_name[$v['id']].']开票总金额已经超出商务确认金额,请检查']);
                }*/
                if($total_invoice_money == $id_real_income[$v['id']]){
                    $income_update[$v['id']]['if_invoice'] = 2;//已开票
                }else{
                    $income_update[$v['id']]['if_invoice'] = 1;//部分已开票
                }
            }
            $income_invoice_where = $income_invoice_update = [];
            $income_invoice_where['invoice_id'] = $inputs['id'];
            $income_invoice_update['status'] = 1;
            $income_invoice_update['invoice_date'] = date('Y-m-d H:i:s');
            $income_invoice_update['updated_at'] = date('Y-m-d H:i:s');
            $result = $invoice->setAccountEntry($income, $invoice_info, $income_update, $income_invoice_where, $income_invoice_update);
            if($result){
                systemLog('开票记录', '开票操作-'.$inputs['id']);
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        
    }

    /**
     *  开票申请汇总-批量开票
     * @author molin
     * @date 2019-06-27
     *
     */
    public function option(){
        $inputs = request()->all();
        $invoice = new \App\Models\ProjectInvoice;
        $income = new \App\Models\ProjectIncome;
        if(!isset($inputs['ids']) || !is_array($inputs['ids'])){
            return response()->json(['code' => -1, 'message' => '缺少参数ids']);
        }
        $data = $info = array();
        $invoice_list = $invoice->whereIn('id', $inputs['ids'])->get()->toArray();
        if(empty($invoice_list)){
            return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
        }
        $invoice_update = $income_update = $invoice_ids = $income_ids = $invoice_money = [];
        foreach($invoice_list as $key => $value){
            if($value['status'] > 0){
                return response()->json(['code' => 0, 'message' => '所选发票已开票或已作废']);
            }
            $income_data = unserialize($value['income_ids']);
            foreach ($income_data as $k => $v) {
                $income_ids[$v['id']] = $v['id'];
                $invoice_money[$v['id']] = $invoice_money[$v['id']] ?? 0;
                $invoice_money[$v['id']] += $v['money'];
            }
            $invoice_ids[] = $value['id'];
        }
        $income_list = $income->whereIn('id', $income_ids)->select(['id','real_income','project_name','invoice_money'])->get()->toArray();
        $id_real_income = $id_invoice_money = $id_project_name = [];
        foreach ($income_list as $key => $value) {
            $id_real_income[$value['id']] = $value['real_income'];//商务确认金额
            $id_invoice_money[$value['id']] = $value['invoice_money'];//已开票金额
            $id_project_name[$value['id']] = $value['project_name'];//项目名称
        }

        foreach($invoice_list as $key => $value){
            $tmp = [];
            $tmp['id'] = $value['id'];
            $tmp['status'] = 1;//开票
            $tmp['invoice_date'] = date('Y-m-d', time());//开票日期
            $invoice_update[] = $tmp;
            $income_data = unserialize($value['income_ids']);
            foreach ($income_data as $k => $v) {
                $income_update[$v['id']]['id'] = $v['id'];
                $income_update[$v['id']]['invoice_date'] = date('Y-m-d H:i:s');//开票日期
                $income_update[$v['id']]['updated_at'] = date('Y-m-d H:i:s');
                $total_invoice_money = $id_invoice_money[$v['id']] + $invoice_money[$v['id']];//已开票金额= 已开票金额 + 开票金额
                $income_update[$v['id']]['invoice_money'] = sprintf('%.2f', $total_invoice_money);
                /*if($total_invoice_money > $id_real_income[$v['id']]){
                    return response()->json(['code' => 0, 'message' => '['.$id_project_name[$v['id']].']开票总金额已经超出商务确认金额,请检查']);
                }*/
                if($total_invoice_money == $id_real_income[$v['id']]){
                    $income_update[$v['id']]['if_invoice'] = 2;//已开票
                }else{
                    $income_update[$v['id']]['if_invoice'] = 1;//部分已开票
                }
            }
            
        }
        $result = $invoice->setOpen($income, $invoice, $invoice_update, $income_update);
        if($result){
            $income_invice = new \App\Models\ProjectIncomeInvoice;
            $income_invice->whereIn('invoice_id', $invoice_ids)->update(['status'=>1, 'invoice_date'=>date('Y-m-d'), 'updated_at'=>date('Y-m-d H:i:s')]);
            systemLog('开票记录', '批量开票操作-['.implode(',', $inputs['ids']).']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);

        
    }

    /**
     *  开票申请汇总-到账
     * @author molin
     * @date 2019-06-10
     *
     */
    public function arrival(){
        $inputs = request()->all();
        $invoice = new \App\Models\ProjectInvoice;
        $income = new \App\Models\ProjectIncome;
        $invoice_type = $invoice_content = array();
        foreach ($income->invoice_type as $key => $value) {
            $invoice_type[$value['id']] = $value['name'];
        }
        foreach ($income->invoice_content as $key => $value) {
            $invoice_content[$value['id']] = $value['name'];
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'daozhang_load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $data = $info = array();
            $invoice_info = $invoice->where('id', $inputs['id'])->where('status', 1)->first();
            if(empty($invoice_info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $info['id'] = $invoice_info->id;
            $info['company'] = $invoice_info->company;
            $info['invoice_type'] = $invoice_type[$invoice_info->invoice_type];
            $info['invoice_content'] = $invoice_content[$invoice_info->invoice_content];
            $info['month'] = $invoice_info->month;
            $info['taxpayer'] = $invoice_info->taxpayer;
            $info['address'] = $invoice_info->address;
            $info['tel'] = $invoice_info->tel;
            $info['bank'] = $invoice_info->bank;
            $info['bank_account'] = $invoice_info->bank_account;
            $info['total_amount'] = $invoice_info->total_amount;
            $income_data = unserialize($invoice_info->income_ids);
            $income_ids = $invoice_money = [];
            foreach ($income_data as $key => $value) {
                $income_ids[] = $value['id'];
                $invoice_money[$value['id']] = $value['money'];
            }
            $income_list = $income->select(['id','project_name','month','send_count','succ_count','real_income','all_income','business'])->whereIn('id', $income_ids)->get();
            $items = array();
            foreach ($income_list as $key => $value) {
                $items[$key]['id'] = $value->id;
                $items[$key]['project_name'] = $value->project_name;
                $items[$key]['month'] = $value->month;
                $items[$key]['succ_count'] = $value->succ_count;
                $items[$key]['real_income'] = $value->real_income;
                $items[$key]['danfeng'] = $value->succ_count > 0 ? sprintf('%.4f', $value->all_income/$value->succ_count) : 0;
                $items[$key]['all_income'] = $value->all_income;
                $items[$key]['business'] = $value->business;
                $items[$key]['invoice_money'] = sprintf('%.2f', $invoice_money[$value->id]);
            }
            $info['income_list'] = $items;
            $data['invoice_info'] = $info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'daozhang_save'){
            $rules = [
                'id' => 'required|integer',
                'arrival_date' => 'required|date_format:Y-m-d',
                'arrival_bank' => 'required|max:30',
                'remarks' => 'required'
            ];
            $attributes = [
                'id' => 'id',
                'arrival_date' => '到账日期',
                'arrival_bank' => '银行',
                'remarks' => '备注'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }

            $data = $info = array();
            $invoice_info = $invoice->where('id', $inputs['id'])->where('status', 1)->first();
            if(empty($invoice_info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $income_data = unserialize($invoice_info->income_ids);
            $income_ids = [];
            $arrival_amount = [];
            $arrival_amount_sum = 0;
            $income_invoice_update = [];
            foreach ($income_data as $key => $value) {
                $income_ids[] = $value['id'];
                $arrival_amount[$value['id']] = $arrival_amount[$value['id']] ?? 0;
                $arrival_amount[$value['id']] += $value['money'];
                $arrival_amount_sum += $value['money'];
                $tmp_income_invoice = [];
                $tmp_income_invoice['income_id'] = $value['id'];
                $tmp_income_invoice['invoice_id'] = $inputs['id'];
                $tmp_income_invoice['arrival_money'] = $value['money'];
                $income_invoice_update[] = $tmp_income_invoice;
            }
            $update_data = array();
            //更新income表数据
            foreach ($income_data as $key => $value) {
                $update_data[$value['id']]['id'] = $value['id'];
                $update_data[$value['id']]['arrival_amount'] = $arrival_amount[$value['id']];
                $update_data[$value['id']]['arrival_date'] = date('Y-m-d');
                $update_data[$value['id']]['arrival_bank'] = $inputs['arrival_bank'];
                $update_data[$value['id']]['if_finance'] = 1;//财务确认
                $update_data[$value['id']]['updated_at'] = date('Y-m-d H:i:s');
            }
            $invoice_info->arrival_date = $inputs['arrival_date'];
            $invoice_info->arrival_bank = $inputs['arrival_bank'];
            $invoice_info->arrival_amount = $arrival_amount_sum;//到账总金额
            $invoice_info->status = 2;//已到账
            $invoice_info->remarks = $inputs['remarks'];
            $result = $invoice->setAccountEntry($income, $invoice_info, $update_data);
            if($result){
                //锁定反馈表金额  不能再变动
                $lock_list = $income->whereIn('id', $income_ids)->select(['project_id', 'month'])->get()->toArray();
                $feedback = new \App\Models\ProjectFeedback;
                foreach ($lock_list as $key => $value) {
                    $m = str_replace('-', '', $value['month']);
                    $feedback->where('project_id', $value['project_id'])->where('month', date('Ym',$m))->update(['if_sett'=>1, 'updated_at'=>date('Y-m-d H:i:s')]);
                }
                $income_invice = new \App\Models\ProjectIncomeInvoice;
                foreach ($income_invoice_update as $key => $value) {
                    $income_invice->where('income_id', $value['income_id'])->where('invoice_id', $value['invoice_id'])->update(['status'=>2,'arrival_money'=>$value['arrival_money'], 'arrival_date'=>date('Y-m-d'), 'arrival_bank'=>$inputs['arrival_bank'], 'updated_at'=>date('Y-m-d H:i:s')]);
                }
                systemLog('开票记录', '到帐操作-'.$inputs['id']);
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        
    }

    /**
     *  开票申请汇总-导出
     * @author molin
     * @date 2019-06-10
     *
     */
    public function export(){
        $inputs = request()->all();
        $invoice = new \App\Models\ProjectInvoice;
        $income = new \App\Models\ProjectIncome;
        $invoice_type = $invoice_content = array();
        foreach ($income->invoice_type as $key => $value) {
            $invoice_type[$value['id']] = $value['name'];
        }
        foreach ($income->invoice_content as $key => $value) {
            $invoice_content[$value['id']] = $value['name'];
        }
        $condition = [];
        $inputs['all'] = 1;//导出
        if(isset($inputs['ids']) && is_array($inputs['ids']) && !empty($inputs['ids'])){
            $condition['ids'] = $inputs['ids'];
            $condition['all'] = 1;
        }else{
            $condition = $inputs;
        }

        $data = $invoice->getInvoiceList($condition);
        $export_data = array();
        foreach ($data['datalist'] as $key => $value) {
            $export_data[$key]['customer_name'] = $value->customer_name;
            $export_data[$key]['sale_man'] = $value->sale_man;
            $export_data[$key]['company'] = $value->company;
            $export_data[$key]['invoice_type'] = $invoice_type[$value->invoice_type];
            $export_data[$key]['invoice_content'] = $invoice_content[$value->invoice_content];
            $export_data[$key]['month'] = $value->month;
            $export_data[$key]['taxpayer'] = $value->taxpayer;
            $export_data[$key]['address'] = $value->address;
            $export_data[$key]['tel'] = "\t".$value->tel."\t";
            $export_data[$key]['bank'] = $value->bank;
            $export_data[$key]['bank_account'] = "\t".$value->bank_account."\t";
            $export_data[$key]['total_amount'] = $value->total_amount;
            if($value->status == 1){
                $status_txt = '已开票未到帐';
            }else if($value->status == 2){
                $status_txt = '已开票已到帐';
            }else if($value->status == 3){
                $status_txt = '已作废';
            }else if($value->status == 4){
                $status_txt = '已撤回';
            }else{
                $status_txt = '未开票';
            }
            $export_data[$key]['status_txt'] = $status_txt;
            $export_data[$key]['remarks'] = $value->remarks ? $value->remarks : '--';
        }
        $export_head = ['客户公司','销售员','开票公司名','开票类型','开票内容','结算周期','纳税人识别号','地址','电话','开户行','账号','开票金额（元）','状态','备注'];
        $filedata = pExprot($export_head, $export_data, 'invoices');
        $filepath = 'storage/exports/' . $filedata['file'];//下载链接
        $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
        return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
        
    }

    /**
     *  开票申请汇总-删除
     * @author molin
     * @date 2019-06-10
     *
     */
    public function delete(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $data = array();
        $invoice = new \App\Models\ProjectInvoice;
        $income = new \App\Models\ProjectIncome;
        $invoice_info = $invoice->where('id', $inputs['id'])->whereIn('status', [0,3,4])->first();//未开票、作废、撤回
        if(empty($invoice_info)){
            return response()->json(['code' => 0, 'message' => '该发票不能删除']);
        }
        $income_data = unserialize($invoice_info->income_ids);
        $income_ids = $income_update = [];
        foreach ($income_data as $k => $v) {
            $income_ids[] = $v['id'];
        }
        $ids_invoice_money = $income->whereIn('id', $income_ids)->pluck('invoice_money', 'id')->toArray();
        foreach ($income_data as $k => $v) {
            $income_update[$k]['id'] = $v['id'];
            $income_update[$k]['invoice_money'] = $int = $ids_invoice_money[$v['id']] - $v['money'];// 已开票金额 = 已开票金额 - 删除发票金额
            if($int == 0){
                //退回到未开票状态  可编辑
                $income_update[$k]['if_edit'] = 1;
                $income_update[$k]['if_invoice'] = 0;
                $income_update[$k]['invoice_date'] = null;
            }else{
                //退回到部分开票状态
                $income_update[$k]['if_invoice'] = 1;
            }
            $income_update[$k]['updated_at'] = date('Y-m-d H:i:s');
        }
        $result = $invoice->delAccountEntry($income, $income_update, $inputs['id']);
        if($result){
            systemLog('开票记录', '删除操作-'.$inputs['id']);
            return response()->json(['code' => 1, 'message' => '删除成功']);
        }
        return response()->json(['code' => 0, 'message' => '删除失败']);
        
    }

}

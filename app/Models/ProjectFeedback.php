<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ProjectFeedback extends Model
{
    //数据反馈
    protected $table = 'project_feedbacks';

    //获取连接信息

    public function getQueryList($inputs){
    	$query_where = $this->when(isset($inputs['project_ids']) && is_array($inputs['project_ids']), function($query)use($inputs){
                		$query->whereIn('project_id', $inputs['project_ids']);
                	})
                    ->when(isset($inputs['date']) && !empty($inputs['date']), function($query)use($inputs){
                        $query->where('date', $inputs['date']);
                    })
                    ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query)use($inputs){
                        $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                    });
    	$list = $query_where->get();
    	return $list;
    }

    //根据日期 计算项目总量
    public function getProjectTotal($start_date, $end_date, $project_ids){
    	return $this->whereIn('project_id', $project_ids)->whereBetween('date',[$start_date, $end_date])->select(DB::raw('`project_id`,SUM(`cpc_amount`) AS cpc_amount,SUM(`cpd_amount`) AS cpd_amount,SUM(`send_amount`) AS send_amount,SUM(`succ_amount`) AS succ_amount,SUM(`money`) AS money,SUM(`intercept`) AS intercept,SUM(`real_psend`) AS real_psend'))->groupBy('project_id')->get();
    }

    //根据项目id 日期 重新计算反馈表里面的金额 
    public function updateProjectIncome($project_id = 0, $date = ''){
        if($project_id == 0 || $date == '') return;
        $feedback = new ProjectFeedback; 
        $link_feedback = new \App\Models\LinkFeedback; 
        $feedback_info = $feedback->where('project_id', $project_id)->where('date', $date)->first();
        if(empty($feedback_info)){
            return;
        }
        //查看是否有单价  没有单价则生成单价
        $this->createFeedbackPrice($project_id, $date);
        $cpc_link_info = $link_feedback->where('project_id', $project_id)->where('date', $date)->where('cpc_price','>',0)->orderBy('id','asc')->first();
        $project_cpc_price = $project_cpd_price = 0;
        if(!empty($cpc_link_info)){
            $project_cpc_price = $cpc_link_info->cpc_price;
            $cpc_link_info->cpc_amount = $feedback_info->cpc_amount;
            $cpc_link_info->save();
        }
        $cpd_link_info = $link_feedback->where('project_id', $project_id)->where('date', $date)->where('cpd_price','>',0)->orderBy('id','asc')->first();
        if(!empty($cpd_link_info)){
            $project_cpd_price = $cpd_link_info->cpd_price;
            $cpd_link_info->cpd_amount = $feedback_info->cpd_amount;
            $cpd_link_info->save();
        }

        $link_feedback_list = $link_feedback->where('project_id', $project_id)->where('date', $date)->orderBy('id','desc')->get()->toArray();
        $project_cpa_amount = $project_cps_amount = $project_money = 0;
        $update_data = [];
        foreach ($link_feedback_list as $key => $value) {
            $tmp = [];
            $tmp['id'] = $value['id'];
            $value['cpa_price'] = $id_price[$value['id']]['cpa_price'] ?? $value['cpa_price'];
            $value['cps_price'] = $id_price[$value['id']]['cps_price'] ?? $value['cps_price'];
            $value['cpc_price'] = $id_price[$value['id']]['cpc_price'] ?? $value['cpc_price'];
            $value['cpd_price'] = $id_price[$value['id']]['cpd_price'] ?? $value['cpd_price'];
            $money = ($value['cpa_price'] * $value['cpa_amount'] + $value['cps_price'] * $value['cps_amount'] + $value['cpc_price'] * $value['cpc_amount'] + $value['cpd_price'] * $value['cpd_amount']);
            $tmp['money'] = sprintf('%.2f', $money);
            $update_data[] = $tmp;
            $project_cpa_amount += $value['cpa_amount'];
            $project_cps_amount += $value['cps_amount'];
            $project_money += $tmp['money'];
        }
        if(!empty($update_data)){
            $link_feedback->updateBatch($update_data);
        }
        $feedback_info->cpc_price = $project_cpc_price;//一个项目一天 cpc只有一个单价 无论多少条链接
        $feedback_info->cpd_price = $project_cpd_price;//一个项目一天 cpd只有一个单价 无论多少条链接
        $feedback_info->cpa_amount = $project_cpa_amount;
        $feedback_info->cps_amount = $project_cps_amount;
        $feedback_info->money = sprintf('%.2f', $project_money);
        return $feedback_info->save();//更新每天结算的项目反馈表
        
    }

    //检查当前项目当天是否有汇总记录和链接反馈记录
    public function checkProjectLinkFeedback($project_id=0, $date=''){
        if($project_id == 0 || $date == '') return ['code'=>0,'message'=>'请输入有效项目和日期'];
        $project = new \App\Models\BusinessProject;
        $feedback = new \App\Models\ProjectFeedback;
        $link_feedback = new \App\Models\LinkFeedback;
        $project_link_log = new \App\Models\ProjectLinkLog;
        $project_info = $project->where('id', $project_id)->first();
        $project_feedback_info = $feedback->where('project_id', $project_id)->where('date', $date)->first();
        if(!empty($project_feedback_info)){
            return ['code'=>1];
        }else{
            //不存在的情况
            //当前项目在这一天没有汇总记录时  查看链接分配记录表 找到链接 再找链接对应的价格  然后生成链接反馈表记录  最后生成反馈汇总记录
            $project_link_date_info = $project_link_log->getProjectLinkDateInfo($project_id, $date);
            if(!empty($project_link_date_info)){
                $link_price_log = new \App\Models\BusinessOrderPrice;
                $link_feedback_insert = $project_feedback_insert = [];
                $project_cpc_price = $project_cpd_price = 0;
                foreach ($project_link_date_info as $key => $value) {
                    $link_price_info = $link_price_log->where('link_id', $value['link_id'])->where('start_time','<=',strtotime($date))->where('end_time','>',strtotime($date))->orderBy('created_at','desc')->first();
                    if(empty($link_price_info)){
                       $link_price_info = $link_price_log->where('link_id', $value['link_id'])->where('start_time','=',0)->where('end_time','=',0)->where('created_at', '<=', $date.' 23:59:59')->orderBy('created_at','desc')->first();
                       //如果价格记录表里面没有符合条件的记录[没有修改过价格时]  则取链接表里面的数据
                       if(empty($link_price_info)){
                            $link_price_info = (new \App\Models\BusinessOrderLink)->where('id', $value['link_id'])->first();
                            $link_price_info['link_id'] = $link_price_info->id;
                       }
                    }
                    if(!empty($link_price_info)){
                        $link_feedback_tmp = [];
                        $market_price = unserialize($link_price_info->market_price);
                        if($link_price_info->pricing_manner == 'CPA'){
                            $link_feedback_tmp['cpa_price'] = $market_price['CPA'];
                            $link_feedback_tmp['cpa_amount'] = 0;
                        }elseif($link_price_info->pricing_manner == 'CPS'){
                            $link_feedback_tmp['cps_price'] = $market_price['CPS'];
                            $link_feedback_tmp['cps_amount'] = 0;
                        }elseif($link_price_info->pricing_manner == 'CPC'){
                            $link_feedback_tmp['cpc_price'] = $project_cpc_price = $market_price['CPC'];
                            $link_feedback_tmp['cpc_amount'] = 0;
                        }elseif($link_price_info->pricing_manner == 'CPD'){
                            $link_feedback_tmp['cpd_price'] = $project_cpd_price = $market_price['CPD'];
                            $link_feedback_tmp['cpd_amount'] = 0;
                        }elseif($link_price_info->pricing_manner == 'CPA+CPS'){
                            $link_feedback_tmp['cpa_price'] = $market_price['CPA'];
                            $link_feedback_tmp['cps_price'] = $market_price['CPS'];
                            $link_feedback_tmp['cpa_amount'] = 0;
                            $link_feedback_tmp['cps_amount'] = 0;
                        }
                        $link_feedback_tmp['money'] = 0;
                        $link_feedback_tmp['link_id'] = $link_price_info->link_id;
                        $link_feedback_tmp['project_id'] = $project_id;
                        $link_feedback_tmp['date'] = $date;
                        $link_feedback_tmp['user_id'] = auth()->user()->id ?? 0;
                        $link_feedback_tmp['created_at'] = date('Y-m-d H:i:s');
                        $link_feedback_tmp['updated_at'] = date('Y-m-d H:i:s');
                        $link_feedback_insert[] = $link_feedback_tmp;
                    }else{
                        return ['code' => 0, 'message' => '无法获取当天单价'];
                    }
                }
                
                $project_feedback_insert['project_id'] = $project_id;
                $project_feedback_insert['user_id'] = auth()->user()->id ?? 0;
                $project_feedback_insert['month'] = date('Ym', strtotime($date));
                $project_feedback_insert['date'] = $date;
                $project_feedback_insert['cpa_price'] = 0;
                $project_feedback_insert['cps_price'] = 0;
                $project_feedback_insert['cpc_price'] = $project_cpc_price;
                $project_feedback_insert['cpd_price'] = $project_cpd_price;
                $project_feedback_insert['cpa_amount'] = 0;
                $project_feedback_insert['cps_amount'] = 0;
                $project_feedback_insert['cpc_amount'] = 0;
                $project_feedback_insert['cpd_amount'] = 0;
                $project_feedback_insert['money'] = 0;
                $project_feedback_insert['customer_id'] = $project_info->customer_id;
                $project_feedback_insert['created_at'] = date('Y-m-d H:i:s');
                $project_feedback_insert['updated_at'] = date('Y-m-d H:i:s');
                if(!empty($link_feedback_insert)){
                    $link_feedback->insert($link_feedback_insert);//创建链接反馈记录
                }
                if(!empty($project_feedback_insert)){
                    $feedback->insert($project_feedback_insert);//创建项目反馈记录
                }
            }else{
                return ['code' => 0, 'message' => '当前项目在'.$date.'无投放链接'];
            }
            return ['code'=>1];
        }
        
    }


    //根据项目id获取时间段内每天的cpc量/cpd量
    public function getFeedbackByProjectIdDay($project_id = 0, $start_date = '', $end_date = ''){
        if($project_id == 0 || $start_date == '' || $end_date == '') return [];
        $project_feedbacks = new ProjectFeedback;
        return $project_feedbacks->where('project_id', $project_id)->whereBetween('date', [$start_date, $end_date])->select(['date','cpc_price','cpd_price','cpc_amount','cpd_amount','money'])->get()->toArray();
    }

    //生成单价
    public function createFeedbackPrice($project_id, $date){
        $link_feedback = new \App\Models\LinkFeedback; 
        $link_feedback_list = $link_feedback->where('project_id', $project_id)->where('date', $date)->orderBy('id','desc')->get()->toArray();//当前项目当天所有链接  
        if(empty($link_feedback_list)){
            return;
        }
        $project_link_ids = $need_update_price_list = [];
        foreach ($link_feedback_list as $key => $value) {
            $project_link_ids[] = $value['link_id'];
            //如果一个单价都没有  就取最新价格
            if($value['cpa_price'] == 0 && $value['cps_price'] == 0 && $value['cpc_price'] == 0 && $value['cpd_price'] == 0){
                $need_update_price_list[] = $value;
            }
        }
        if(!empty($need_update_price_list)){
            //获取对应链接的单价
            $order_link_list = (new \App\Models\BusinessOrderLink)->whereIn('id', $project_link_ids)->select(['id','pricing_manner', 'market_price'])->get()->toArray();
            $link_price = [];
            foreach ($order_link_list as $key => $value) {
                $link_price[$value['id']]['pricing_manner'] = $value['pricing_manner'];
                $link_price[$value['id']]['market_price'] = unserialize($value['market_price']);
            }
            $update_price = $id_price = [];
            foreach ($need_update_price_list as $key => $value) {
                $tmp_price = [];
                $tmp_price['id'] = $value['id'];
                if($link_price[$value['link_id']]['pricing_manner'] == 'CPA'){
                    $tmp_price['cpa_price'] = $link_price[$value['link_id']]['market_price']['CPA'];
                    $id_price[$value['id']]['cpa_price'] = $tmp_price['cpa_price'];
                }elseif($link_price[$value['link_id']]['pricing_manner'] == 'CPS'){
                    $tmp_price['cps_price'] = $link_price[$value['link_id']]['market_price']['CPS'];
                    $id_price[$value['id']]['cps_price'] = $tmp_price['cps_price'];
                }elseif($link_price[$value['link_id']]['pricing_manner'] == 'CPA+CPS'){
                    $tmp_price['cpa_price'] = $link_price[$value['link_id']]['market_price']['CPA'];
                    $tmp_price['cps_price'] = $link_price[$value['link_id']]['market_price']['CPS'];
                    $id_price[$value['id']]['cpa_price'] = $tmp_price['cpa_price'];
                    $id_price[$value['id']]['cps_price'] = $tmp_price['cps_price'];
                }elseif($link_price[$value['link_id']]['pricing_manner'] == 'CPC'){
                    $tmp_price['cpc_price'] = $link_price[$value['link_id']]['market_price']['CPC'];
                    $id_price[$value['id']]['cpc_price'] = $tmp_price['cpc_price'];
                }elseif($link_price[$value['link_id']]['pricing_manner'] == 'CPD'){
                    $tmp_price['cpd_price'] = $link_price[$value['link_id']]['market_price']['CPD'];
                    $id_price[$value['id']]['cpd_price'] = $tmp_price['cpd_price'];
                }
                $update_price[] = $tmp_price;
                
            }
            if(!empty($update_price)){
                $link_feedback->updateBatch($update_price);
            }
        }
        return true;
    }

    
}

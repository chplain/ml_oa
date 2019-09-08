<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class V3ProjectTaskStat extends Model
{
    // 同步v3数据表
    protected $table = 'v3_project_task_stats';

    protected $guarded = [];

    //获取v3模板数据
    public function V3ProjectTaskTpl()
    {
        return $this->hasOne('App\Models\V3ProjectTaskTpl', 'tpl_id', 'tpl_id');
    }

    public function getQueryList($inputs){
        $query_where = $this->when(isset($inputs['project_ids']) && is_array($inputs['project_ids']), function($query)use($inputs){
                        $query->whereIn('project_id', $inputs['project_ids']);
                    })
                    ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query)use($inputs){
                        $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                    });
        $list = $query_where->select(['project_id','date','send_count','succ_count','open_count_email_uv','click_count_email_uv'])->get(); 
        return $list;
    }

    //根据时间管查询v3数据
    public function getProjectToData($start_date, $end_date, $project_ids)
    {
        $v3_project = new V3ProjectTaskStat;
        $v3_list = $v3_project->whereIn('project_id', $project_ids)->whereBetween('date', [$start_date, $end_date])
            ->select(DB::raw('`project_id`,`date`, SUM(`succ_count`) AS succ_count, SUM(`send_count`) AS send_count'))
            ->groupBy('date')->groupBy('project_id')->get();
        return $v3_list;
    }

    //根据时间获取v3数据
    public static function getDataByTime($project_id, $date)
    {
        $v3_project = new V3ProjectTaskStat;
        $v3_list = $v3_project->where('project_id', $project_id)->where('date', $date)
            ->select(DB::raw('`project_id`,SUM(`succ_count`) AS succ_count, SUM(`send_count`) AS send_count,SUM(`open_count_pv`) AS open_count_pv, SUM(`click_count_pv`) AS click_count_pv,SUM(`open_count_email_uv`) AS open_count_email_uv, SUM(`click_count_email_uv`) AS click_count_email_uv,SUM(`open_count_ip_uv`) AS open_count_ip_uv, SUM(`click_count_ip_uv`) AS click_count_ip_uv,SUM(`complaint_count_email_uv`) AS complaint_count_email_uv,SUM(`unsubscribe_count_email_uv`) AS unsubscribe_count_email_uv,SUM(`soft_fail_count`) AS soft_fail_count,SUM(`hard_fail_count`) AS hard_fail_count,SUM(`timeout_count`) AS timeout_count,SUM(`p_send_count`) AS p_send_count,SUM(`p_succ_count`) AS p_succ_count'))
            ->groupBy('project_id')
            ->first()->toArray();
        return $v3_list;
    }

    //每两个小时统计一次发送量等
    public static function calProjectDateStat($project_info = [],$date = 0, $if_cpc_cpd = ''){
        if(empty($project_info) || $date == 0) return;
        $feedback = new \App\Models\ProjectFeedback;
        $feedback_project_ids = $feedback->where('date', $date)->pluck('project_id')->toArray();
        if(!in_array($project_info['id'], $feedback_project_ids)){
            //项目汇总里面没有今天的记录  则创建记录
            $feedback->checkProjectLinkFeedback($project_info['id'], $date);
        }
        //判断是否有价格  没有价格则添加价格
        $feedback->createFeedbackPrice($project_info['id'], $date);
        //今天反馈回来的所有项目id
        $v3_task_stat_data = self::getDataByTime($project_info['id'], date('Ymd', strtotime($date)));

        $feedback_info = $feedback->where('project_id',$project_info['id'])->where('date', $date)->first();
        $feedback_info->send_amount = $v3_task_stat_data['send_count'];
        $feedback_info->succ_amount = $v3_task_stat_data['succ_count'];
        $feedback_info->open_amount = $v3_task_stat_data['open_count_pv'];
        $feedback_info->open_inde_amount = $v3_task_stat_data['open_count_email_uv'];
        $feedback_info->open_ip_inde_amount = $v3_task_stat_data['open_count_ip_uv'];
        $feedback_info->click_amount = $v3_task_stat_data['click_count_pv'];
        $feedback_info->click_inde_amount = $v3_task_stat_data['click_count_email_uv'];
        $feedback_info->click_ip_inde_amount = $v3_task_stat_data['click_count_ip_uv'];
        $feedback_info->p_send_amount = $v3_task_stat_data['p_send_count'];
        $feedback_info->p_succ_amount = $v3_task_stat_data['p_succ_count'];
        $feedback_info->soft_fail = $v3_task_stat_data['soft_fail_count'];
        $feedback_info->hard_fail = $v3_task_stat_data['hard_fail_count'];
        $feedback_info->timeout_amount = $v3_task_stat_data['timeout_count'];
        $feedback_info->email_complaint_uv = $v3_task_stat_data['complaint_count_email_uv'];
        $feedback_info->email_unsubscribe_uv = $v3_task_stat_data['unsubscribe_count_email_uv'];
        //自动统计cpc、cpd量
        $if_cpc = $if_cpd = 0;
        if($if_cpc_cpd == 'cpc_cpd' && ($feedback_info->cpc_price > 0 || $feedback_info->cpd_price > 0)){
            $suspend = new \App\Models\BusinessProjectSuspend;
            $allowance_date = $project_info['allowance_date'];
            if($allowance_date > 0){
                //余量计算大于0时  暂停时间超过余量计算天数  则不再统计
                $sdate = date('Y-m-d', strtotime("$date -$allowance_date day"));//计算余量天数
                $count = $suspend->where('project_id', $project_info['id'])->whereBetween('date', [$sdate, $date])->count();
                if($count == 0 && $project_info['status'] != 1) {
                    //不在投递中等项目并且没有暂停记录的  不统计
                }else{
                    if($feedback_info->cpc_price > 0){
                        if($count <= $allowance_date){
                            $feedback_info->cpc_amount = $if_cpc = $feedback_info->click_inde_amount;
                        }
                    }
                    if($feedback_info->cpd_price > 0){
                        if($allowance_date <= $count ){
                            $feedback_info->cpd_amount = $if_cpd = $feedback_info->succ_amount;
                        }
                    }
                }
            }else{
                //余量计算天数=0时  只看当天是否暂停
                $count = $suspend->where('project_id', $project_info['id'])->where('date', $date)->count();
                if($count == 0 && $project_info['status'] != 1) {
                    //不在投递中等项目并且没有暂停记录的  不统计
                }else{
                    if($count == 0){
                        if($feedback_info->cpc_price > 0){
                            $feedback_info->cpc_amount = $if_cpc = $feedback_info->click_inde_amount;
                        }
                        if($feedback_info->cpd_price > 0){
                            $feedback_info->cpd_amount = $if_cpd = $feedback_info->succ_amount;
                        }
                    }
                }
            }
        }
        $feedback_info->save();
        if($if_cpc > 0 || $if_cpd > 0){
            $feedback->updateProjectIncome($project_info['id'], $date);//重新计算金额
        }
    }

}

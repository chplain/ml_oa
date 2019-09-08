<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sdoa extends Model
{
    //将旧oa数据转移到新oa上面
    protected $connection = 'mysql_old';//链接旧oa数据库


    public function getClientList(){
        $this->table = "client";//客户表
        return $this->get()->toArray();

    }

    public function getUserList(){
        $this->table = "adminuser";//用户表
        return $this->select(['uid','username','realname'])->get()->toArray();

    }

    public function getSellerList(){
        $this->table = "seller";//销售表
        return $this->select(['id','name'])->get()->toArray();

    }

    public function getContractList(){
        $this->table = "contract";//合同表
        return $this->get()->toArray();

    }

    public function getIndustryList(){
        $this->table = "industry";//行业表
        return $this->get()->toArray();

    }

    public function getInvoiceClientList(){
        $this->table = "invoice_client";//开票公司表
        return $this->get()->toArray();

    }

    public function getProjectList($order_model, $project_model, $prices_model, $group_model, $user_user, $user_seller,$link_model,$project_link_log_model){
        $this->table = "project";//项目表
        $order = $order_model;//商务单
        $project = $project_model;//项目
        $prices = $prices_model;//单价
        $link = $link_model;//链接
        $link_log = $project_link_log_model;//链接
        $group = $group_model;//组段
        $group_list = $group->get()->toArray();
        $this->chunk(200, function ($query) use ($order,$project,$prices,$group_list,$user_user, $user_seller,$link,$link_log){
            $project_ids = [];
            foreach ($query as $value) {
                $project_ids[] = $value['id'];
            }
            $change_data = $this->changePriceLog($project_ids);
            
            foreach ($query as $key => $value) {
                $order_insert = array();
                $project_insert = array();
                $prices_insert = array();
                //商务单 ===start===
                $max_id = $order->max('id');
                $max_id = $max_id ? $max_id : 0;
                $swd_id = $max_id + 1;
                if(strlen($swd_id) <= 4){
                    $n = 4;
                }else{
                    $n = strlen($swd_id);
                }
                $order_insert['user_id'] = $user_user[$value['bill_uid']] ?? 1;
                $order_insert['swd_id'] = 'SWD'.str_pad($swd_id, $n, '0', STR_PAD_LEFT);
                $order_insert['customer_id'] = $value['cid'];
                $order_insert['project_name'] = $value['project_name'];
                $order_insert['project_type'] = 1;//项目类型 平台 非平台
                $order_insert['project_sale'] = $user_seller[$value['seller_id']] ?? 1;
                $order_insert['project_business'] = 1;
                $order_insert['trade_id'] = $value['industry_id'];
                $order_insert['test_cycle'] = '';//测试周期
                $order_insert['direct_area'] = '';//定向区域
                $order_insert['remarks'] = $value['remark'];
                $order_insert['settlement_type'] = $value['pay_type'];//结算方式
                $order_insert['definition'] = '';//有效定义  
                $order_insert['feedback'] = '';//反馈周期
                $order_insert['get_data'] = '';//可获得数据
                $order_insert['tpl_demand'] = '';//模板要求
                $order_insert['if_verify'] = 0;//模板是否要审核
                $order_insert['if_has'] = 0;//是否有logo
                $order_insert['logo_demand'] = '';//logo要求
                $order_insert['website'] = '';//官网
                $order_insert['theme'] = '';//活动/主题
                $order_insert['feature'] = '';//产品特征
                $order_insert['other'] = '';//其它素材
                $order_insert['verify_user_id'] = 1;//审核人id
                $order_insert['status'] = 4;
                $order_insert['created_at'] = date('Y-m-d H:i:s', time());
                $order_insert['updated_at'] = date('Y-m-d H:i:s', time());
                $order_insert['is_old_oa'] = 1;
                $order_id = $order->insertGetId($order_insert);
                if(!$order_id){
                    return ['code'=>0,'message'=>'商务单插入失败'];
                }
                //商务单 ===end===
                //项目 ===start===
                $project_insert['id'] = $value['id'];
                $project_insert['order_id'] = $order_id;
                $project_insert['customer_id'] = $value['cid'];
                $project_insert['trade_id'] = $value['industry_id'];
                $project_insert['project_name'] = $value['project_name'];
                $project_insert['sale_man'] = $user_seller[$value['seller_id']] ?? 1;//销售
                $project_insert['charge_id'] = $user_user[$value['person_charge_id']] ?? 1;//项目负责人
                $project_insert['execute_id'] = $user_user[$value['executor_id']] ?? 1;//执行人
                $project_insert['business_id'] = 1;//商务
                $project_insert['assistant_id'] = 1;//商务助理
                $project_insert['if_check'] = $value['is_assess'];//是否是考核项目
                $project_insert['if_xishu'] = 0;//是否是洗数项目
                $project_insert['send_group'] = $value['group_segment'];//发送组段
                $project_insert['deliver_type'] = $value['trash'];//投递类型
                $project_insert['cooperation_cycle'] = $value['coop_cycle'];//合作周期
                $project_insert['allowance_date'] = $value['margin_planning_day'];//余量计算天数
                $project_insert['status'] = $value['run_state'] == 3 ? 2 : $value['run_state'];//执行状态  将原来的暂停改为终止
                $project_insert['has_v3'] = 0;
                $project_insert['user_id'] = $user_user[$value['bill_uid']] ?? 1;
                if(!empty($value['send_group_name'])){
                    foreach ($group_list as $g) {
                        if($g['name'] == $value['send_group_name']){
                            $project_insert['group_id'] = $g['id'];
                        }
                    }
                }else{
                    $project_insert['group_id'] = 0;
                }
                $project_insert['created_at'] = date('Y-m-d H:i:s', $value['timestamp']);
                $project_insert['updated_at'] = date('Y-m-d H:i:s', time());
                $project_insert['is_old_oa'] = 1;
                if(mb_strpos($value['project_name'],'触') !== false){
                    $project_insert['resource_type'] = 2;//触发
                }else if(mb_strpos($value['project_name'],'中腾信') !== false || mb_strpos($value['project_name'],'民生') !== false || mb_strpos($value['project_name'],'中信') !== false){
                    $project_insert['resource_type'] = 3;//特殊组段
                }else{
                    $project_insert['resource_type'] = 1;//正常
                }
                if(mb_strpos($value['project_name'],'技术') !== false){
                    $project_insert['income_main_type'] = 2;//技术
                }else{
                    $project_insert['income_main_type'] = 1;//神灯
                }
                $project_result = $project->insert($project_insert);
                if(!$project_result){
                    return response(['code'=>0,'message'=>'单价表插入失败']);
                }
                //项目 ===end===

                $link_insert = [];
                $link_insert['order_id'] = $order_id;
                $link_insert['project_id'] = $value['id'];
                $link_insert['link_type'] = 2;
                $link_insert['link_name'] = '默认连接';
                $link_insert['pc_link'] = '';
                $link_insert['wap_link'] = '';
                $link_insert['zi_link'] = 'https://oa.irading.com/';
                $link_insert['remarks'] = '旧oa数据转移';
                $link_insert['if_use'] = 1;//启用
                if($value['cpc_price'] > 0){
                    $link_insert['pricing_manner'] = 'CPC';//计价方式
                    $market_price = serialize(['CPC'=> $value['cpc_price']]);
                    $link_insert['market_price'] = $market_price;
                }
                if($value['cpd_price'] > 0){
                    $link_insert['pricing_manner'] = 'CPD';//计价方式
                    $market_price = serialize(['CPD'=> $value['cpd_price']]);
                    $link_insert['market_price'] = $market_price;
                }
                if($value['cpa_price'] > 0 && $value['cps_price'] > 0){
                    //CPA+CPS模式
                    $link_insert['pricing_manner'] = 'CPA+CPS';//计价方式
                    $market_price = serialize(['CPA'=> $value['cpa_price'],'CPS'=> $value['cps_price']]);
                    $link_insert['market_price'] = $market_price;
                }elseif($value['cpa_price'] > 0 && $value['cps_price'] == 0){
                    $link_insert['pricing_manner'] = 'CPA';//计价方式
                    $market_price = serialize(['CPA'=> $value['cpa_price']]);
                    $link_insert['market_price'] = $market_price;
                }elseif($value['cps_price'] > 0 && $value['cpa_price'] == 0){
                    $link_insert['pricing_manner'] = 'CPS';//计价方式
                    $market_price = serialize(['CPS'=> $value['cps_price']]);
                    $link_insert['market_price'] = $market_price;
                }
                $link_insert['created_at'] = $value['timestamp'] > 0 ? date('Y-m-d H:i:s', $value['timestamp']) : date('Y-m-d H:i:s');
                $link_insert['updated_at'] = date('Y-m-d H:i:s');
                $link_id = $link->insertGetId($link_insert);
                //生成一条默认链接 ===end===
                if(!$link_id){
                    return ['code'=>0,'message'=>'默认链接生成失败'];
                }

                //生成链接分配记录
                $project_link_log_insert = [];
                $project_link_log_insert['link_id'] = $link_id;
                $project_link_log_insert['project_id'] = $value['id'];
                $project_link_log_insert['start_time'] = $value['timestamp'];//从生产项目开始分配链接
                $project_link_log_insert['end_time'] = 0;
                $project_link_log_insert['created_at'] = date('Y-m-d H:i:s');
                $project_link_log_insert['updated_at'] = date('Y-m-d H:i:s');
                $log_id = $link_log->insertGetId($project_link_log_insert);
                //生成一条链接分配记录 ===end===
                if(!$log_id){
                    return ['code'=>0,'message'=>'链接分配记录生成失败'];
                }

                //单价 ===start===
                $prices_insert = [];
                if(isset($change_data[$value['id']])){
                    //更改记录表存在数据的时候
                    $pre_ch_value = [];
                    foreach ($change_data[$value['id']] as $ch_id => $ch_value) {
                        // dd($ch_value['value']);
                        $new_pricing_manner = $old_pricing_manner = $old_market_price = $new_market_price = [];
                        foreach ($ch_value['value'] as $k => $v) {
                            $a = explode('-', $v['price_type_text']);
                            if($v['new_price'] == 0){
                                $old_pricing_manner[] = $a[0];//旧的计价方式
                                $old_market_price[$a[0]] = $v['old_price'];
                            }else{
                                $new_pricing_manner[] = $a[0];//新的
                                $new_market_price[$a[0]] = $v['new_price'];
                            }
                        }
                        $tmp = [];
                        $tmp['link_id'] = $link_id;
                        $tmp['order_id'] = $order_id;
                        $tmp['old_pricing_manner'] = $old_pricing_manner ? implode('+', $old_pricing_manner) : '';
                        $tmp['old_market_price'] = $old_market_price ? serialize($old_market_price) : '';
                        $tmp['pricing_manner'] = $new_pricing_manner ? implode('+', $new_pricing_manner) : '';
                        $tmp['market_price'] = $new_market_price ? serialize($new_market_price) : '';
                        $tmp['remarks'] = '从旧oa转移到新oa';
                        $tmp['start_time'] = $ch_value['log']['start_time'];
                        $tmp['end_time'] = $ch_value['log']['end_time'];
                        $tmp['user_id'] = $user_user[$ch_value['log']['opt_uid']] ?? 1;
                        $tmp['created_at'] = date('Y-m-d H:i:s', $ch_value['log']['opt_time']);
                        $tmp['updated_at'] = date('Y-m-d H:i:s', time());
                        $tmp['is_old_oa'] = 1;
                        $tmp['notice_user_ids'] = '';
                        $prices_insert[] = $tmp; 
                    }
                    
                }else{
                    //没有修改记录的项目  创建一条
                    $tmp = [];
                    $tmp['link_id'] = $link_id;
                    $tmp['order_id'] = $order_id;
                    $tmp['old_pricing_manner'] = '';
                    $tmp['old_market_price'] = '';
                    $tmp['pricing_manner'] = $link_insert['pricing_manner'];
                    $tmp['market_price'] = $link_insert['market_price'];
                    $tmp['remarks'] = '从旧oa转移到新oa，没有修改记录的，自动创建';
                    $tmp['start_time'] = 0;
                    $tmp['end_time'] = 0;
                    $tmp['user_id'] = 1;
                    $tmp['created_at'] = $value['timestamp'] > 0 ? date('Y-m-d H:i:s', $value['timestamp']) : date('Y-m-d H:i:s');
                    $tmp['updated_at'] = date('Y-m-d H:i:s');
                    $tmp['is_old_oa'] = 1;
                    $tmp['notice_user_ids'] = '';
                    $prices_insert[] = $tmp;
                }
                $created_at = array_column($prices_insert,'created_at');
                array_multisort($created_at,SORT_ASC,$prices_insert);//根据原操作时间来排序
                $prices_insert = array_chunk($prices_insert, 200);
                foreach ($prices_insert as $ins) {
                    $prices_result = $prices->insert($ins);
                    if(!$prices_result){
                        return ['code'=>0,'message'=>'单价表插入失败'];
                    }
                }
                
            }
            
        });
        return ['code'=>1, 'message'=>'插入成功'];
        //商务单、项目、单价 ===end===

    }

    public function getPlanList(){
        $this->table = "project_send_plan";//日投递计划表
        return $this->get()->toArray();

    }

    public function getFeedbackList($user_user,$link_project,$feedback_model,$link_feedback_model){
        $this->table = "consume_log";//数据反馈
        $this->chunk(200, function ($query) use ($user_user,$link_project,$feedback_model,$link_feedback_model){
            $feedback_data = $link_feedback_data = array();
            foreach ($query as $value) {
                $tmp = $link_tmp = array();
                //$tmp['link_id'] = $link_project[$value['project_id']];//链接id
                $tmp['project_id'] = $value['project_id'];
                $tmp['user_id'] = $user_user[$value['opt_uid']] ?? 1;
                $tmp['month'] = $value['month'];
                $tmp['date'] = date('Y-m-d', $value['date']);
                $tmp['is_old_oa'] = 1;
                $tmp['cpa_price'] = $value['cpa_price'];
                $tmp['cpa_amount'] = $value['cpa_amount'];
                $tmp['cps_price'] = $value['cps_price'];
                $tmp['cps_amount'] = $value['cps_amount'];
                $tmp['cpc_price'] = $value['cpc_price'];
                $tmp['cpc_amount'] = $value['cpc_amount'];
                $tmp['cpd_price'] = $value['cpd_price'];
                $tmp['cpd_amount'] = $value['cpd_amount'];
                $tmp['money'] = $value['money'];
                $tmp['customer_id'] = $value['cid'];
                $tmp['send_amount'] = $value['send_amount'];
                $tmp['succ_amount'] = $value['succ_amount'];
                $tmp['open_amount'] = $value['open_amount'];
                $tmp['open_inde_amount'] = $value['open_inde_amount'];
                $tmp['click_amount'] = $value['click_amount'];
                $tmp['click_inde_amount'] = $value['click_inde_amount'];
                $tmp['open_ip_inde_amount'] = $value['open_ip_inde_amount'];
                $tmp['click_ip_inde_amount'] = $value['click_ip_inde_amount'];
                $tmp['gross_reg_amount'] = $value['gross_reg_amount'];
                $tmp['p_send_amount'] = $value['p_send_amount'];
                $tmp['p_succ_amount'] = $value['p_succ_amount'];
                $tmp['soft_fail'] = $value['soft_fail'];
                $tmp['hard_fail'] = $value['hard_fail'];
                $tmp['timeout_amount'] = $value['timeout_amount'];
                $tmp['email_complaint_uv'] = $value['email_complaint_uv'];
                $tmp['email_unsubscribe_uv'] = $value['email_unsubscribe_uv'];
                $tmp['intercept'] = $value['intercept'];
                $tmp['real_psend'] = $value['real_psend'];
                if($value['timestamp'] > 0){
                    $tmp['created_at'] = date('Y-m-d H:i:s', $value['timestamp']);
                }else{
                    $tmp['created_at'] = date('Y-m-d H:i:s', time());
                }
                $tmp['updated_at'] = date('Y-m-d H:i:s', time());
                $feedback_data[] = $tmp;

                $link_tmp['link_id'] = $link_project[$value['project_id']];
                $link_tmp['project_id'] = $value['project_id'];
                $link_tmp['date'] = date('Y-m-d', $value['date']);
                $link_tmp['cpa_price'] = $value['cpa_price'];
                $link_tmp['cpa_amount'] = $value['cpa_amount'];
                $link_tmp['cps_price'] = $value['cps_price'];
                $link_tmp['cps_amount'] = $value['cps_amount'];
                $link_tmp['cpc_price'] = $value['cpc_price'];
                $link_tmp['cpc_amount'] = $value['cpc_amount'];
                $link_tmp['cpd_price'] = $value['cpd_price'];
                $link_tmp['cpd_amount'] = $value['cpd_amount'];
                $link_tmp['money'] = $value['money'];
                if($value['timestamp'] > 0){
                    $link_tmp['created_at'] = date('Y-m-d H:i:s', $value['timestamp']);
                }else{
                    $link_tmp['created_at'] = date('Y-m-d H:i:s', time());
                }
                $link_tmp['updated_at'] = date('Y-m-d H:i:s', time());
                $link_feedback_data[] = $link_tmp;
                
            }
            $inser_feedback = $feedback_model->insert($feedback_data);
            $inser_link_feedback = $link_feedback_model->insert($link_feedback_data);
            if(!$inser_feedback && !$inser_link_feedback){
                return ['code'=>0,'message'=>'数据反馈插入失败'];
            }
        });
        return ['code'=>1, 'message'=>'插入成功'];

    }

    public function getProjectTask($task_ids){
        $this->table = "project_task";//任务表
        $task_list = $this->whereIn('id', $task_ids)->get()->toArray();
        return $task_list;
    }
    
    public function changePriceLog($project_ids){
        $this->table = "project_price_change_log";//价格变动记录表
        $log_list = $this->whereIn('project_id', $project_ids)->orderBy('change_id', 'asc')->get()->toArray();
        $change_ids = [];
        foreach ($log_list as $key => $value) {
            $change_ids[] = $value['change_id'];
        }
        $value_list = $this->changePriceValue($change_ids);
        $change_data = [];
        foreach ($log_list as $key => $value) {
            $tmp = [];
            foreach ($value_list as $k => $v) {
                $tmp[$v['change_id']][] = $v;
            }
            if(isset($tmp[$value['change_id']])){
                $change_data[$value['project_id']][$value['change_id']]['log'] = $value;
                $change_data[$value['project_id']][$value['change_id']]['value'] = $tmp[$value['change_id']];
            }
        }
        return $change_data;
    }
    public function changePriceValue($change_ids){
        $this->table = "project_price_change_value";//价格变动记录表
        $value_list = $this->whereIn('change_id', $change_ids)->orderBy('id', 'asc')->get()->toArray();
        return $value_list;
    }

    public function getProjectTaskStat($v3_task_stat){
        $this->table = "project_task_stat";//任务反馈数据
        $this->where('day', '20190620')->chunk(1000, function ($query) use ($v3_task_stat){
            $task_ids = [];
            foreach ($query as $value) {
                $task_ids[] = $value['oa_task_id'];
            }
            $task_list = $this->getProjectTask($task_ids);
            $task_data = [];
            foreach ($task_list as $key => $value) {
                $task_data[$value['id']]['task_name'] = $value['task_name'];
                $task_data[$value['id']]['tpl_id'] = $value['tpl_id'];
                $task_data[$value['id']]['v3_identity'] = $value['v3_identity'];
                $task_data[$value['id']]['v3_jump_page'] = $value['v3_jump_page'];
                $task_data[$value['id']]['v3_autotest_emails'] = $value['v3_autotest_emails'];
            }
            $task_insert = [];
            foreach ($query as $value) {
                $tmp = [];
                $tmp['id'] = $value['id'];
                $tmp['task_id'] = $value['task_id'];
                $tmp['project_id'] = $value['project_id'];
                $tmp['name'] = $task_data[$value['oa_task_id']]['task_name'];
                $tmp['date'] = $value['day'];
                $tmp['start_time'] = $value['send_time'];
                $tmp['send_count'] = $value['send_amount'];
                $tmp['succ_count'] = $value['succ_amount'];
                $tmp['open_count_pv'] = $value['open_pv'];
                $tmp['click_count_pv'] = $value['click_pv'];
                $tmp['open_count_email_uv'] = $value['email_open_uv'];
                $tmp['click_count_email_uv'] = $value['email_click_uv'];
                $tmp['open_count_ip_uv'] = $value['ip_open_uv'];
                $tmp['click_count_ip_uv'] = $value['ip_click_uv'];
                $tmp['server_group'] = $value['send_group_name'];
                $tmp['complaint_count_email_uv'] = $value['email_complaint_uv'];
                $tmp['unsubscribe_count_email_uv'] = $value['email_unsubscribe_uv'];
                $tmp['soft_fail_count'] = $value['soft_fail'];
                $tmp['hard_fail_count'] = $value['hard_fail'];
                $tmp['identity'] = $task_data[$value['oa_task_id']]['v3_identity'];
                $tmp['jump_page'] = $task_data[$value['oa_task_id']]['v3_jump_page'];
                $tmp['timeout_count'] = $value['timeout_amount'];
                $tmp['autotest_emails'] = $task_data[$value['oa_task_id']]['v3_autotest_emails'];
                $tmp['tpl_id'] = $task_data[$value['oa_task_id']]['tpl_id'];
                $tmp['p_send_count'] = $value['p_send_amount'];
                $tmp['p_succ_count'] = $value['p_succ_amount'];
                $tmp['created_at'] = date('Y-m-d H:i:s');
                $tmp['updated_at'] = date('Y-m-d H:i:s');
                $task_insert[] = $tmp;
            }
            if(!empty($task_insert)){
                //写入新oa
                $inser_task = $v3_task_stat->insert($task_insert);
                if(!$inser_task){
                    return false;
                }
            }
        });
        return true;
    }

    //把旧oa的数据转移到新oa上
    public function sync($user_model,$customer_model,$contact_model,$trade_model,$receipt_model,$order_model,$project_model,$prices_model,$group_model,$plan_model,$feedback_model,$link_model,$link_feedback_model,$project_link_log_model){
        set_time_limit(0);
        ini_set('memory_limit', '1024M');//临时设置内存
        $sdoa = new Sdoa;

        $old_user_list = $sdoa->getUserList();//用户
        $seller = $sdoa->getSellerList();//销售员
        $user_list = $user_model->select(['id', 'realname'])->get()->toArray();
        $list = $sdoa->getClientList();//客户
        //用户-用户
        $user_user = [];
        foreach ($old_user_list as $key => $value) {
            foreach ($user_list as $k => $v) {
                if ($value['realname'] == $v['realname']) {
                    $user_user[$value['uid']] = $v['id'];
                }
            }
        }

        //用户-销售
        $user_seller = [];
        foreach ($seller as $key => $value) {
            foreach ($user_list as $k => $v) {
                if ($value['name'] == $v['realname']) {
                    $user_seller[$value['id']] = $v['id'];
                }
            }
        }

        /*$customer = $customer_model;
        $client_data = array();
        foreach ($list as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value['cid'];
            $tmp['customer_type'] = $value['type'];
            $tmp['customer_name'] = $value['company_name'];
            $tmp['contacts'] = $value['link_man'];
            $tmp['customer_tel'] = $value['link_phone'];
            $tmp['customer_email'] = $value['email'];
            $tmp['customer_qq'] = $value['qq'];
            if(!empty($value['start_time'])){
                $tmp['start_time'] = date('Y-m-d',strtotime($value['start_time']));
            }else{
                $tmp['start_time'] = '';
            }
            $tmp['customer_address'] = $value['address'];
            $tmp['bank_accounts'] = $value['banking_account'];
            $tmp['start_time'] = date('Y-m-d', $value['timestamp']);
            $tmp['sale_user_id'] = $user_seller[$value['seller_id']] ?? 1;
            $tmp['created_at'] = date('Y-m-d H:i:s', $value['timestamp']);
            $tmp['updated_at'] = date('Y-m-d H:i:s', time());
            $tmp['is_old_oa'] = 1;
            $client_data[] = $tmp;
        }
        if(!empty($client_data)){
            //写入新oa
            $inser_client = $customer->insert($client_data);
            if(!$inser_client){
                return ['code'=>0,'message'=>'客户表插入失败'];
            }
        }
        //----------end 客户列表--------------

        $contact = $contact_model;
        $contract_list = $sdoa->getContractList();
        $customer_list = $client_data;
        $customer_data = array();
        foreach ($customer_list as $key => $value) {
            $customer_data[$value['id']] = $value['customer_name'];
        }
        // dd($contract_list);
        $contacts_data = array();
        foreach ($contract_list as $key => $value) {
            $tmp = array();
            $tmp['user_id'] = $user_user[$value['bill_uid']] ?? 1;//没有则默认超级管理员
            $tmp['customer_id'] = $value['cid'];
            $tmp['customer_name'] = $customer_data[$value['cid']] ?? '';
            $tmp['type'] = $value['contract_type'];
            $tmp['deadline'] = date('Y-m-d', $value['end_date']);
            $tmp['number'] = $value['contract_no'];
            $tmp['if_auto'] = $value['auto_child'];
            $tmp['file_url'] = $value['ele_contract_url'];
            $tmp['created_at'] = date('Y-m-d H:i:s',$value['timestamp']);
            $tmp['updated_at'] = date('Y-m-d H:i:s', time());
            $tmp['is_old_oa'] = 1;
            $contacts_data[] = $tmp;
        }
        if(!empty($contacts_data)){
            //写入新oa
            $inser_contact = $contact->insert($contacts_data);
            if(!$inser_contact){
                return ['code'=>0,'message'=>'合同表插入失败'];
            }
        }
        //----------end 合同列表--------------

        $trade = $trade_model;
        $industry_list = $sdoa->getIndustryList();
        $trade_data = array();
        foreach ($industry_list as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value['id'];
            $tmp['name'] = $value['name'];
            $tmp['parent_id'] = $value['pid'];
            $tmp['if_use'] = 1;
            $tmp['created_at'] = date('Y-m-d H:i:s', time());
            $tmp['updated_at'] = date('Y-m-d H:i:s', time());
            $tmp['is_old_oa'] = 1;
            $trade_data[] = $tmp;
        }
        if(!empty($trade_data)){
            //写入新oa
            $inser_trade = $trade->insert($trade_data);
            if(!$inser_trade){
                return ['code'=>0,'message'=>'行业表插入失败'];
            }
        }
        //----------end 行业列表--------------

        $receipt = $receipt_model;
        $invoice_list = $sdoa->getInvoiceClientList();
        $receipt_data = array();
        foreach ($invoice_list as $key => $value) {
            $tmp = array();
            $tmp['customer_id'] = $value['cid'];
            $tmp['name'] = $value['company_name'];
            $tmp['created_at'] = date('Y-m-d H:i:s', $value['timestamp']);
            $tmp['updated_at'] = date('Y-m-d H:i:s', time());
            $tmp['is_old_oa'] = 1;
            $receipt_data[] = $tmp;
        }
        if(!empty($receipt_data)){
            //写入新oa
            $inser_receipt = $receipt->insert($receipt_data);
            if(!$inser_receipt){
                return ['code'=>0,'message'=>'开票表插入失败'];
            }
        }*/


        //----------end 开票公司列表--------------
        /*$project_result = $this->getProjectList($order_model, $project_model, $prices_model, $group_model, $user_user, $user_seller,$link_model,$project_link_log_model);
        if($project_result['code'] != 1){
            return $project_result['message'];
        }*/
        


        //商务单、项目、单价 ===end===

        /*$plan = $plan_model;
        $plan_list = $sdoa->getPlanList(); 
        $plan_data = array();
        foreach ($plan_list as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value['id'];
            $tmp['project_id'] = $value['project_id'];
            $tmp['amount'] = $value['amount'];
            $tmp['real_amount'] = $value['real_amount'];
            $tmp['date'] = date('Y-m-d', strtotime($value['day']));
            $tmp['created_at'] = date('Y-m-d H:i:s', time());
            $tmp['updated_at'] = date('Y-m-d H:i:s', time());
            $tmp['is_old_oa'] = 1;
            $plan_data[] = $tmp;
        }
        if(!empty($plan_data)){
            //写入新oa
            $plan_data = array_chunk($plan_data, 200);
            foreach ($plan_data as $key => $value) {
                $inser_plan = $plan->insert($value);
            }
            if(!$inser_plan){
                return ['code'=>0,'message'=>'日投递计划插入失败'];
            }
        }*/
        
        
        //日投递计划 ===end===

        /*$link_data = $link_model->select(['id', 'project_id'])->get();
        $link_project = [];
        foreach ($link_data as $key => $value) {
            $link_project[$value['project_id']] = $value['id'];
        }
        $feedback_list = $sdoa->getFeedbackList($user_user, $link_project,$feedback_model,$link_feedback_model);
        */
        
        //数据反馈 ===end===

        return ['code' => 1, 'message' => '插入成功'];
    }



}

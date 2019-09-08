<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class BusinessProject extends Model
{
    //商务单表
    protected $table = 'business_projects';

    protected $client_api_key = 'KEU8#.*&%vda';

     // 关联商务单
    public function hasOrder()
    {
        return $this->belongsTo('App\Models\BusinessOrder', 'order_id', 'id');
    }

     // 关联链接
    public function hasLink()
    {
        return $this->hasMany('App\Models\BusinessOrderLink', 'project_id', 'id');
    }
    
     // 关联客户
    public function hasCustomer()
    {
        return $this->belongsTo('App\Models\BusinessOrderCustomer', 'customer_id', 'id');
    }

    // 关联行业
    public function trade()
    {
        return $this->belongsTo('App\Models\Trade', 'trade_id', 'id');
    }

    // 关联组段
    public function projectGroup()
    {
        return $this->belongsTo('App\Models\ProjectGroup', 'group_id', 'id');
    }

    // 关联用户
    public function saleUser()
    {
        return $this->belongsTo('App\Models\User', 'sale_man', 'id');
    }

    // 关联用户
    public function executeUser()
    {
        return $this->belongsTo('App\Models\User', 'execute_id', 'id');
    }

    //保存数据
    public function storeData($inputs){
    	$project = new BusinessProject;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
            //编辑
            $project = $project->where('id', $inputs['id'])->first();
            $project->status = $inputs['status'] ?? $project->status;
        }else{
            //新增
            $project->order_id = $inputs['id'];
            $project->customer_id = $inputs['customer_id'];
            $project->trade_id = $inputs['trade_id'];
            $project->sale_man = $inputs['project_sale'];
            $project->project_type = $inputs['project_type'];
            $project->status = 0;//待投递
            $project->user_id = auth()->user()->id;//添加人
        }
        $project->project_name = $inputs['project_name'];
        $project->charge_id = $inputs['charge_id'];
        $project->execute_id = $inputs['execute_id'];
        $project->business_id = $inputs['business_id'];//商务
        $project->assistant_id = $inputs['assistant_id'];//商务助理
        $project->if_check = $inputs['if_check'];
        $project->if_xishu = $inputs['if_xishu'] ?? 0;//是否为洗数项目
    	$project->send_group = 1;
        $project->group_id = $inputs['send_group'];
    	$project->deliver_type = $inputs['deliver_type'];
    	$project->cooperation_cycle = $inputs['cooperation_cycle'];
    	$project->allowance_date = $inputs['allowance_date'];
        $project->has_v3 = $inputs['has_v3'];
        $project->username = isset($inputs['username']) && $inputs['username'] ? $inputs['username'] : '';
        $project->password = isset($inputs['password']) && $inputs['password'] ? $inputs['password'] : '';
        $project->resource_type = $inputs['resource_type'] ?? 1;
        $project->income_main_type = $inputs['income_main_type'] ?? 1;
    	$project->save();
        //绑定链接  一个链接只能分配给一个项目  一个项目可以有多个链接
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
            //编辑
            $link = new \App\Models\BusinessOrderLink;
            if(isset($inputs['link_ids']) && empty($inputs['link_ids'])){
                $link->where('project_id', $inputs['id'])->update(['project_id'=>0, 'updated_at'=>date('Y-m-d H:i:s')]);
            }
            if(isset($inputs['link_ids']) && !empty($inputs['link_ids']) && is_array($inputs['link_ids'])){
                $has_exist = $link->where('project_id', $project->id)->pluck('id')->toArray();//已绑定当前项目的链接
                $untying_arr = [];//解绑
                $new_binding = [];//新绑定
                foreach ($has_exist as $key => $value) {
                    if(!in_array($value, $inputs['link_ids'])){
                        $untying_arr[] = $value;//解绑
                    }
                }
                foreach ($inputs['link_ids'] as $key => $value) {
                    if(!in_array($value, $has_exist)){
                        $new_binding[] = $value;//新绑定
                    }
                }
                if(!empty($untying_arr)){
                    $link->whereIn('id', $untying_arr)->update(['project_id'=>0, 'updated_at'=>date('Y-m-d H:i:s')]);
                }
                if(!empty($new_binding)){
                    $link->whereIn('id', $new_binding)->update(['project_id'=>$project->id, 'updated_at'=>date('Y-m-d H:i:s')]);
                    //创建连接分配日志
                    $this->createLinkLog($new_binding, $project->id);
                    systemLog('商务单', "分配链接id：".implode(',', $new_binding).'给项目id：'.$project->id);
                }

            }
        }else{
            //新增
            if(isset($inputs['link_ids']) && is_array($inputs['link_ids']) && !empty($inputs['link_ids'])){
                $link = new \App\Models\BusinessOrderLink;
                $link->whereIn('id', $inputs['link_ids'])->update(['project_id'=>$project->id, 'updated_at'=>date('Y-m-d H:i:s')]);
                //创建连接分配日志
                $this->createLinkLog($inputs['link_ids'], $project->id);
                systemLog('商务单', "分配链接id：".implode(',', $inputs['link_ids']).'给项目id：'.$project->id);
            }
        }
        //链接反馈表当天绑定的项目  更改前的项目和更改后的项目反馈需要重新统计
        if(isset($inputs['link_ids']) && is_array($inputs['link_ids']) && !empty($inputs['link_ids'])){
            $link_feedback = new \App\Models\LinkFeedback;
            $old_project_ids = $link_feedback->whereIn('link_id',$inputs['link_ids'])->where('date', date('Y-m-d'))->pluck('project_id')->toArray();
            $link_feedback->whereIn('link_id',$inputs['link_ids'])->where('date', date('Y-m-d'))->update(['project_id'=>$project->id]);
            $feedback = new \App\Models\ProjectFeedback;
            foreach($old_project_ids as $k=>$val){
                $feedback->updateProjectIncome($val, date('Y-m-d'));//重新计算之前绑定的项目
            }
            $feedback->updateProjectIncome($project->id, date('Y-m-d'));//计算新绑定的项目
        }

        //暂停日期
        if(isset($inputs['suspend_list']) && is_array($inputs['suspend_list']) && !empty($inputs['suspend_list'])){
            $suspend =  new \App\Models\BusinessProjectSuspend;
            $suspend_data = $suspend->where('project_id', $project->id)->get()->toArray();
            $suspend_list = array();
            foreach ($suspend_data as $key => $value) {
                $suspend_list[] = $value['date'];
            }
            sort($suspend_list);
            sort($inputs['suspend_list']);
            if(implode(',', $suspend_list) == implode(',', $inputs['suspend_list'])){
                //没有变化
            }else{
                if(empty($suspend_list) && !empty($inputs['suspend_list'])){
                    $insert = array();//新增
                    foreach ($inputs['suspend_list'] as $key => $value) {
                        $insert[$key]['project_id'] = $project->id;
                        $insert[$key]['date'] = $value;
                        $insert[$key]['created_at'] = date('Y-m-d H:i:s');
                        $insert[$key]['updated_at'] = date('Y-m-d H:i:s');
                    }
                    $result = $suspend->insert($insert);
                    if($result){
                        systemLog('项目汇总', '设置了项目-'.$project->project_name.'暂停日期:'.implode(',', $inputs['suspend_list']));
                        return response()->json(['code' => 1, 'message' => '操作成功']);
                    }
                }else{
                    $insert = array();//新增集
                    $delete = array();//删除集
                    $log_date = array();
                    foreach ($inputs['suspend_list'] as $key => $value) {
                        $tmp = array();
                        if(!in_array($value, $suspend_list)){
                            $tmp['project_id'] = $project->id;
                            $tmp['date'] = $value;
                            $tmp['created_at'] = date('Y-m-d H:i:s');
                            $tmp['updated_at'] = date('Y-m-d H:i:s');
                            $insert[] = $tmp;
                            $log_date[] = $value;
                        }
                    }
                    if(!empty($insert)){
                        $suspend->insert($insert);
                        systemLog('项目汇总', '设置了项目-'.$project->project_name.'暂停日期:'.implode(',', $log_date));
                    }
                    $log_date = array();
                    foreach ($suspend_data as $key => $value) {
                        if(!in_array($value['date'], $inputs['suspend_list'])){
                            $delete[] = $value['id'];
                            $log_date[] = $value['date'];
                        }
                    }
                    if(!empty($delete)){
                        $suspend->whereIn('id', $delete)->delete();
                        systemLog('项目汇总', '设置了项目-'.$project->project_name.'取消暂停日期:'.implode(',', $log_date));
                    }
                }
                
            }
        }
        
        return true;

    }

    //获取项目列表
    public function getDataList($inputs){        
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query =  $this->when(isset($inputs['ids']) && !empty($inputs['ids']), function ($query) use ($inputs){
                            $query->whereIn('id', $inputs['ids']);
                        })
                        ->when(isset($inputs['project_name']) && !empty($inputs['project_name']), function ($query) use ($inputs){
                            $query->where('project_name', 'like', '%'.$inputs['project_name'].'%');
                        })
                        ->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs){
                            $query->where('status', $inputs['status']);
                        })
                        ->when(isset($inputs['status_in']) && is_array($inputs['status_in']), function ($query) use ($inputs){
                            $query->whereIn('status', $inputs['status_in']);
                        })
                        ->when(isset($inputs['sale_user']) && !empty($inputs['sale_user']), function ($query) use ($inputs){
                            $query->whereHas('saleUser', function($query)use($inputs){
                                $query->where('realname', 'like', '%'.$inputs['sale_user'].'%');
                            });
                        })
                        ->when(isset($inputs['customer_name']) && !empty($inputs['customer_name']), function ($query) use ($inputs){
                            $query->whereHas('hasCustomer', function($query)use($inputs){
                                $query->where('customer_name', 'like', '%'.$inputs['customer_name'].'%');
                            });
                        })
                        ->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query)use($inputs){
                            $query->where('project_name', 'like', '%'.$inputs['keywords'].'%');
                        })
                        ->when(isset($inputs['user_ids']), function($query)use($inputs){
                            $query->whereIn('charge_id', $inputs['user_ids']);
                        })
                        ->when(isset($inputs['cooperation_cycle']) && is_numeric($inputs['cooperation_cycle']), function($query)use($inputs){
                            $query->where('cooperation_cycle', $inputs['cooperation_cycle']);;
                        })
                        ->when(isset($inputs['trade_id']) && is_numeric($inputs['trade_id']), function($query)use($inputs){
                            $query->where('trade_id', $inputs['trade_id']);
                        })
                        ->when(isset($inputs['charge_id']) && is_numeric($inputs['charge_id']), function($query)use($inputs){
                            $query->where('charge_id', $inputs['charge_id']);
                        })
                        ->when(isset($inputs['charge_or_execute']) && is_numeric($inputs['charge_or_execute']), function($query)use($inputs){
                            $query->where(function($query)use($inputs){
                                $query->where('charge_id', $inputs['charge_or_execute'])->orWhere('execute_id', $inputs['charge_or_execute']);
                            });
                        })
                        ->when(isset($inputs['charge_or_business']) && is_numeric($inputs['charge_or_business']), function($query)use($inputs){
                            $query->where(function($query)use($inputs){
                                $query->where('charge_id', $inputs['charge_or_business'])->orWhere('business_id', $inputs['charge_or_business']);
                            });
                        })
                        ->when(isset($inputs['charge_or_business_all']) && is_numeric($inputs['charge_or_business_all']), function($query)use($inputs){
                            $query->where(function($query)use($inputs){
                                $query->where('charge_id', $inputs['charge_or_business_all'])->orWhere('business_id', $inputs['charge_or_business_all'])->orWhere('execute_id', $inputs['charge_or_business_all'])->orWhere('assistant_id', $inputs['charge_or_business_all']);
                            });
                        })
                        ->when(isset($inputs['execute']) && !empty($inputs['execute']), function($query)use($inputs){
                            $query->whereHas('executeUser', function($query)use($inputs){
                                $query->where('realname', 'like', '%'.$inputs['execute'].'%');
                            });
                        })
                        ->when(isset($inputs['resource_type']) && is_numeric($inputs['resource_type']), function($query)use($inputs){
                            $query->where('resource_type', $inputs['resource_type']);;
                        })
                        ->when(isset($inputs['income_main_type']) && is_numeric($inputs['income_main_type']), function($query)use($inputs){
                            $query->where('income_main_type', $inputs['income_main_type']);;
                        })
                        ->with(['hasCustomer' => function ($query){
                            $query->with(['contract'])->select(['id','customer_name','customer_type','contacts']);
                        }])
                        ->with(['trade' => function ($query){
                            $query->select(['id','name']);
                        }])
                        ->with(['hasLink'=>function($query){
                            $query->select(DB::raw('COUNT(*) as count,project_id'))->groupBy('project_id');
                        }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->when(!isset($inputs['all']),function($query)use($start,$length){
                    $query->skip($start)->take($length);
                })->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取单条
    public function getProjectInfo($inputs){
        $query_where = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function($query) use ($inputs){
            return $query->where('id', $inputs['id']);
        })
        ->with(['trade' => function ($query) use ($inputs) {
            return $query->select(['id', 'name']);
        }])
        ->with(['hasCustomer'=> function ($query) use ($inputs){
            $query->select(['id', 'customer_name', 'customer_type', 'customer_tel', 'contacts', 'customer_email', 'customer_qq', 'bank_accounts', 'customer_address']);
        }])
        ->with(['hasOrder'=> function($query){
            $query->select(['id','swd_id','project_business','test_cycle','settlement_type']);
        }])
        ->with(['hasLink']);
        $info = $query_where->first();
        return $info;
    }

    //项目请求同步V3
    public function doRequestSyn() {
        return;
        //$ip = request()->server('SERVER_ADDR');
        //if($ip != '111.230.143.238'){
            //非正式环境 不同步数据 直接返回1  等到停掉旧oa之后再开启接口推送
            //return ['code' => 1, 'message'=>'ok'];
        //}
        // $urls = ['http://edmv3.mail-mall.com/frontend/oa_projects_receiver.php','http://120.31.134.57/cosdata/api.php?do=ReceiveProjects'];//正式环境
        //$urls = ['http://edmv3.mail-mall.com/frontend/oa_projects_receiver_debug.php'];
        //获取需要提交的客户
        $post_data = array();
        $post_data['projects'] = $this->doJsonList();
        $post_data['sign'] = md5($this->client_api_key . http_build_query($post_data));
        //推送数据
        $fail_list = array();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        foreach($urls as $url) {
            curl_setopt($ch,CURLOPT_URL,$url);
            $content = curl_exec($ch);
            $msg = json_decode($content,true);
            if ($msg['code']) $fail_list[] = $url.">>>>".$msg['message'];
        }
        curl_close($ch);
        if(count($fail_list)) {
            return ['code' => 0, 'message' => "以下地址同步出错：".implode(",",$fail_list)];
        } else {
            return ['code' => 1, 'message' => 'ok'];
        }

    }

    //api获取项目列表数据
    public function doJsonList(){
        $project = new BusinessProject;
        $project_list = $project->select(['id','customer_id','project_name'])->orderBy('id','asc')->get();
        $list = array();
        foreach ($project_list as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['cid'] = $value->customer_id;
            $tmp['name'] = $value->project_name;
            $tmp['need_send_email'] = '';
            $tmp['project_cluster_id'] = 0;
            $list[] = $tmp;
        }
        return json_encode($list);
    }

    /**
     * @Author: qinjintian
     * @Date:   2019-03-04
     * 找出二维数组中与指定日期最邻近的数据
     */
    public static function recentDate($array, $contrast_date)
    {
        $array = collect($array)->keyBy('id');
        $recent_date_datas = [];
        foreach ($array as $key => $val) {
            if (strtotime($val['rdate']) <= strtotime($contrast_date)) {
                $recent_data_temps = [];
                $recent_data_temps['id'] = $val['id'];
                $recent_data_temps['rdate'] = $val['rdate'];
                $recent_data_temps['updated_at'] = $val['updated_at'];
                $recent_date_datas[] = $recent_data_temps;
            }
        }
        $recent_date_datas = collect($recent_date_datas)->sortByDesc('rdate')->sortByDesc('updated_at')->values()->all();
        $recent_date_data = [];
        if (count($recent_date_datas) > 0) {
            $recent_date_data_id = $recent_date_datas[0]['id'];
            $recent_date_data = $array[$recent_date_data_id];
        }
        return $recent_date_data;
    }

    //根据日期修改项目暂停或启用   （如果该状态关系到接口对接  再把此处就写到任务计划里面 00:00:00开始执行）
    public function updateProjectStatus(){
        $project = new BusinessProject;
        $date = date('Y-m-d');
        $suspend = new \App\Models\BusinessProjectSuspend;
        $project_ids = $suspend->where('date', $date)->pluck('project_id')->toArray();//今天要暂停的项目id
        $project_all_ids = $suspend->groupBy('project_id')->pluck('project_id')->toArray();//所有暂停过的项目id
        if(!empty($project_ids)){
            //把在暂停日期内的项目设置为暂停
            $project->whereIn('id', $project_ids)->where('status', 1)->update(['status'=> 3]);
            //把不在暂停日期内的项目设置为正在运营  暂停过的项目 再次启动
            $qidong_ids = [];
            foreach ($project_all_ids as $key => $pid) {
                if(!in_array($pid, $project_ids)){
                    $qidong_ids[] = $pid;
                }
            }
            if(!empty($qidong_ids)){
                $project->where('status', 3)->whereIn('id', $qidong_ids)->update(['status'=> 1]);
            }
            
        }else{
            //当天没有要暂停的项目
            $project->where('status', 3)->whereIn('id', $project_all_ids)->update(['status'=> 1]);
        }
        return true;
    }

    //创建分配日志
    public function createLinkLog($link_ids = [], $project_id = 0){
        if(empty($link_ids) || $project_id == 0) return;
        //记录分配日志
        $link_log = new \App\Models\ProjectLinkLog;
        $insert = $update = [];
        $update['end_time'] = strtotime(date('Y-m-d'));
        $update['updated_at'] = date('Y-m-d H:i:s');
        $link_log->whereIn('link_id', $link_ids)->where('end_time', 0)->update($update);//把分配给上一个项目的结束日期给加上
        foreach ($link_ids as $lid) {
            $tmp = [];
            $tmp['link_id'] = $lid;
            $tmp['project_id'] = $project_id;
            $tmp['start_time'] = strtotime(date('Y-m-d'));
            $tmp['end_time'] = 0;
            $tmp['created_at'] = date('Y-m-d H:i:s');
            $tmp['updated_at'] = date('Y-m-d H:i:s');
            $insert[] = $tmp;
        }
        if($link_log->insert($insert)){
            //更新链接最后更新时间
            (new \App\Models\BusinessOrderLink)->whereIn('id', $link_ids)->update(['updated_at'=>date('Y-m-d H:i:s')]);
            return true;
        }
        return false;
    }
}

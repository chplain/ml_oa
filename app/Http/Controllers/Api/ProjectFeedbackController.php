<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class ProjectFeedbackController extends Controller
{
    //数据反馈
    /**
     * 数据反馈汇总
     * @Author: molin
     * @Date:   2019-02-19
     */
    public function index(){
        $inputs = request()->all();
        $project = new \App\Models\BusinessProject;

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'show'){
            if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数project_id']);
            }   
            $feedback = new \App\Models\ProjectFeedback;
            $link_feedback = new \App\Models\LinkFeedback;
            $project_link_log = new \App\Models\ProjectLinkLog;
            $page_num = $inputs['page_num'] ?? 10;
            if(!isset($inputs['end_time'])){
                $inputs['end_time'] = date('Y-m-d');
            }
            if(!isset($inputs['start_time'])){
                $end_date = $inputs['end_time'];
                $inputs['start_time'] = date('Y-m-d', strtotime("$end_date -$page_num day"));
            }
            $days = prDates($inputs['start_time'],$inputs['end_time']);
            $days = array_reverse($days);
            $inputs['project_ids'] = [$inputs['project_id']];
            $project_feedback_list = $feedback->getQueryList($inputs);
            $feedback_data = $link_feedback_data = array();
            foreach ($project_feedback_list as $key => $value) {
                $feedback_data[$value->date]['CPC']['id'] = 0;
                $feedback_data[$value->date]['CPC']['value'] = $value->cpc_amount;
                $feedback_data[$value->date]['CPD']['id'] = 0;
                $feedback_data[$value->date]['CPD']['value'] = $value->cpd_amount;
            }
            $link_feedback_list = $link_feedback->getLinkFeedback($inputs);
            //查出当前项目在这段时间内使用过的所有链接
            $link_all = $project_link_log->getLinkLogs($inputs);
            foreach ($link_feedback_list as $key => $value) {
                $feedback_data[$value->date]['CPA']['id'] = 0;
                $feedback_data[$value->date]['CPS']['id'] = 0;
                $feedback_data[$value->date]['CPA']['value'] = $feedback_data[$value->date]['CPA']['value'] ?? 0;
                $feedback_data[$value->date]['CPS']['value'] = $feedback_data[$value->date]['CPS']['value'] ?? 0;
                $feedback_data[$value->date]['CPA']['value'] += $value->cpa_amount;
                $feedback_data[$value->date]['CPS']['value'] += $value->cps_amount;
                
                $link_feedback_data[$value->date][$value->link_id]['CPA'] = $link_feedback_data[$value->date][$value->link_id]['CPA'] ?? 0;
                $link_feedback_data[$value->date][$value->link_id]['CPS'] = $link_feedback_data[$value->date][$value->link_id]['CPS'] ?? 0;
                
                $link_feedback_data[$value->date][$value->link_id]['CPA'] += $value->cpa_amount;
                $link_feedback_data[$value->date][$value->link_id]['CPS'] += $value->cps_amount;
            }

            $th = ['date'=>['title'=>'日期','label'=>['/']],'summary'=>['title'=>'汇总','label'=>['CPD','CPC','CPA','CPS']]];
            $th_link_data = [];
            foreach ($link_all as $key => $value) {
                $tmp = [];
                $tmp['title'] = $value['link_name'].'-ID:'.$value['link_id'];
                $tmp['label'] = ['CPA','CPS'];
                $th_link_data[] = $tmp;
                $th['link_data'] = $th_link_data;
            }
            $body = array();
            $price_type = ['CPC','CPD','CPA','CPS'];
            foreach ($days as $d) {
                $summary = [];
                $summary['CPD'] = $feedback_data[$d]['CPD'] ?? ['id'=>0,'value'=>0];
                $summary['CPC'] = $feedback_data[$d]['CPC'] ?? ['id'=>0,'value'=>0];
                $summary['CPA'] = $feedback_data[$d]['CPA'] ?? ['id'=>0,'value'=>0];
                $summary['CPS'] = $feedback_data[$d]['CPS'] ?? ['id'=>0,'value'=>0];
                $body[$d]['date'] = $d;
                $body[$d]['summary'] = $summary;
                $link_data = [];
                foreach ($link_all as $key => $value) {
                    $tmp = [];
                    $tmp['link_id'] = $value['link_id'];
                    $tmp['value']['CPA'] = $link_feedback_data[$d][$value['link_id']]['CPA'] ?? 0;
                    $tmp['value']['CPS'] = $link_feedback_data[$d][$value['link_id']]['CPS'] ?? 0;
                    $link_data[] = $tmp;
                }
                $body[$d]['link_data'] = $link_data;
            }
            $data['th'] = $th;
            $data['body'] = $body;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $data = $project->getDataList($inputs);
        $items = [];
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['trade'] = $value->trade->name;
            $tmp['project_name'] = $value->project_name;
            $tmp['charge'] = $user_data['id_realname'][$value->charge_id];
            $tmp['sale_man'] = $user_data['id_realname'][$value->sale_man];
            $tmp['business'] = $user_data['id_realname'][$value->business_id];
            $items[] = $tmp;
        }
        $data['datalist'] = $items;
        $data['trade_list'] = (new \App\Models\Trade)->select(['id', 'name'])->get();
        $data['project_list'] = $project->select(['id','project_name'])->get();
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

    /**
     * 数据反馈汇总-导出
     * @Author: molin
     * @Date:   2019-02-19
     */
    public function export(){
    	$inputs = request()->all();
        if(!isset($inputs['start_time']) || !isset($inputs['end_time']) || empty($inputs['start_time']) || empty($inputs['end_time'])){
            return response()->json(['code' => -1, 'message' => '请输入时间']);
        }
        if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
            return response()->json(['code' => -1, 'message' => '开始时间不能大于结束时间']);
        }
        if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
            return response()->json(['code' => -1, 'message' => '请选择项目']);
        }
        $days = prDates($inputs['start_time'], $inputs['end_time']);
        $inputs['project_ids'] = [$inputs['project_id']];
        $feedback = new \App\Models\ProjectFeedback;
        $project_feedback_list = $feedback->getQueryList($inputs);
        $feedback_data = $link_feedback_data = array();
        foreach ($project_feedback_list as $key => $value) {
            $feedback_data[$value->date]['CPC'] = $value->cpc_amount;
            $feedback_data[$value->date]['CPD'] = $value->cpd_amount;
        }
        
        $link_feedback = new \App\Models\LinkFeedback;
        $link_feedback_list = $link_feedback->getLinkFeedback($inputs);

        $link_all = [];
        foreach ($link_feedback_list as $key => $value) {
            $feedback_data[$value->date]['CPA'] = $feedback_data[$value->date]['CPA'] ?? 0;
            $feedback_data[$value->date]['CPS'] = $feedback_data[$value->date]['CPS'] ?? 0;
            $feedback_data[$value->date]['CPA'] += $value->cpa_amount;
            $feedback_data[$value->date]['CPS'] += $value->cps_amount;
            
            $link_feedback_data[$value->date][$value->link_id]['CPA'] = $link_feedback_data[$value->date][$value->link_id]['CPA'] ?? 0;
            $link_feedback_data[$value->date][$value->link_id]['CPS'] = $link_feedback_data[$value->date][$value->link_id]['CPS'] ?? 0;
            $link_feedback_data[$value->date][$value->link_id]['CPA'] += $value->cpa_amount;
            $link_feedback_data[$value->date][$value->link_id]['CPS'] += $value->cps_amount;
            $link_all[$value->link_id] = $value->hasLink->link_name.';ID:'.$value->link_id;
        }
        $header = ['日期','汇总','','',''];
        foreach ($link_all as $key => $value) {
            $header[] = $value;
            $header[] = '';
        }
        $th = ['--','CPD','CPC','CPA','CPS'];
        foreach ($link_all as $key => $value) {
            $th[] = 'CPA';
            $th[] = 'CPS';
        }
        $body[0] = $th;
        foreach ($days as $d) {
            $tmp = [];
            $tmp[] = $d;
            foreach (['CPD','CPC','CPA','CPS'] as $value) {
                $tmp[] = $feedback_data[$d][$value] ?? 0;
            }
            foreach ($link_all as $lid => $value) {
                $tmp[] = $link_feedback_data[$d][$lid]['CPA'] ?? 0;
                $tmp[] = $link_feedback_data[$d][$lid]['CPS'] ?? 0;
            }
            $body[] = $tmp;
        }
        $filedata = pExprot($header, $body, 'feedback_list');
        $filepath = 'storage/exports/' . $filedata['file'];//下载链接
        $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
        return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
    }

    /**
     * 我的项目数据反馈
     * @Author: molin
     * @Date:   2019-02-20
     */
    public function list(){
        $inputs = request()->all();
        $project = new \App\Models\BusinessProject;

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'show'){
            if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数project_id']);
            }   
            $feedback = new \App\Models\ProjectFeedback;
            $link_feedback = new \App\Models\LinkFeedback;
            $project_link_log = new \App\Models\ProjectLinkLog;
            
            $page_num = $inputs['page_num'] ?? 10;
            if(!isset($inputs['end_time'])){
                $inputs['end_time'] = date('Y-m-d');
            }
            if(!isset($inputs['start_time'])){
                $end_date = $inputs['end_time'];
                $inputs['start_time'] = date('Y-m-d', strtotime("$end_date -$page_num day"));
            }
            $days = prDates($inputs['start_time'],$inputs['end_time']);
            $days = array_reverse($days);
            $inputs['project_ids'] = [$inputs['project_id']];
            $project_feedback_list = $feedback->getQueryList($inputs);
            $feedback_data = $link_feedback_data = array();
            foreach ($project_feedback_list as $key => $value) {
                $feedback_data[$value->date]['CPC']['id'] = 0;
                $feedback_data[$value->date]['CPC']['value'] = $value->cpc_amount;
                $feedback_data[$value->date]['CPD']['id'] = 0;
                $feedback_data[$value->date]['CPD']['value'] = $value->cpd_amount;
            }
            $link_feedback_list = $link_feedback->getLinkFeedback($inputs);
            //查出当前项目在这段时间内使用过的所有链接
            $link_all = $project_link_log->getLinkLogs($inputs);
            foreach ($link_feedback_list as $key => $value) {
                $feedback_data[$value->date]['CPA']['id'] = 0;
                $feedback_data[$value->date]['CPS']['id'] = 0;
                $feedback_data[$value->date]['CPA']['value'] = $feedback_data[$value->date]['CPA']['value'] ?? 0;
                $feedback_data[$value->date]['CPS']['value'] = $feedback_data[$value->date]['CPS']['value'] ?? 0;
                $feedback_data[$value->date]['CPA']['value'] += $value->cpa_amount;
                $feedback_data[$value->date]['CPS']['value'] += $value->cps_amount;
                
                $link_feedback_data[$value->date][$value->link_id]['CPA'] = $link_feedback_data[$value->date][$value->link_id]['CPA'] ?? 0;
                $link_feedback_data[$value->date][$value->link_id]['CPS'] = $link_feedback_data[$value->date][$value->link_id]['CPS'] ?? 0;
                
                $link_feedback_data[$value->date][$value->link_id]['CPA'] += $value->cpa_amount;
                $link_feedback_data[$value->date][$value->link_id]['CPS'] += $value->cps_amount;
            }

            $th = ['date'=>['title'=>'日期','label'=>['/']],'summary'=>['title'=>'汇总','label'=>['CPD','CPC','CPA','CPS']]];
            $th_link_data = [];
            foreach ($link_all as $key => $value) {
                $tmp = [];
                $tmp['title'] = $value['link_name'].'-ID:'.$value['link_id'];
                $tmp['label'] = ['CPA','CPS'];
                $th_link_data[] = $tmp;
                $th['link_data'] = $th_link_data;
            }
            $body = array();
            $price_type = ['CPC','CPD','CPA','CPS'];
            foreach ($days as $d) {
                $summary = [];
                $summary['CPD'] = $feedback_data[$d]['CPD'] ?? ['id'=>0,'value'=>0];
                $summary['CPC'] = $feedback_data[$d]['CPC'] ?? ['id'=>0,'value'=>0];
                $summary['CPA'] = $feedback_data[$d]['CPA'] ?? ['id'=>0,'value'=>0];
                $summary['CPS'] = $feedback_data[$d]['CPS'] ?? ['id'=>0,'value'=>0];
                $body[$d]['date'] = $d;
                $body[$d]['summary'] = $summary;
                $link_data = [];
                foreach ($link_all as $key => $value) {
                    $tmp = [];
                    $tmp['link_id'] = $value['link_id'];
                    $tmp['value']['CPA'] = $link_feedback_data[$d][$value['link_id']]['CPA'] ?? 0;
                    $tmp['value']['CPS'] = $link_feedback_data[$d][$value['link_id']]['CPS'] ?? 0;
                    $link_data[] = $tmp;
                }
                $body[$d]['link_data'] = $link_data;
            }
            $data['th'] = $th;
            $data['body'] = $body;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        $inputs['charge_or_business'] = auth()->user()->id;
        $data = $project->getDataList($inputs);
        $items = [];
        foreach ($data['datalist'] as $key => $value) {
            $tmp = array();
            $tmp['id'] = $value->id;
            $tmp['trade'] = $value->trade->name;
            $tmp['project_name'] = $value->project_name;
            $tmp['charge'] = $user_data['id_realname'][$value->charge_id];
            $tmp['sale_man'] = $user_data['id_realname'][$value->sale_man];
            $tmp['business'] = $user_data['id_realname'][$value->business_id];
            $items[] = $tmp;
        }
        $data['datalist'] = $items;
        $data['trade_list'] = (new \App\Models\Trade)->select(['id', 'name'])->get();
        $data['project_list'] = $project->where('charge_id', auth()->user()->id)->orWhere('business_id', auth()->user()->id)->select(['id','project_name'])->get();
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        
    }

    /**
     * 我的项目数据反馈-导入
     * @Author: molin
     * @Date:   2019-06-04
     */
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
            return ['code' => 0, 'message' => '反馈文档没有任何行信息，请检查'];
        }
        $link = new \App\Models\BusinessOrderLink;
        $price_log = new \App\Models\BusinessOrderPrice;
        $link_log = new \App\Models\ProjectLinkLog;
        $link_feedback = new \App\Models\LinkFeedback;
        $feedback = new \App\Models\ProjectFeedback;
        $project = new \App\Models\BusinessProject;

        foreach ($first_sheet as $key => $val) {
            if(empty($val[0]) || $val[0] == 0) continue;
            $vdata = [
                'link_id' => trim($val[0]),
                'date' => date('Y-m-d', strtotime(trim($val[1]))),
                'cpa_amount' => trim($val[2]),
                'cps_amount' => trim($val[3])
            ];
            $rules = [
                'link_id' => 'required|min:1',
                'date' => 'required',
                'cpa_amount' => 'required|integer',
                'cps_amount' => 'required|numeric'
            ];
            $attributes = [
                'link_id' => '链接id',
                'date' => '日期格式',
                'cpa_amount' => 'CPA值',
                'cps_amount' => 'CPS值'
            ];
            $validator = validator($vdata, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            //判断链接是否存在  在所在日期是否有项目
            $link_info = $link->where('id', $vdata['link_id'])->first();
            if(empty($link_info)){
                return response()->json(['code' => 0, 'message' => '链接不存在，id:'.$vdata['link_id']]);
            }
            $link_log_info = $link_log->getLinkProjectDateInfo($vdata['link_id'], $vdata['date']);
            if(empty($link_log_info)){
                return response()->json(['code' => 0, 'message' => '链接id：'.$vdata['link_id'].'在'.$vdata['date'].'没有关联的项目']);
            }

            $link_feedback_info = $link_feedback->where('link_id', $vdata['link_id'])->where('date', $vdata['date'])->first();
            $insert = [];
            if(!empty($link_feedback_info)){
                //编辑
                $link_feedback_info->cpa_amount = $vdata['cpa_amount'];
                $link_feedback_info->cps_amount = $vdata['cps_amount'];
                $res = $link_feedback_info->save();
                $project_id = $link_feedback_info->project_id;
            }else{
                //新添加
                $link_price_info = $price_log->where('link_id', $vdata['link_id'])->where('start_time', '<=', strtotime($vdata['date']))->where('end_time', '>=', strtotime($vdata['date']))->orderBy('created_at', 'desc')->first();
                if(empty($link_price_info)){
                    $link_price_info = $price_log->where('link_id', $vdata['link_id'])->where('start_time','=',0)->where('end_time','=',0)->where('created_at', '<=', $vdata['date'].' 23:59:59')->orderBy('created_at','desc')->first();
                    if(empty($link_price_info)){
                        $link_price_info = $link->where('id', $vdata['link_id'])->first();
                    }
                }

                if(!empty($link_price_info)){
                    $link_insert = [];
                    $market_price = unserialize($link_price_info->market_price);
                    if($link_price_info->pricing_manner == 'CPA'){
                        $link_insert['cpa_price'] = $market_price['CPA'];
                        $link_insert['cpa_amount'] = $vdata['cpa_amount'];
                    }elseif($link_price_info->pricing_manner == 'CPS'){
                        $link_insert['cps_price'] = $market_price['CPS'];
                        $link_insert['cps_amount'] = $vdata['cps_amount'];
                    }elseif($link_price_info->pricing_manner == 'CPA+CPS'){
                        $link_insert['cpa_price'] = $market_price['CPA'];
                        $link_insert['cps_price'] = $market_price['CPS'];
                        $link_insert['cpa_amount'] = $vdata['cpa_amount'];
                        $link_insert['cps_amount'] = $vdata['cps_amount'];
                    }elseif($link_price_info->pricing_manner == 'CPC'){
                        return response()->json(['code' => 0, 'message' => '链接id：'.$vdata['link_id'].'在日期：'.$vdata['date'].'的结算方式为CPC']);
                    }elseif($link_price_info->pricing_manner == 'CPD'){
                        return response()->json(['code' => 0, 'message' => '链接id：'.$vdata['link_id'].'在日期：'.$vdata['date'].'的结算方式为CPD']);
                    }
                    $link_insert['link_id'] = $vdata['link_id'];
                    $link_insert['project_id'] = $link_log_info->project_id;
                    $link_insert['date'] = $vdata['date'];
                    $link_insert['money'] = 0;
                    $link_insert['user_id'] = auth()->user()->id;
                    $link_insert['created_at'] = date('Y-m-d H:i:s');
                    $link_insert['updated_at'] = date('Y-m-d H:i:s');
                    $res = $link_feedback->insert($link_insert);
                    $project_id = $link_insert['project_id'];
                }else{
                    return ['code' => 0, 'message' => '无法获取当天单价,错误行 链接id'.$vdata['link_id'].' 日期：'.$vdata['date']];
                }
                if(!$res) return response()->json(['code' => 0, 'message' => '导入失败 链接id：'.$vdata['link_id'].' 日期：'.$vdata['date']]);
                $project_info = $project->where('id', $project_id)->select(['customer_id'])->first();
                $project_feedback_info = $feedback->where('project_id', $project_id)->where('date', $vdata['date'])->first();
                if(empty($project_feedback_info)){
                    $project_insert = [];
                    $project_insert['project_id'] = $project_id;
                    $project_insert['user_id'] = auth()->user()->id;
                    $project_insert['date'] = $vdata['date'];
                    $project_insert['cpa_price'] = 0;
                    $project_insert['cps_price'] = 0;
                    $project_insert['cpc_price'] = 0;
                    $project_insert['cpd_price'] = 0;
                    $project_insert['cpa_amount'] = 0;
                    $project_insert['cps_amount'] = 0;
                    $project_insert['cpc_amount'] = 0;
                    $project_insert['cpd_amount'] = 0;
                    $project_insert['money'] = 0;
                    $project_insert['customer_id'] = $project_info->customer_id;
                    $project_insert['created_at'] = date('Y-m-d H:i:s');
                    $project_insert['updated_at'] = date('Y-m-d H:i:s');
                    $res2 = $feedback->insert($project_insert);
                    if(!$res2) return response()->json(['code' => 0, 'message' => '创建项目收入汇总失败']);
                }
                
            }
            //重新统计收入
            $res3 = $feedback->updateProjectIncome($project_id, $vdata['date']);
            if(!$res3) return response()->json(['code' => 0, 'message' => '更新项目收入汇总失败，项目id:'.$project_id.' 链接id：'.$vdata['link_id'].' 日期：'.$vdata['date']]);

        }
        return response()->json(['code' => 1, 'message' => '导入成功']);

    }

    /**
     * 我的项目数据反馈-导出
     * @Author: molin
     * @Date:   2019-02-20
     */
    public function export_mine(){
        $inputs = request()->all();
        if(!isset($inputs['start_time']) || !isset($inputs['end_time']) || empty($inputs['start_time']) || empty($inputs['end_time'])){
            return response()->json(['code' => -1, 'message' => '请输入时间']);
        }
        if(strtotime($inputs['start_time']) > strtotime($inputs['end_time'])){
            return response()->json(['code' => -1, 'message' => '开始时间不能大于结束时间']);
        }
        if(!isset($inputs['project_id']) || !is_numeric($inputs['project_id'])){
            return response()->json(['code' => -1, 'message' => '请选择项目']);
        }
        $days = prDates($inputs['start_time'], $inputs['end_time']);
        $inputs['project_ids'] = [$inputs['project_id']];
        $feedback = new \App\Models\ProjectFeedback;
        $project_feedback_list = $feedback->getQueryList($inputs);
        $feedback_data = $link_feedback_data = array();
        foreach ($project_feedback_list as $key => $value) {
            $feedback_data[$value->date]['CPC'] = $value->cpc_amount;
            $feedback_data[$value->date]['CPD'] = $value->cpd_amount;
        }
        
        $link_feedback = new \App\Models\LinkFeedback;
        $link_feedback_list = $link_feedback->getLinkFeedback($inputs);

        $link_all = [];
        foreach ($link_feedback_list as $key => $value) {
            $feedback_data[$value->date]['CPA'] = $feedback_data[$value->date]['CPA'] ?? 0;
            $feedback_data[$value->date]['CPS'] = $feedback_data[$value->date]['CPS'] ?? 0;
            $feedback_data[$value->date]['CPA'] += $value->cpa_amount;
            $feedback_data[$value->date]['CPS'] += $value->cps_amount;
            
            $link_feedback_data[$value->date][$value->link_id]['CPA'] = $link_feedback_data[$value->date][$value->link_id]['CPA'] ?? 0;
            $link_feedback_data[$value->date][$value->link_id]['CPS'] = $link_feedback_data[$value->date][$value->link_id]['CPS'] ?? 0;
            $link_feedback_data[$value->date][$value->link_id]['CPA'] += $value->cpa_amount;
            $link_feedback_data[$value->date][$value->link_id]['CPS'] += $value->cps_amount;
            $link_all[$value->link_id] = $value->hasLink->link_name.';ID:'.$value->link_id;
        }
        $header = ['日期','汇总','','',''];
        foreach ($link_all as $key => $value) {
            $header[] = $value;
            $header[] = '';
        }
        $th = ['--','CPD','CPC','CPA','CPS'];
        foreach ($link_all as $key => $value) {
            $th[] = 'CPA';
            $th[] = 'CPS';
        }
        $body[0] = $th;
        foreach ($days as $d) {
            $tmp = [];
            $tmp[] = $d;
            foreach (['CPD','CPC','CPA','CPS'] as $value) {
                $tmp[] = $feedback_data[$d][$value] ?? 0;
            }
            foreach ($link_all as $lid => $value) {
                $tmp[] = $link_feedback_data[$d][$lid]['CPA'] ?? 0;
                $tmp[] = $link_feedback_data[$d][$lid]['CPS'] ?? 0;
            }
            $body[] = $tmp;
        }
        $filedata = pExprot($header, $body, 'feedback_list');
        $filepath = 'storage/exports/' . $filedata['file'];//下载链接
        $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
        return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
    }

    /**
     * 我的项目数据反馈-设置值
     * @Author: molin
     * @Date:   2019-02-20
     */
    public function update(){
        $inputs = request()->all();
        //更改反馈数据 salt=summary 时  只允许修改cpc cpd  salt=link_data时  只允许修改cpa cps
        //model: CPC/CPD/CPA/CPS  value:更改值
        $project = new \App\Models\BusinessProject;
        $feedback = new \App\Models\ProjectFeedback;
        $link_feedback = new \App\Models\LinkFeedback;
        $project_link_log = new \App\Models\ProjectLinkLog;
        $rules = [
            'project_id' => 'required|integer',
            'salt' => 'required',
            'model' => 'required',
            'date' => 'required|date_format:Y-m-d',
            'value' => 'required|numeric'

        ];
        $attributes = [
            'project_id' => '项目id',
            'salt' => 'salt',
            'model' => '类型，如CPA',
            'date' => '日期',
            'value' => '更改值'
        ];
        
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if($inputs['date'] == date('Y-m-d')){
            return response()->json(['code' => 0, 'message' => '不能录入今天的数据']);
        }
        if($inputs['salt'] == 'summary' && !in_array($inputs['model'], ['CPC','CPD'])){
            return response()->json(['code' => 0, 'message' => '此项不能修改']);
        }
        $project_info = $project->where('id', $inputs['project_id'])->first();
        if(empty($project_info)){
            return response()->json(['code' => 0, 'message' => '项目不存在']);
        }
        $return = $feedback->checkProjectLinkFeedback($inputs['project_id'], $inputs['date']);
        if($return['code'] != 1){
            return response()->json($return);
        }
        if($inputs['salt'] == 'summary' && in_array($inputs['model'], ['CPC','CPD'])){
            //CPC\CPD
            $feedback_info = $feedback->where('project_id', $inputs['project_id'])->where('date', $inputs['date'])->orderBy('id', 'asc')->first();
            if(!empty($feedback_info)){
                //编辑
                if($inputs['model'] == 'CPC'){
                    $old_amount = $feedback_info->cpc_amount;//旧值
                    $feedback_info->cpc_amount = $inputs['value'];
                }
                if($inputs['model'] == 'CPD'){
                    $old_amount = $feedback_info->cpd_amount;//旧值
                    $feedback_info->cpd_amount = $inputs['value'];
                }
                $result = $feedback_info->save();
                if($result){
                    //更新收入
                    $feedback->updateProjectIncome($inputs['project_id'], $inputs['date']);
                    $change_log = [];
                    $change_log['fid'] = $feedback_info->id;
                    $change_log['project_id'] = $inputs['project_id'];
                    $change_log['pricing_manner'] = strtolower($inputs['model']);
                    $change_log['amount'] = $old_amount;
                    $change_log['e_amount'] = $inputs['value'];
                    (new \App\Models\ProjectFeedbackLog)->storeData($change_log);//写入更改日志
                    systemLog('数据反馈', '更改了'.$inputs['date'].'的'.$inputs['model'].'值：'.$old_amount.'->'.$inputs['value']);
                    return response()->json(['code' => 1, 'message' => '操作成功']);
                }
            }else{
                return response()->json(['code' => 0, 'message' => '无法创建数据反馈']);
            }
        }
        if($inputs['salt'] == 'link_data' && !in_array($inputs['model'], ['CPA','CPS'])){
            return response()->json(['code' => 0, 'message' => '此项不能修改']);
        }
        if($inputs['salt'] == 'link_data' && in_array($inputs['model'], ['CPA','CPS'])){
            if(!isset($inputs['link_id']) || !is_numeric($inputs['link_id'])){
                return response()->json(['code' => 0, 'message' => '缺少参数link_id']);
            }
            $link_feedback_info = $link_feedback->where('project_id', $inputs['project_id'])->where('date', $inputs['date'])->where('link_id', $inputs['link_id'])->first();
            if(!empty($link_feedback_info)){
                //编辑
                if($inputs['model'] == 'CPA'){
                    $old_amount = $link_feedback_info->cpa_amount;
                    $link_feedback_info->cpa_amount = $inputs['value'];
                }
                if($inputs['model'] == 'CPS'){
                    $old_amount = $link_feedback_info->cps_amount;
                    $link_feedback_info->cps_amount = $inputs['value'];
                }
                $cpa = $link_feedback_info->cpa_price * $link_feedback_info->cpa_amount;
                $cps = $link_feedback_info->cps_price * $link_feedback_info->cps_amount;
                $cpc = $link_feedback_info->cpc_price * $link_feedback_info->cpc_amount;
                $cpd = $link_feedback_info->cpd_price * $link_feedback_info->cpd_amount;
                $link_feedback_info->money = sprintf('%.2f', ($cpa+$cps+$cpc+$cpd));//兼容旧oa数据
                $result = $link_feedback_info->save();
                if($result){
                    //更新收入
                    $feedback->updateProjectIncome($inputs['project_id'], $inputs['date']);
                    $change_log = [];
                    $change_log['lid'] = $link_feedback_info->id;
                    $change_log['project_id'] = $inputs['project_id'];
                    $change_log['pricing_manner'] = strtolower($inputs['model']);
                    $change_log['amount'] = $old_amount;
                    $change_log['e_amount'] = $inputs['value'];
                    (new \App\Models\ProjectFeedbackLog)->storeData($change_log);//写入更改日志
                    systemLog('数据反馈', '更改了'.$inputs['date'].'的'.$inputs['model'].'值：'.$old_amount.'->'.$inputs['value']);
                    return response()->json(['code' => 1, 'message' => '操作成功']);
                }
            }else{
                return response()->json(['code' => 0, 'message' => '无法获取当天单价']);
            }
        }
    }

}

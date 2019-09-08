<?php

namespace App\Http\Controllers\Api;

use App\Models\Holiday;
use App\Models\TeamProject;
use App\Models\TeamProjectMilestone;
use App\Models\TeamProjectTask;
use App\Models\TeamProjectTaskDocument;
use App\Models\TeamProjectTaskDocumentHasModel;
use App\Models\User;
use http\Env\Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Overtrue\ChineseCalendar\Calendar;

class TeamProjectTaskController extends Controller
{
    /**
     * 任务列表
     * @Author: renxianyong
     * @Date:   2019-03-18
     */
    public function index()
    {
        $inputs = \request()->all();
        if(!isset($inputs['team_project_id'])){
            return \response()->json(['code'=>-1,'message'=>'项目id不存在']);
        }
        $team_project_task_model = new TeamProjectTask;
        $datas['datas'] = $team_project_task_model->queryTaskDatas($inputs);
        $datas['status'] = [
            1 => '进行中',
            2 => '已完成',
            3 => '已超时',
            4 => '终止',
            5 => '未开始'
        ];
        if($datas){
            return response()->json(['code' => 1,'message' => '获取成功','data'=>$datas]);
        }
        return response()->json(['code'=>0,'message'=>'获取数据失败，请重试']);
    }

    /**
     * 新建任务
     * @Author: renxianyong
     * @Date:   2019-03-18
     */
    public function store()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case '1'://获取用户数据
                if(!isset($inputs['team_project_id'])){
                    return \response()->json(['code'=>-1,'message'=>'项目id不存在']);
                }
                $project_users = TeamProject::where('id',$inputs['team_project_id'])->first(['id','participant_ids','stakeholder_ids'])->toArray();
                $project_user_ids = explode(',',$project_users['participant_ids'].','.$project_users['stakeholder_ids']);
                $datas['users'] = User::whereIn('id',$project_user_ids)->get(['id','realname']);
                $datas['priority'] = [
                    1 => '一级',
                    2 => '二级',
                    3 => '三级',
                    4 => '四级',
                ];
                $datas['status'] = [
                    1 => '进行中',
                    2 => '已完成',
                    4 => '终止',
                    5 => '未开始'
                ];
                return response()->json(['code' => 1,'message'=>'获取成功','data'=>$datas]);
                break;
            case 'upload'://上传附件
                // 上传附件
                return $this->uploadAccessory($inputs);
                break;
            default ://新建任务
                return $this->storeTask($inputs);
        }
    }

    /**
     * 编辑任务
     * @Author: renxianyong
     * @Date:   2019-03-20
     */
    public function edit()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case 'delete'://删除附件
                if(!isset($inputs['document_id']) && empty($inputs['document_id'])){
                    return response()->json(['code'=>-1,'message'=>'文档id不存在,请输入']);
                }
                $result = TeamProjectTaskDocument::where('id',$inputs['document_id'])->delete();
                if($result){
                    return response()->json(['code'=>1,'message'=>'删除成功']);
                }
                return response()->json(['code'=>1,'message'=>'删除失败，请稍后重试']);
                break;
            case 'edit'://获取用户数据和任务数据
                if(!isset($inputs['id'])){
                    return response()->json(['code'=>-1,'message'=>'id不存在,请输入']);
                }
                $project_id = TeamProjectTask::where('id',$inputs['id'])->value('team_project_id');
                $project_users = TeamProject::where('id',$project_id)->first(['id','participant_ids','stakeholder_ids'])->toArray();
                $project_user_ids = explode(',',$project_users['participant_ids'].','.$project_users['stakeholder_ids']);
                $datas['users'] = User::whereIn('id',$project_user_ids)->get(['id','realname']);
                $datas['team_project_task'] = TeamProjectTask::where('id',$inputs['id'])->first();
                $datas['team_project_task']['team_project_task_document'] = TeamProjectTaskDocument::where('model_id',$inputs['id'])
                    ->where('model_type','App\Models\TeamProjectTask')
                    ->get(['id','path_name','original_name']);
                $host = url()->previous();//获取域名
                foreach ($datas['team_project_task']['team_project_task_document'] as $key => $val){
                    $datas['team_project_task']['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
                }
                $datas['priority'] = [
                    1 => '一级',
                    2 => '二级',
                    3 => '三级',
                    4 => '四级',
                ];
                $datas['status'] = [
                    1 => '进行中',
                    2 => '已完成',
                    4 => '终止',
                    5 => '未开始'
                ];
                return response()->json(['code' => 1,'message'=>'获取数据成功','data'=>$datas]);
                break;
            case 'upload'://上传附件
                // 上传附件
                return $this->uploadAccessory($inputs);
                break;
            default ://编辑任务
                return $this->editTask($inputs);
        }
    }

    /**
     * 任务查看详情
     * @Author: renxianyong
     * @Date:   2019-03-18
     */
    public function detail()
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return \response()->json(['code'=>-1,'message'=>'任务id不存在']);
        }
        $id = $inputs['id'];
        return $this->showDetail($id);
    }

    /**
     * 上传文档
     * @param $inputs
     * @return array
     */
    public function uploadAccessory($inputs)
    {
        $rules = ['document' => 'file|max:10240'];
        $attributes = ['document' => '文件'];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        if (request()->isMethod('post')) {
            $file = request()->file('document');
            if ($file->isValid()) {
                $original_name = $file->getClientOriginalName(); // 文件原名
                $ext = $file->getClientOriginalExtension();  // 扩展名
            }
            $directory = storage_path('app/public/uploads/document');
            if (!\File::isDirectory($directory)) {
                \File::makeDirectory($directory, $mode = 0777, $recursive = true); // 递归创建目录
            }
            $filename = date('YmdHis') . uniqid() .'.' . $ext;
            $fileinfo = $file->move($directory, $filename);
            $datas['path_name'] = asset('/storage/uploads/document').'/'.$filename;
            $datas['original_name'] = $original_name;
            return ['code' => 1, 'message' => '上传成功', 'data' => $datas];
        } else {
            return ['code' => 0, 'message' => '非法操作'];
        }
    }

    /**
     * 甘特图列表
     * @Author: renxianyong
     * @Date:   2019-04-22
     */
    public function ganttIndex()
    {
        $inputs = \request()->all();
        $rules = [
            'date' => 'required|date_format:Y-m',
            'team_project_id' => 'required|integer'
        ];
        $attributes = [
            'date' => '日期',
            'team_project_id' => '项目id'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $firstday = date('Y-m-01', strtotime($inputs['date']));
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
        //获取日期数据
        $holiday = Holiday::whereBetween('date',[$firstday,$lastday])->get(['date','type','holiday_name'])->toArray();
        $calendar = new Calendar();
        $holiday_data = [];
        foreach ($holiday as $key => $val){
            //阳历转阴历
            $date = explode('-',$val['date']);
            $result = $calendar->solar($date[0], $date[1], $date[2]);
            $holiday_data[$val['date']]['date'] = $val['date'];
            $holiday_data[$val['date']]['week'] = $result['week_name'];
            $holiday_data[$val['date']]['lunar_month_chinese'] = $result['lunar_month_chinese'];
            $holiday_data[$val['date']]['lunar_day_chinese'] = $result['lunar_day_chinese'];
            $holiday_data[$val['date']]['lunar_day_chinese'] = $result['lunar_day_chinese'];
            $holiday_data[$val['date']]['term'] = $result['term'];//节气
            $holiday_data[$val['date']]['holiday'] = '';
            if($val['type'] == 2){//是节假日
                $holiday_data[$val['date']]['holiday'] = $val['holiday_name'];//节假日名称
            }

        }
        //获取里程碑数据
        $milestone = TeamProjectMilestone::whereBetween('date',[$firstday,$lastday])->get(['id','name','date'])->toArray();
        $milestone_data = [];
        foreach ($milestone as $val) {
            $milestone_data[$val['date']][] = $val;
        }
        //获取任务数据
        $task = TeamProjectTask::where('team_project_id',$inputs['team_project_id'])->whereBetween('start_date',[$firstday,$lastday])->get(['id','name','supervisor_name','start_date','deadline_date'])->toArray();
        $task_data = [];
        foreach ($task as $key => $val){
            $array = [
                'id'            =>  $val['id'],
                'data'          =>  $val['name'].'---'.$val['supervisor_name'].'[监督人]',
                'start_date'    =>  $val['start_date'],
                'deadline_date' =>  $val['deadline_date']
            ];
            $task_data[$val['start_date']][] = $array;
        }
        $datas = [];
        foreach ($holiday_data as $key => $val){
            $datas[$key]['holiday'] = $val;
            foreach ($milestone_data as $mile_key => $mile_val){
                if($mile_key == $key){
                    $datas[$key]['milestone'] = $mile_val;
                }
            }
            if(!isset($datas[$key]['milestone'])){
                $datas[$key]['milestone'] = [];
            }
            foreach ($task_data as $task_key => $task_val) {
                if($task_key == $key){
                    $datas[$key]['task'] = $task_val;
                }
            }
            if(!isset($datas[$key]['task'])){
                $datas[$key]['task'] = [];
            }
        }
        return \response()->json(['code'=>1,'message'=>'获取成果','data'=>$datas]);
    }

    /**
     * 新增里程碑
     * @Author: renxianyong
     * @Date:   2019-04-22
     */
    public function ganttStore()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case 1://获取新增里程碑数据
                $rules = [
                    'team_project_id' => 'required|integer'
                ];
                $attributes = [
                    'team_project_id' => '项目id'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                $datas['task_datas'] = TeamProjectTask::where('team_project_id',$inputs['team_project_id'])->pluck('name','id');//获取任务名称列表
                $project_users = TeamProject::where('id',$inputs['team_project_id'])->first(['id','principal_id','participant_ids','stakeholder_ids'])->toArray();
                $project_user_ids = explode(',',$project_users['principal_id'].','.$project_users['participant_ids']);
                $project_stakeholder_ids = explode(',',$project_users['stakeholder_ids']);
                $datas['users'] = User::whereIn('id',$project_user_ids)->get(['id','realname']);//负责人和参与人
                $datas['stakeholder_users'] = User::whereIn('id',$project_stakeholder_ids)->get(['id','realname']);//干系人
                return response()->json(['code'=>1,'message'=>'获取成功','data'=>$datas]);
                break;
            default :
                $rules = [
                    'name' => 'required|string',
                    'team_project_task_id' => 'required|integer',
                    'date' => 'required|date_format:Y-m-d',
                    'notifier_ids' => 'required|array',
                    'explain' => 'required|string'
                ];
                $attributes = [
                    'name' => '里程碑名称',
                    'team_project_task_id' => '所属任务id',
                    'date' => '日期',
                    'notifier_ids' => '通知人ids',
                    'explain' => '说明'
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $validator->errors()->first()];
                }
                $milestone = new TeamProjectMilestone;
                $result = $milestone->storeMilestone($inputs);
                if($result){
                    return response()->json(['code'=>1,'message'=>'操作成功']);
                }
                return response()->json(['code'=>0,'message'=>'操作失败，请重试']);
        }

    }

    /**
     * 里程碑详情
     * @Author: renxianyong
     * @Date:   2019-04-23
     */
    public function ganttDetail()
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'里程碑id不存在']);
        }
        $data = TeamProjectMilestone::with(['teamProjectTask'=>function($query){
            $query->select('id','name');
        }])->where('id',$inputs['id'])->first(['name','team_project_task_id','date','notifier_names','explain','created_at'])->toArray();
        if($data){
            $data['created_at'] = substr($data['created_at'],0,10);
            return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
        }
        return response()->json(['code'=>0,'message'=>'获取失败，请稍后重试']);
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function storeTask(array $inputs)
    {
        $rules = [
            'team_project_id' => 'required|integer',
            'name' => 'required|string|unique:team_project_tasks,name',
            'supervisor_id' => 'required|integer',
            'executive_ids' => 'required|array',
            'priority' => 'required|integer|between:1,4',
            'deadline_date' => 'required|date',
            'start_date' => 'required|date',
            'status' => 'required|integer',
            'describe' => 'required|string',
            'files' => 'nullable|array',
        ];
        $attributes = [
            'team_project_id' => '项目id',
            'name' => '任务名称',
            'supervisor_id' => '监督人',
            'executive_ids' => '执行人',
            'priority' => '优先级',
            'deadline_date' => '计划结束日期',
            'start_date' => '计划开始时间',
            'status' => '状态',
            'describe' => '任务内容',
            'files' => '任务文档',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $team_project_task = new TeamProjectTask;
        $result = $team_project_task->storeData($inputs);
        if ($result) {
            $user_ids = $inputs['executive_ids'];
            array_push($user_ids,$inputs['supervisor_id']);
            $operator_id = auth()->id();
            $url = 'works-proj-index-view';
            $api_route_url = 'team_project_task/index';
            $api_params = ['team_project_id' => $inputs['team_project_id'], 'start' => 0, 'length' => 10];
            addNotice($user_ids,'项目协作','您被分配了一个任务，请及时跟进','',$operator_id,$url,$api_route_url,$api_params);
            systemLog('项目协作', '添加了一个任务[' . $inputs["name"] . ']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function editTask(array $inputs)
    {
        $rules = [
            'id' => 'required|integer',
            'team_project_id' => 'required|integer',
            'name' => 'required|string|unique:team_project_tasks,name,'.$inputs['id'],
            'supervisor_id' => 'required|integer',
            'executive_ids' => 'required|array',
            'priority' => 'required|integer|between:1,4',
            'deadline_date' => 'required|date',
            'start_date' => 'required|date',
            'describe' => 'required|string',
            'files' => 'nullable|array',
        ];
        $attributes = [
            'id' => '任务id',
            'team_project_id' => '项目id',
            'name' => '任务名称',
            'supervisor_id' => '监督人',
            'executive_ids' => '执行人',
            'priority' => '优先级',
            'deadline_date' => '计划结束时间',
            'start_date' => '计划开始时间',
            'describe' => '任务内容',
            'files' => '任务文档',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $team_project_task = new TeamProjectTask;
        $result = $team_project_task->storeData($inputs);
        if ($result) {
            $user_ids = $inputs['executive_ids'];
            array_push($user_ids,$inputs['supervisor_id']);
            $operator_id = auth()->id();
            $url = 'works-proj-index-view';
            $api_route_url = 'team_project_task/index';
            $api_params = ['team_project_id' => $inputs['team_project_id'], 'start' => 0, 'length' => 10];
            addNotice($user_ids,'项目协作','您的一个任务有更新，请及时跟进','',$operator_id,$url,$api_route_url,$api_params);
            systemLog('项目协作', '编辑了一个任务[' . $inputs["name"] . ']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDetail($id): \Illuminate\Http\JsonResponse
    {
        $datas = TeamProjectTask::with(['teamProjectTaskDocument' => function ($query) use ($id) {
            $query->where('model_type', 'App\Models\TeamProjectTask')->select('path_name','original_name','model_id');
        }])->where('id', $id)
            ->first()->toArray();
        if(isset($datas)){
            if ($datas['status'] != 2 && $datas['status'] != 4) {
                //根据是开始和结束时间，判断状态
                if ($datas['deadline_date'] < date('Y-m-d')) {//超时
                    $datas['status'] = 3;
                } elseif ($datas['start_date'] > date('Y-m-d')) {//未开始
                    $datas['status'] = 5;
                } elseif ($datas['start_date'] <= date('Y-m-d') && $datas['deadline_date'] >= date('Y-m-d')) {//进行中
                    $datas['status'] = 1;
                }
            }
            $host = url()->previous();//获取域名
            foreach ($datas['team_project_task_document'] as $key => $val){
                $datas['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
            }
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $datas]);
        }
        return response()->json(['code' => 1, 'message' => '获取失败，请重试']);
    }
}

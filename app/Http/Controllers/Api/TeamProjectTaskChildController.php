<?php

namespace App\Http\Controllers\Api;

use App\Models\TeamProjectTask;
use App\Models\TeamProjectTaskChild;
use App\Models\TeamProjectTaskChildFruit;
use App\Models\TeamProjectTaskDocument;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TeamProjectTaskChildController extends Controller
{
    /**
     * 子任务列表
     * @Author: renxianyong
     * @Date:   2019-03-18
     */
    public function index()
    {
        $inputs = \request()->all();
        if(!isset($inputs['team_project_task_id'])){
            return response()->json(['code' => -1,'message' => '任务id不存在']);
        }
        $team_project_task_child_model = new TeamProjectTaskChild;
        $datas['datas'] = $team_project_task_child_model->queryTaskChildDatas($inputs);
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
     * 我的子任务列表
     * @Author: renxianyong
     * @Date:   2019-03-27
     */
    public function myIndex()
    {
        $inputs = \request()->all();
        $team_project_task_child_model = new TeamProjectTaskChild;
        $datas['datas'] = $team_project_task_child_model->queryTaskMyChildDatas($inputs);
        $datas['priority'] = [
            1 => '一级',
            2 => '二级',
            3 => '三级',
            4 => '四级',
        ];
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
     * 项目子任务列表
     * @Author: renxianyong
     * @Date:   2019-03-25
     */
    public function projectIndex()
    {
        $inputs = \request()->all();
        if(!isset($inputs['team_project_id'])){
            return response()->json(['code' => -1,'message' => '项目id不存在']);
        }
        $team_project_task_child_model = new TeamProjectTaskChild;
        $datas['datas'] = $team_project_task_child_model->queryTaskChildDatas($inputs);
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
     * 新建子任务
     * @Author: renxianyong
     * @Date:   2019-03-18
     */
    public function store()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case '1'://获取用户数据
                if(!isset($inputs['team_project_task_id'])){
                    return \response()->json(['code'=>-1,'message'=>'任务id不存在']);
                }
                $task_users = TeamProjectTask::where('id',$inputs['team_project_task_id'])->first(['id','executive_ids','supervisor_id'])->toArray();
                $task_user_ids = explode(',',$task_users['executive_ids'].','.$task_users['supervisor_id']);
                $datas['users'] = User::whereIn('id',$task_user_ids)->get(['id','realname']);//用户数据
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
            default ://新建子任务
                return $this->storeTask($inputs);
        }
    }

    /**
     * 编辑子任务
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
            case 'edit'://获取用户数据和子任务数据
                if(!isset($inputs['id'])){
                    return response()->json(['code'=>-1,'message'=>'id不存在,请输入']);
                }
                $team_project_task_id = TeamProjectTaskChild::where('id',$inputs['id'])->value('team_project_task_id');//获取任务id
                $task_users = TeamProjectTask::where('id',$team_project_task_id)->first(['executive_ids','supervisor_id']);//获取任务参与人
                $task_user_ids = explode(',',$task_users['executive_ids'].','.$task_users['supervisor_id']);
                $datas['users'] = User::whereIn('id',$task_user_ids)->get(['id','realname']);
                $datas['team_project_task_child'] = TeamProjectTaskChild::where('id',$inputs['id'])->first()->toArray();
                $datas['team_project_task_child']['team_project_task_document'] = TeamProjectTaskDocument::where('model_id',$inputs['id'])
                    ->where('model_type','App\Models\TeamProjectTaskChild')
                    ->get(['id','path_name','original_name'])->toArray();
                $host = url()->previous();//获取域名
                foreach ($datas['team_project_task_child']['team_project_task_document'] as $key => $val){
                    $datas['team_project_task_child']['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
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
            default ://编辑子任务
                if(!isset($inputs['id']) && empty($inputs['id'])){
                    return response()->json(['code' => -1,'message'=>'子任务id不存在，请输入']);
                }
                return $this->editTask($inputs);
        }
    }

    /**
     * 子任务查看详情
     * @Author: renxianyong
     * @Date:   2019-03-18
     */
    public function detail()
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'子任务id不存在']);
        }
        $id = $inputs['id'];
        $datas = TeamProjectTaskChild::with(['teamProjectTaskDocument'=>function($query) use ($id){
            $query->where('model_type','App\Models\TeamProjectTaskChild')->select('path_name','original_name','model_id');
        }])->where('id',$id)
            ->first();
        if ($datas['status'] != 2 && $datas['status'] != 4 && ($datas['deadline_date'] < date('Y-m-d'))) {
            $datas['status'] = 3;
        }
        $datas['team_project_task_child_fruit'] = TeamProjectTaskChildFruit::with(['teamProjectTaskDocument'=>function($query) use($id){
            $query->where('model_type','App\Models\TeamProjectTaskChildFruit')->select('path_name','original_name','model_id');
            }])->where('team_project_task_child_id',$id)
            ->first();
        $host = url()->previous();
        if($datas['team_project_task_child_fruit']['team_project_task_document']){
            foreach($datas['team_project_task_child_fruit']['team_project_task_document'] as $key => $val){
                $datas['team_project_task_child_fruit']['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
            }
        }
        if($datas['team_project_task_document']){
            foreach($datas['team_project_task_document'] as $key => $val){
                $datas['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
            }
        }
        return response()->json(['code'=>1,'message'=>'获取成功','data'=>$datas]);
    }

    /**
     * 我的项目子任务查看详情
     * @Author: renxianyong
     * @Date:   2019-03-18
     */
    public function myDetail()
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'子任务id不存在']);
        }
        $id = $inputs['id'];
        $datas = TeamProjectTaskChild::with(['teamProjectTaskDocument'=>function($query) use ($id){
            $query->where('model_type','App\Models\TeamProjectTaskChild')->select('path_name','original_name','model_id');
        },'teamProjectTask'=>function($query){
            $query->select('id','name');
        }])->where('id',$id)
            ->first();
        if ($datas['status'] != 2 && $datas['status'] != 4 && ($datas['deadline_date'] < date('Y-m-d'))) {
            $datas['status'] = 3;
        }
        $datas['team_project_task_child_fruit'] = TeamProjectTaskChildFruit::with(['teamProjectTaskDocument'=>function($query) use($id){
            $query->where('model_type','App\Models\TeamProjectTaskChildFruit')->select('path_name','original_name','model_id');
        }])->where('team_project_task_child_id',$id)
            ->first();
        $host = url()->previous();
        if($datas['team_project_task_child_fruit']['team_project_task_document']){
            foreach($datas['team_project_task_child_fruit']['team_project_task_document'] as $key => $val){
                $datas['team_project_task_child_fruit']['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
            }
        }
        if($datas['team_project_task_document']){
            foreach($datas['team_project_task_document'] as $key => $val){
                $datas['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
            }
        }
        return response()->json(['code'=>1,'message'=>'获取成功','data'=>$datas]);
    }

    /**
     * 我的项目子任务查完成或终止
     * @Author: renxianyong
     * @Date:   2019-03-27
     */
    public function myFinish()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 'edit'://显示任务详情
                if (!isset($inputs['id'])) {
                    return \response()->json(['code' => -1, 'message' => '子任务id不存在']);
                }
                $id = $inputs['id'];
                $datas = TeamProjectTaskChild::with(['teamProjectTaskDocument' => function ($query) use ($id) {
                    $query->where('model_type', 'App\Models\TeamProjectTaskChild')->select('path_name','original_name','model_id');
                },'teamProjectTask'=>function($query){
                    $query->select('id','name');
                }])->where('id', $id)
                    ->first()->toArray();
                if ($datas['status'] != 2 && $datas['status'] != 4 && ($datas['deadline_date'] < date('Y-m-d'))) {
                    $datas['status'] = 3;
                }
                $host = url()->previous();
                foreach($datas['team_project_task_document'] as $key => $val){
                    $datas['team_project_task_document'][$key]['path_name'] = $host.$val['path_name'];
                }
                return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $datas]);
                break;
            case 'finish'://完成任务
                return $this->finishChildTask($inputs, $request_type);
                break;
            case 'start'://开始任务
                $inputs = \request()->all();
                if(isset($inputs['id']) && empty($inputs['id'])){
                    return response()->json(['code'=>-1,'message'=>'子任务id不存在']);
                }
                $child_model = TeamProjectTaskChild::where('id',$inputs['id'])->first();
                $status = $child_model->status;
                if($status == 5){
                    $child_name = $child_model->name;
                    $child_model->status = 1;
                    $result = $child_model->save();
                }else{
                    $result = false;
                }
                if($result){
                    systemLog('项目协作', '设置了任务[' . $child_name . ']为完成状态');
                    return response()->json(['code' => 1, 'message' => '操作成功']);
                }
                return response()->json(['code' => 0, 'message' => '操作失败，请重试']);
            case 'upload'://上传附件
                // 上传附件
                return $this->uploadAccessory($inputs);
                break;
            default ://终止任务
                return $this->finishChildTask($inputs, $request_type);
        }
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
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function storeTask(array $inputs)
    {
        $rules = [
            'team_project_task_id' => 'required|integer',
            'name' => 'required|string|unique:team_project_task_childs,name',
            'supervisor_id' => 'required|integer',
            'executive_id' => 'required|integer',
            'priority' => 'required|integer|between:1,4',
            'deadline_date' => 'required|date',
            'start_date' => 'required|date',
            'describe' => 'required|string',
            'files' => 'nullable|array',
        ];
        $attributes = [
            'team_project_task_id' => '任务id',
            'name' => '任务名称',
            'supervisor_id' => '监督人',
            'executive_id' => '执行人',
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
        $team_project_task_child = new TeamProjectTaskChild;
        $result = $team_project_task_child->storeData($inputs);
        if ($result) {
            $user_ids = [$inputs['supervisor_id'],$inputs['executive_id']];
            $operator_id = auth()->id();
            $url = 'works-proj-task-index-view';
            $api_route_url = 'team_project_task_child/index';
            $api_params = ['team_project_task_id'=> $inputs['team_project_task_id'], 'start'=> 0, 'length'=> 10];
            addNotice($user_ids,'项目协作','您被分配了一个子任务，请及时跟进','',$operator_id,$url,$api_route_url,$api_params);
            systemLog('项目协作', '添加了一个子任务[' . $inputs["name"] . ']');
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
            'team_project_task_id' => 'required|integer',
            'name' => 'required|string|unique:team_project_task_childs,name,'.$inputs['id'],
            'supervisor_id' => 'required|integer',
            'executive_id' => 'required|integer',
            'priority' => 'required|integer|between:1,4',
            'deadline_date' => 'required|date',
            'start_date' => 'required|date',
            'describe' => 'required|string',
            'files' => 'nullable|array',
        ];
        $attributes = [
            'id' => '子任务id',
            'team_project_task_id' => '任务id',
            'name' => '任务名称',
            'supervisor_id' => '监督人',
            'executive_id' => '执行人',
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
        $team_project_task_child = new TeamProjectTaskChild;
        $result = $team_project_task_child->storeData($inputs);
        if ($result) {
            $user_ids = [$inputs['supervisor_id'],$inputs['executive_id']];
            $operator_id = auth()->id();
            $url = 'works-proj-task-index-view';
            $api_route_url = 'team_project_task_child/index';
            $api_params = ['team_project_task_id'=> $inputs['team_project_task_id'], 'start'=> 0, 'length'=> 10];
            addNotice($user_ids,'项目协作','您的一个子任务有更新，请及时跟进','',$operator_id,$url,$api_route_url,$api_params);
            systemLog('项目协作', '编辑了一个子任务[' . $inputs["name"] . ']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }

    /**
     * @param array $inputs
     * @param string $request_type
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function finishChildTask(array $inputs, string $request_type)
    {
        $rules = [
            'id' => 'required|integer',
            'description' => 'required|string',
        ];
        $attributes = [
            'id' => '子任务id',
            'description' => '成果描述',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $team_project_task_child = new TeamProjectTaskChild;
        $result = $team_project_task_child->finishMyTask($inputs);
        if ($result) {
            $operator_id = auth()->id();
            $url = 'works-proj-task-index-view';
            $api_route_url = 'team_project_task_child/index';
            $api_params = ['team_project_task_id'=> $result['team_project_task_id'], 'start'=> 0, 'length'=> 10];
            if ($request_type == 'finish') {
                addNotice($result['user_ids'],'项目协作','您有一个子任务已完成，请查看','',$operator_id,$url,$api_route_url,$api_params);
                systemLog('项目协作', '设置了任务[' . $result['child_name'] . ']为完成状态');
            } else {
                addNotice($result['user_ids'],'项目协作','您有一个子任务终止，请查看','',$operator_id,$url,$api_route_url,$api_params);
                systemLog('项目协作', '设置了任务[' . $result['child_name'] . ']为终止状态');

            }
            systemLog('项目协作', '添加了一个子任务成果');
            if (isset($result['doc_name'])) {
                foreach ($result['doc_name'] as $val) {
                    systemLog('项目协作', '添加了一个文档[' . $val . ']');
                }
            }
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }
}

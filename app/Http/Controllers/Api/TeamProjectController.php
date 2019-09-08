<?php

namespace App\Http\Controllers\Api;

use App\Models\TeamProject;
use App\Models\User;
use http\Env\Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TeamProjectController extends Controller
{
    /*
    * 我的项目列表
    * @author renxianyong
    * @date 2019-03-15
    */
    public function index()
    {
        //获取项目和子任务数
        $user_id = auth()->id();

        //我的项目
        $datas = [];
        $datas = $this->aboutMyProject($user_id, $datas);

        return response()->json(['code' => 1,'message' => '获取成功', 'data' => $datas]);
    }

    /*
    * 项目汇总列表
    * @author renxianyong
    * @date 2019-05-13
    */
    public function projectSummary()
    {
        //获取项目和子任务数
        $user_id = auth()->id();

        //与我有关的项目
        $datas = [];
        $datas = $this->aboutMyProject($user_id, $datas);

        //与我无关的项目
        $datas['not_my_project'] = TeamProject::withCount('teamProjectTask')->with(['user' => function ($query) {
            $query->select('id', 'realname');
        }])->where('principal_id','!=', $user_id)
            ->WhereRaw("!FIND_IN_SET($user_id,participant_ids)")
            ->WhereRaw("!FIND_IN_SET($user_id,stakeholder_ids)")
            ->get(['id', 'name', 'principal_id']);

        return response()->json(['code' => 1,'message' => '获取成功', 'data' => $datas]);
    }

    /*
    * 新增项目
    * @author renxianyong
    * @date 2019-03-15
    */
    public function store()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            case '1':
                $user = User::where('status',1)->get(['id','realname']);
                return \response()->json(['code' => 1, 'message' => '获取成功', 'data' => $user]);
                break;
            default :
                return $this->addProject($inputs);
        }
    }

    /*
    * 编辑项目
    * @author renxianyong
    * @date 2019-03-15
    */
    public function edit()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        if(empty($inputs['id'])){
            return \response()->json(['code' => -1, 'message' => 'id不能为空，请输入']);
        }
        switch ($request_type){
            case 'edit'://获取项目信息和用户信息
                $team_project = TeamProject::with(['user' => function($query){
                    $query->select('id','realname');
                }])->find($inputs['id']);
                if(!$team_project){
                    return \response()->json(['code' => 0, 'message' => '项目不存在，请检查']);
                }
                $datas['user'] = User::where('status',1)->get(['id','realname']);
                $datas['team_project'] = $team_project;
                return \response()->json(['code' => 1, 'message' => '获取成功', 'data' => $datas]);
                break;
            default ://修改项目
                return $this->editProject($inputs);
        }
    }

    /*
    * 查看详情
    * @author renxianyong
    * @date 2019-03-15
    */
    public function detail()
    {
        $inputs = \request()->all();
        $rules = [
            'id' => 'required|integer'
        ];
        $attributes = [
            'id' => '项目id'
        ];
        $validator = validator($inputs,$rules,[],$attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $result = TeamProject::with(['user' => function($query){
            $query->select('id','realname');
        }])->where('id',$inputs['id'])->first();
        if($result){
            return \response()->json(['code' => 1, 'message' => '获取成功', 'data' => $result]);
        }
        return \response()->json(['code' => 0, 'message' => '获取失败，请重试']);
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function addProject(array $inputs)
    {
        $rules = [
            'name' => 'required|string|min:1|unique:team_projects',
            'describe' => 'required|string|min:1',
            'project_type'  => 'required|integer|min:1|max:2',
            'related_project' => 'required|integer|min:0|max:3',
            'principal_id' => 'required|integer|min:1',
            'participant_ids' => 'required|array',
            'stakeholder_ids' => 'required|array'
        ];
        $attributes = [
            'name' => '项目名称',
            'describe' => '项目描述',
            'project_type'  => '项目类型',
            'related_project' => '关联项目',
            'principal_id' => '项目负责人',
            'participant_ids' => '参与人',
            'stakeholder_ids' => '干系人'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $team_project = new TeamProject;
        $result = $team_project->storeData($inputs);
        if ($result) {
            $user_ids = array_merge($inputs['participant_ids'],$inputs['stakeholder_ids']);
            array_push($user_ids,$inputs['principal_id']);
            $operator_id = auth()->id();
            $url = 'works-proj-index';
            $api_route_url = 'team_project/index';
            addNotice($user_ids,'项目协作','您被分配了一个项目，请及时跟进','',$operator_id,$url,$api_route_url);
            systemLog('项目协作', '添加了一个项目[' . $inputs["name"] . ']');
            return \response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return \response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function editProject(array $inputs)
    {
        $rules = [
            'name' => 'required|string|min:1|unique:team_projects,name,'.$inputs['id'],
            'describe' => 'required|string|min:1',
            'project_type'  => 'required|integer|min:1|max:2',
            'related_project' => 'required|integer|min:0|max:3',
            'principal_id' => 'required|integer|min:1',
            'participant_ids' => 'required|array',
            'stakeholder_ids' => 'required|array'
        ];
        $attributes = [
            'name' => '项目名称',
            'describe' => '项目描述',
            'project_type'  => '项目类型',
            'related_project' => '关联项目',
            'principal_id' => '项目负责人',
            'participant_ids' => '参与人',
            'stakeholder_ids' => '干系人'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $team_project = new TeamProject;
        $result = $team_project->storeData($inputs);
        if ($result) {
            $user_ids = array_merge($inputs['participant_ids'],$inputs['stakeholder_ids']);
            array_push($user_ids,$inputs['principal_id']);
            $operator_id = auth()->id();
            $url = 'works-proj-index';
            $api_route_url = 'team_project/index';
            addNotice($user_ids,'项目协作','您的一个项目有更新，请及时跟进','',$operator_id,$url,$api_route_url);
            systemLog('项目协作', '编辑了一个项目[' . $inputs["name"] . ']');
            return \response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return \response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }

    /**
     * @param $user_id
     * @param $datas
     * @return mixed
     */
    public function aboutMyProject($user_id, $datas)
    {
        //我参与的项目
        $datas['my_involved_project_list'] = TeamProject::with(['user' => function ($query) {
            $query->select('id', 'realname');
        }])->WhereRaw("FIND_IN_SET($user_id,participant_ids)")
            ->orWhereRaw("FIND_IN_SET($user_id,stakeholder_ids)")
            ->get();
        //我的项目
        $datas['my_project_list'] = TeamProject::with(['user' => function ($query) {
            $query->select('id', 'realname');
        }])->where('principal_id', $user_id)
            ->get();
        return $datas;
    }
}

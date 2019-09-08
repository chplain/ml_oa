<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DeptController extends Controller
{
    /**
     * 添加部门
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function store()
    {
        $inputs = request()->all();
        $request_type = empty($inputs['request_type']) ? '' : $inputs['request_type'];
        switch ($request_type) {
            case 'create':
                // 添加部门页面需要的数据
                $depts = getTree((new \App\Models\Dept)->get(['id', 'name', 'parent_id']), 0); // 系统部门
                array_unshift($depts, ['id' => 0, 'name' => '顶级部门', 'parent_id' => 0]);
                $users = (new \App\Models\User)->get(); // 系统用户
                return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => ['depts' => $depts, 'users' => $users]]);
                break;
            default:
                // 添加部门
                $rules = [
                    'name' => 'required|min:1|max:30|unique:depts',
                    'description' => 'max:200',
                    'parent_id' => 'required|numeric|min:0',
                ];
                $attributes = [
                    'name' => '部门名称',
                    'description' => '描述',
                    'parent_id' => '父级ID',
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
                }
                $dept = new \App\Models\Dept;
                $result = $dept->storeData($inputs);
                if($result){
                    $response = ['code' => 1, 'message' => '操作成功'];
                    systemLog('部门管理','添加了部门['.$inputs["name"].']');
                }else{
                    $response = ['code' => 0, 'message' => '操作失败，请重试'];
                }
                return response()->json($response);
        }
    }

    /**
     * 修改部门
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function update()
    {
        $inputs = request()->all();
        $request_type = empty($inputs['request_type']) ? '' : $inputs['request_type'];
        switch ($request_type) {
            case 'edit':
                // 修改部门页面需要的数据
                $dept_id = empty($inputs['id']) ? 0 : $inputs['id'];
                $dept = new \App\Models\Dept;
                $dept_data = $dept->where('id', $dept_id)->first(); // 当前部门
                $depts = getTree($dept->get(['id', 'name', 'parent_id']), 0); // 所有部门
                array_unshift($depts, ['id' => 0, 'name' => '顶级部门', 'parent_id' => 0]);
                $users = (new \App\Models\User)->get(); // 系统用户
                return response()->json(['code' => 1, 'message' => ['dept' => $dept_data, 'depts' => $depts, 'users' => $users]]);
                break;
            default:
                // 修改部门
                $rules = [
                    'name' => 'required|min:1|max:30|unique:depts,name,' . ($inputs['id'] ?? 0),
                    'description' => 'max:200',
                    'parent_id' => 'required|numeric|min:0',
                ];
                $attributes = [
                    'name' => '部门名称',
                    'description' => '描述',
                    'parent_id' => '父级ID',
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
                }
                $dept = new \App\Models\Dept;
                $result = $dept->storeData($inputs);
                if($result){
                    $response = ['code' => 1, 'message' => '操作成功'];
                    systemLog('部门管理','修改了部门['.$inputs["name"].']');
                }else{
                    $response = ['code' => 0, 'message' => '操作失败，请重试'];
                }
                return response()->json($response);
        }
    }

    /**
     * 部门列表
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function index()
    {
        $inputs = request()->all();
        $dept = new \App\Models\Dept;
        $resources = $dept->queryDeptList($inputs);
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $resources]);
    }

    /**
     * 删除部门
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function destroy()
    {
        $id = request()->input('id', 0);
        $dept = new \App\Models\Dept;
        $dept_model = $dept->where('id',$id)->first();
        $dept_name = $dept_model['name'];
        $result = $dept_model->destroyData($id);
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('部门管理','删除了部门['.$dept_name.']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，当前部门及以下部门正在关联用户数据'];
        }
        return response()->json($response);
    }

    /**
     * 部门详情
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function show()
    {
        $dept_id = request()->input('dept_id', 0);
        $dept = new \App\Models\Dept;
        $dept_obj = $dept->where('id', $dept_id)->first();
        if (!$dept_obj) {
            return response()->json(['code' => 1, 'message' => '该部门不存在，请检查']);
        }
        $dept_obj->supervisor = $dept_obj->supervisor()->get(['id', 'username', 'realname', 'avatar']); // 部门负责人信息
        $dept_obj->user_count = $dept_obj->users()->where(function ($query) {
            $query->doesntHave('dismiss')->orWhereHas('dismiss', function($query){
                $query->where('resign_date', '>', date('Y-m-d'));
            });
        })->count(); // 部门人员数;
        // 部门用户
        $users = $dept_obj->users()->where(function ($query) {
            $query->doesntHave('dismiss')->orWhereHas('dismiss', function($query){
                $query->where('resign_date', '>', date('Y-m-d'));
            });
        })->with(['position' => function ($query) {
                $query->select(['id', 'name']);
            }])->get(); // 部门用户
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => ['dept' => $dept_obj, 'users' => $users]]);
    }
}

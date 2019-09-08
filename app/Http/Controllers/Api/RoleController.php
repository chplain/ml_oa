<?php

namespace App\Http\Controllers\Api;

use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\DocBlock\Tags\Author;

class RoleController extends Controller
{
    /**
     * 添加角色
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function store()
    {
        $inputs = request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case 'create':
                // 进入添加角色页面需要的数据
                $data = array();
                $data['sys_permissions'] = getTree((new \App\Models\Permission)->all(), 0);
                return ['code' => 1, 'message' => '获取数据成功', 'data' => $data];
                break;
            default:
                // 新增角色操作
                return $this->addRole($inputs);
        }
    }

    /**
     * 修改角色
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function update()
    {
        $inputs = request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case 'edit':
                // 加载修改角色表单是需要的表单数据
                return $this->getRoleFormData($inputs);
                break;
            default:
                // 修改角色信息接口
                return $this->editRole($inputs);
        }
    }

    /**
     * 角色列表
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function index()
    {
        $role_model = new \App\Models\Role;
        $roles = $role_model->get();
        return response()->json(['code' => 1, 'message' => '获取角色列表成功', 'data' => $roles]);
    }

    /**
     * 角色详情
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function show()
    {
        $role_id = request()->input('id', 0);
        $role = (new \App\Models\Role)->find($role_id);
        if (!$role) {
            return ['code' => 0, 'message' => '该角色不存在，请检查'];
        }
        $data = array();
        $data['role'] = $role;
        $role_permissions = $role->roleHasPermissions()->get(['id', 'parent_id']); // 当前角色的权限
        $un_format_permissions = (new Permission)->all();
        $all_permission_parent_ids = $un_format_permissions->unique('parent_id')->pluck('parent_id')->toArray();
        $role_has_permissions = [];
        foreach ($role_permissions as $key => $val) {
            if (!in_array($val['id'], $all_permission_parent_ids)) {
                array_push($role_has_permissions, $val['id']);
            }
        }
        $data['role_has_permissions'] = $role_has_permissions;
        $sys_permissions = getTree($un_format_permissions, 0); // 系统所有权限
        $data['sys_permissions'] = $sys_permissions;
        return ['code' => 1, 'message' => '获取数据成功', 'data' => $data];
    }

    /**
     * 删除角色
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function destroy()
    {
        $inputs = request()->only('id');
        if (!isset($inputs['id']) || empty($inputs['id'])) {
            return ['code' => -1, 'message' => '角色ID不能为空，请检查'];
        }
        $is_using = false;
        if (DB::table('role_has_permissions')->where('role_id', $inputs['id'])->first()) {
            $is_using = true;
        }
        if (!$is_using) {
            if (DB::table('model_has_roles')->where('role_id', $inputs['id'])->first()) {
                $is_using = true;
            }
        }
        if ($is_using) {
            return ['code' => 0, 'message' => '角色正在使用用，无法删除'];
        }
        $role = new \App\Models\Role;
        $result = $role->destroyData($inputs['id']);
        return $result ? ['code' => 1, 'message' => '操作成功'] : ['code' => 0, 'message' => '操作失败，请重试'];
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function editRole(array $inputs): array
    {
        $rules = [
            'id' => 'required|numeric|min:2',
            'name' => 'required|min:1|max:10|unique:roles,name,' . (isset($inputs['id']) ? $inputs['id'] : 0),
        ];
        $attributes = [
            'id' => '角色ID',
            'name' => '角色名称',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $role_model = new \App\Models\Role;
        $save_result = $role_model->saveData($inputs);
        if($save_result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('权限组管理','修改了权限组['.$inputs["name"].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function getRoleFormData(array $inputs): array
    {
        if (empty($inputs['id'])) {
            return ['code' => -1, 'message' => '角色ID不能为空，请检查'];
        }
        $role_model = new \App\Models\Role;
        $role = $role_model->find($inputs['id']);
        if (!$role) {
            return ['code' => 0, 'message' => '不存在该角色，请检查'];
        }
        $data = [];
        $data['role'] = $role;
        $role_permissions = $role->roleHasPermissions()->get(['id', 'parent_id']); // 当前角色的权限
        $un_format_permissions = Permission::all();
        $all_permission_parent_ids = $un_format_permissions->unique('parent_id')->pluck('parent_id')->toArray();
        $leafs = [];
        $un_leafs = [];
        foreach ($role_permissions as $key => $val) {
            if (in_array($val['id'], $all_permission_parent_ids)) {
                array_push($leafs, $val['id']);
            } else {
                array_push($un_leafs, $val['id']);
            }
        }
        $role_has_permissions = [];
        $role_has_permissions['leafs'] = $leafs;
        $role_has_permissions['un_leafs'] = $un_leafs;
        $data['role_has_permissions'] = $role_has_permissions;
        $sys_permissions = getTree($un_format_permissions, 0); // 系统所有权限
        $data['sys_permissions'] = $sys_permissions;
        return ['code' => 1, 'message' => '获取数据成功', 'data' => $data];
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function addRole(array $inputs): array
    {
        $rules = [
            'name' => 'required|min:1|max:10|unique:roles',
        ];
        $attributes = [
            'name' => '角色名称',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $save_result = (new \App\Models\Role)->saveData($inputs);
        if($save_result){
            $response = ['code' => 1,'message' => '操作成功'];
            systemLog('权限组管理','添加了权限组['.$inputs["name"].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }
}

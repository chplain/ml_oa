<?php

namespace App\Http\Controllers\Api;

use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PermissionController extends Controller
{
    /**
     * 添加权限
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function store()
    {
        $inputs = request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case 'create':
                // 加载添加权限表单数据
                $data = array();
                $data['permissions'] = getTree(Permission::whereIn('type', [0, 1])->get(['id', 'title', 'parent_id']), 0);
                array_unshift($data['permissions'], ['id' => 0, 'title' => '无', 'parent_id' => 0]);
                return ['code' => 1, 'message' => '获取数据成功', 'data' => $data];
                break;
            default:
                // 添加权限操作
                return $this->addPermission($inputs);
        }
    }

    /**
     * 修改权限
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function update()
    {
        $inputs = request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case 'edit':
                // 加载编辑表单需要的表单数据
                return $this->getPermissionFormData($inputs);
            default:
                return $this->editPermission($inputs);
        }
    }

    /**
     * 权限列表
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function index()
    {
        $permissions = getTree(Permission::all(['id', 'title', 'name', 'icon', 'type', 'parent_id', 'sort', 'created_at']), 0);
        return ['code' => 1, 'message' => '获取数据成功', 'data' => $permissions];
    }

    /**
     * 删除权限
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function destroy()
    {
        return ['code' => 0, 'message' => '暂时不提供删除权限'];
    }

    /**
     * 系统权限
     * @Author: qinjintian
     * @Date:   2018-09-13
     */
    public function sysPermissions() {
        $inputs = request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case 1:
                // 获取对应权限下的所有子权限
                if (!isset($inputs['name']) || empty($inputs['name'])) {
                    return ['code' => -1, 'message' => '路由标记不能为空，请检查'];
                }
                $permission = Permission::where('name', $inputs['name'])->first();
                if (!$permission) {
                    return ['code' => 0, 'message' => '不存这个权限，请检查'];
                }
                $sys_permissions = getTree(Permission::all(), $permission['id']);
                return ['code' => 1, 'message' => 'success', 'data' => ['sys_permissions' => $sys_permissions]];
                break;
            case 2:
                // 获取系统全部权限
                $sys_permissions = getTree(Permission::all(), 0);
                return ['code' => 1, 'message' => 'success', 'data' => ['sys_permissions' => $sys_permissions]];
                break;
            case 3:
                // 当前用户拥有的权限
                $user = auth()->user();
                $user_has_permissions = [];
                if ($user->id == 1 || $user->hasAnyRole(1)) {
                    $user_has_permissions = Permission::where('status', 1)->pluck('name');
                } else {
                    // $permissionsViaRoles = $user->getPermissionsViaRoles()->where('status', 1)->pluck('name');
                    // $directPermissions = $user->getDirectPermissions()->where('status', 1)->pluck('name');
                    // $allPermissions = $permissionsViaRoles->merge($directPermissions)->unique();
                    $allPermissions = $user->getAllPermissions()->where('status', 1)->pluck('name');
                    $user_has_permissions = $allPermissions;
                }
                return ['code' => 1, 'message' => 'success', 'data' => ['user_has_permissions' => $user_has_permissions]];
                break;
        }
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function editPermission(array $inputs): array
    {
        $rules = [
            'id' => 'required|integer|min:1',
            'title' => 'required|max:20',
            'name' => 'required|max:80|unique:permissions,name,'.$inputs['id'],
            'type' => 'required|integer|min:0',
            'parent_id' => 'required|integer|min:0',
        ];
        $attributes = [
            'id' => '权限ID',
            'title' => '菜单名称',
            'name' => '路由地址',
            'type' => '菜单类型',
            'parent_id' => '上级ID',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        // 验证权限标识是否已经存在
        $save_result = (new \App\Models\Permission)->saveData($inputs);
        if($save_result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('权限管理','修改了权限['.$inputs["title"].']-['.$inputs['name'].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function getPermissionFormData(array $inputs): array
    {
        if (empty($inputs['id'])) {
            return ['code' => -1, 'message' => '权限ID不能为空'];
        }
        $permission_model = new \App\Models\Permission;
        $permission = $permission_model->find($inputs['id']);
        $sys_permissions = getTree($permission_model->whereIn('type', [0, 1])->get(), 0);
        array_unshift($sys_permissions, ['id' => 0, 'title' => '无', 'parent_id' => 0]);
        return ['code' => 1, 'message' => '获取数据成功', 'data' => ['permission' => $permission, 'sys_permissions' => $sys_permissions]];
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function addPermission(array $inputs): array
    {
        $rules = [
            'title' => 'required|max:20',
            'name' => 'required|max:80|unique:permissions',
            'type' => 'required|integer|min:0',
            'parent_id' => 'required|integer|min:0',
        ];
        $attributes = [
            'title' => '菜单名称',
            'name' => '路由地址',
            'type' => '菜单类型',
            'parent_id' => '上级ID',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $save_result = (new \App\Models\Permission)->saveData($inputs);
        if($save_result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('权限管理','添加了权限['.$inputs["title"].']-['.$inputs['name'].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }
}

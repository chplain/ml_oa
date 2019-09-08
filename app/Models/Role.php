<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role AS SpatieRole;
use Illuminate\Support\Facades\DB;

class Role extends SpatieRole
{
    protected $table = 'roles';

    // 获取角色关联的权限
    public function roleHasPermissions()
    {
        return $this->BelongsToMany('App\Models\Permission', 'role_has_permissions', 'role_id', 'permission_id');
    }

    /**
     * 保存角色和角色对应的权限
     */
    public function saveData($inputs = array())
    {
        $role = new Role;
        if (!empty($inputs['id']) && is_numeric($inputs['id'])) {
            $role = $role->where('id', $inputs['id'])->first();
        }
        $role->name = $inputs['name']; // 角色名称(唯一标示)
        $role->description = $inputs['description'] ?? '';
        $role->status = empty($inputs['status']) ? 0 : 1; // 为空默认不启用
        $permissions = isset($inputs['permissions']) && is_array($inputs['permissions'])  ? array_unique($inputs['permissions']) : [];
        try {
            DB::transaction(function () use ($role, $permissions) {
                $role->save(); // 角色处理
                $role->syncPermissions($permissions); // 将多个权限同步赋予到一个角色
            });
            $result = true;
        } catch (\Exception $e){
            $result = false;
        }
        return $result;
    }

    /**
     * 删除角色和对应角色色权限
     */
    public function destroyData($role_id)
    {
        $role = $this->where('id', $role_id)->first();
        try {
            DB::transaction(function () use ($role) {
                // 删除角色的时候把权限和角色之间的多对多关联表中对应的关系也删除
                $role->syncPermissions([]); // 删除当前角色对应的权限
                $role->delete(); // 删除当前角色
            });
            $result = true;
        } catch (\Exception $e){
            $result = false;
        }
        return $result;
    }
}

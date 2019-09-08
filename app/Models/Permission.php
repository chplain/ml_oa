<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission AS SpatiePermission;

class Permission extends SpatiePermission
{
    protected $table = 'permissions';

    /**
     * 保存权限数据
     */
    public function saveData($inputs = array())
    {
        $permission = new Permission;
        if (!empty($inputs['id'])) {
            $permission = $permission->where('id', $inputs['id'])->first();
        }
        $permission->title = $inputs['title'];
        $permission->name = $inputs['name'];
        $permission->icon = $inputs['icon'] ?? '';
        $permission->type = $inputs['type'];
        $permission->parent_id = $inputs['parent_id'];
        $permission->sort = isset($inputs['sort']) && is_numeric($inputs['sort']) && $inputs['sort'] > 0 ? $inputs['sort'] : 0;
        $permission->status = isset($inputs['status']) && is_numeric($inputs['status']) && $inputs['status'] > 0 ? 1 : 0;
        return $permission->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dept extends Model
{
    protected $table = 'depts';

    // 部门用户
    public function users()
    {
        return $this->hasMany('App\Models\User', 'dept_id', 'id');
    }

    // 获取直接父级部门
    public function parentDept()
    {
        return $this->belongsTo('App\Models\Dept', 'parent_id', 'id');
    }

    // 获取部门负责人
    public function supervisor()
    {
        return $this->belongsTo('App\Models\User', 'supervisor_id', 'id');
    }

    /**
     * 保存部门信息
     */
    public function storeData($inputs = array())
    {
        $dept = new Dept;
        if (!empty($inputs['id'])) {
            $dept = $dept->where('id', $inputs['id'])->first();
        }
        $dept->name = $inputs['name'];
        $dept->description = $inputs['description'] ?? '';
        $dept->parent_id = $inputs['parent_id'];
        $dept->supervisor_id = $inputs['supervisor_id'] ?? 0;
        $dept->status = empty($inputs['status']) ? 0 : 1;
        return $dept->save();
    }

    /**
     * 部门列表
     */
    public function queryDeptList($inputs = array())
    {
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $querys = $this->with(['parentDept' => function ($query) {
            $query->select(['id', 'name']);
        }])->withCount(['users' => function($query) {
            $query->doesntHave('dismiss')->orWhereHas('dismiss', function($query){
                $query->where('resign_date', '>', date('Y-m-d'));
            });
        }])->when(!empty($inputs['keyword']), function ($query) use ($inputs) {
            $query->where('name', 'like', '%' . $inputs['keyword'] . '%');
        });
        $records_filtered = $querys->count(); // 符合条件的总数
        $depts = $querys->skip($start)->take($length)->get();
        return ['records_filtered' => $records_filtered, 'datalist' => $depts];
    }

    /**
     * 删除部门，假如当前部门有子部门会一并递归删除
     */
    public function destroyData($dept_id)
    {
        $dept = new Dept;
        $dept_obj = $dept->find($dept_id);
        try {
            $dept_ids = getTreeById($dept->all(), $dept_obj->id);
            array_unshift($dept_ids, $dept_obj->id);
            $user = new \App\Models\User;
            $if_useing = $user->whereIn('dept_id', $dept_ids)->count();
            if($if_useing > 0){
                return false;//部门正在使用  不能删除
            }
            return $dept->whereIn('id', $dept_ids)->delete(); // 删除部门
        } catch (Exception $e) {
            return false;
        }
    }

    //获取id对应的部门名称
    public function getIdToData(){
        $list = $this->select('id', 'name', 'supervisor_id')->get();
        $id_name = $id_storage = $id_unit = array();
        foreach ($list as $key => $value) {
            $id_name[$value->id] = $value->name;
            $id_supervisor_id[$value->id] = $value->supervisor_id;
        }
        return ['id_name' => $id_name, 'id_supervisor_id'=>$id_supervisor_id];

    }
}

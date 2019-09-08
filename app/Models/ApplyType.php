<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplyType extends Model
{
    //
    protected $table = "apply_types";
    // 获取角色关联的菜单
    public function typeHasSetting()
    {
        return $this->hasMany('App\Models\ApplyProcessSetting', 'type_id');
    }

    //获取数据
    public function getDataList($inputs = array())
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $count = $this->count();
        $query_where = $this->with(array('typeHasSetting' => function ($query) {
                            return $query->select(['type_id','setting_content','created_at']);
                        }))
                        ->when(isset($inputs['if_use']) && is_numeric($inputs['if_use']), function ($query) use ($inputs) {
                            $query->where('if_use', $inputs['if_use']);
                        })
                        ->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function ($query) use ($inputs) {
                            $query->where('name', 'like', '%'.$inputs['keywords'].'%');
                        });
        $list = $query_where->skip($start)->take($length)->get();

        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //启用、禁用
    public function if_enable($inputs = array())
    {
        $type = new ApplyType;
        if(isset($inputs['id']) && is_numeric($inputs['id'])){
            $type = $type->where('id', $inputs['id'])->first();
        }
        if(isset($inputs['if_use']) && is_numeric($inputs['if_use'])){
            $type->if_use = $inputs['if_use'];
        }
        return $type->save();
    }

    //表单类型-数组
    public function getTypes(){
        $type = new ApplyType;
        $list = $type->select(['id','name'])->get();
        $items = array();
        foreach ($list as $key => $value) {
            $items[$value->id] = $value->name;
        }
        return $items;
    }
}

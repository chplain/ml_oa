<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $table = 'positions';

    // 岗位列表信息

    public function users()
    {
        return $this->hasMany(User::class, 'position_id', 'id');
    }

    // 岗位下人员

    public function savePositions($inputs = [])
    {
        $position = new Position();
        if (!empty($inputs['id'])) {
            $position = $position->where('id', $inputs['id'])->first();
        }
        $position->name = $inputs['name'];
        $position->description = $inputs['description'] ?? '';
        return $position->save();
    }


    // 岗位列表

    public function queryPositionList($inputs = [])
    {
        $start = isset($inputs['start']) ? $inputs['start'] : 0;
        $length = isset($inputs['length']) ? $inputs['length'] : 10;
        $querys = $this->withCount(['users' => function($query) {
            $query->doesntHave('dismiss')->orWhereHas('dismiss', function($query){
                $query->where('resign_date', '>', date('Y-m-d'));
            });
        }])->when(!empty($inputs['keyword']), function ($query) use ($inputs) {
                $query->where('name', 'like', '%' . $inputs['keyword'] . '%');
            });
        $records_filtered = $querys->count(); // 符合条件的总数
        $positions = $querys->skip($start)->take($length)->get();
        return ['records_filtered' => $records_filtered, 'datalist' => $positions];
    }

    // 岗位详情

    public function detail($position_id)
    {
        $sys_depts = array_pluck(Dept::all(), 'name', 'id');
        $data = [];
        $position = $this->where('id', $position_id)->first();
        $users = $position->users()->where(function ($query) {
            $query->doesntHave('dismiss')->orWhereHas('dismiss', function ($query) {
                $query->where('resign_date', '>', date('Y-m-d'));
            });
        })->get(['dept_id', 'id', 'realname']);
        foreach ($users as $key => $value) {
            $users[$key]['dept_name'] = $sys_depts[$value['dept_id']] ?? '';
        }
        $data['position'] = $position;
        $data['users'] = $users;
        return $data;
    }
}

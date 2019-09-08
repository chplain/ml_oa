<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamProjectTaskChildFruit extends Model
{
    protected $table = 'team_project_task_child_fruits';
    
    //获取文档数据
    public function teamProjectTaskDocument()
    {
        return $this->hasMany('App\Models\TeamProjectTaskDocument','model_id','id');
    }

    //获取子任务数据
    public function teamProjectTaskChild()
    {
        return $this->belongsTo('App\Models\TeamProjectTaskChild','team_project_task_child_id','id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamProjectMilestone extends Model
{
    protected $table = 'team_project_milestones';

    //获取任务名称
    public function teamProjectTask()
    {
        return $this->belongsTo('App\Models\TeamProjectTask','team_project_task_id','id');
    }
    //新增里程碑、
    public function storeMilestone($inputs)
    {
        try{
            $notifier_names = User::whereIn('id',$inputs['notifier_ids'])->pluck('realname')->toArray();
            $team_project_milestone = new TeamProjectMilestone;
            $team_project_milestone->name = $inputs['name'];
            $team_project_milestone->team_project_task_id = $inputs['team_project_task_id'];
            $team_project_milestone->date = $inputs['date'];
            $team_project_milestone->notifier_ids = implode(',',$inputs['notifier_ids']);
            $team_project_milestone->notifier_names = implode(',',$notifier_names);
            $team_project_milestone->explain = $inputs['explain'];
            $team_project_milestone->creator_id = auth()->id();
            $team_project_milestone->save();
            return true;
        }catch (\Exception $e){
            return false;
        }

    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamProject extends Model
{
    protected $table = 'team_projects';

    protected $fillable = [
        'name', 'describe', 'project_type', 'related_project','principal_id', 'participant_ids','participant_names', 'stakeholder_ids', 'stakeholder_names','creator_id'
    ];

    //获取任务列表内容
    public function teamProjectTask()
    {
        return $this->hasMany('App\Models\TeamProjectTask', 'team_project_id', 'id');
    }

    //获取用户信息
    public function user()
    {
        return $this->belongsTo('App\models\User', 'principal_id', 'id');
    }

    //新增或修改项目信息
    public function storeData($inputs)
    {
        $team_project = new TeamProject;
        $participant_names = User::whereIn('id',$inputs['participant_ids'])->pluck('realname')->toArray();
        $stakeholder_names = User::whereIn('id',$inputs['stakeholder_ids'])->pluck('realname')->toArray();
        $inputs['participant_ids'] = implode(',', $inputs['participant_ids']);
        $inputs['participant_names'] = implode(',',$participant_names);
        $inputs['stakeholder_ids'] = implode(',', $inputs['stakeholder_ids']);
        $inputs['stakeholder_names'] = implode(',',$stakeholder_names);
        $inputs['creator_id'] = auth()->id();
        if (isset($inputs['id']) && !empty($inputs['id']) && is_numeric($inputs['id'])) {
            $team_project = $this->where('id', $inputs['id'])->first();
            $team_project->name = $inputs['name'];
            $team_project->describe = $inputs['describe'];
            $team_project->project_type = $inputs['project_type'];
            $team_project->related_project = $inputs['related_project'];
            $team_project->principal_id = $inputs['principal_id'];
            $team_project->participant_ids = $inputs['participant_ids'];
            $team_project->participant_names = $inputs['participant_names'];
            $team_project->stakeholder_ids = $inputs['stakeholder_ids'];
            $team_project->stakeholder_names = $inputs['stakeholder_names'];
            $result = $team_project->save();
        }else{
            $result = $team_project->create($inputs);
        }
        if($result){
            return true;
        }
        return false;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class TeamProjectTaskChild extends Model
{
    protected $table = 'team_project_task_childs';

    //获取子任务成果数据
    public function teamProjectTaskChildFruit()
    {
        return $this->hasOne('App\Models\TeamProjectTaskChildFruit','team_project_task_child_id','id');
    }

    //获取子任务成果文档数据
    public function teamProjectTaskDocument()
    {
        return $this->HasMany('App\Models\TeamProjectTaskDocument','model_id','id');
    }

    //获取任务数据
    public function teamProjectTask()
    {
        return $this->belongsTo('App\Models\TeamProjectTask','team_project_task_id','id');
    }

    //获取任务对应的所有子任务数据
    public function queryTaskChildDatas($inputs)
    {
        $start_date = $inputs['start_date'] ?? '';
        $end_date = $inputs['end_date'] ?? '';
        $keyword_name = $inputs['keyword_name'] ?? '';//任务名
        $keyword_executive = $inputs['keyword_executive'] ?? '';//执行人名字
        $status = $inputs['status'] ?? '';
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $query = $this->when($start_date && $end_date, function($query) use($start_date,$end_date){
            $query->whereBetween('created_at',[$start_date.' 00:00:00',$end_date.' 23:59:59']);
        })->when($keyword_name,function($query) use($keyword_name){
            $query->where('name','like','%'.$keyword_name.'%');
        })->when($keyword_executive,function($query)use($keyword_executive){
            $query->where('executive_name','like','%'.$keyword_executive.'%');
        })->when($status && $status != 3 ,function($query)use($status){//除了已超时的,其他都在数据库直接查
            $query->where('status',$status);
        })->when($status && $status == 3,function($query)use($status){//已超时的直接按日期查询
            $query->where('deadline_date','<',date('Y-m-d'));
        });
        if(isset($inputs['team_project_task_id']) && !isset($inputs['team_project_id'])){
            $querys = $query->where('team_project_task_id',$inputs['team_project_task_id']);
            $datas['team_project_task'] = TeamProjectTask::with(['teamProject'=>function($query){
                $query->select('id','name');
            }])->where('id',$inputs['team_project_task_id'])
                ->first(['id','name', 'team_project_id']);
        }elseif(isset($inputs['team_project_id']) && !isset($inputs['team_project_task_id'])){
            $task_ids = TeamProjectTask::where('team_project_id',$inputs['team_project_id'])->pluck('id');
            $datas['team_project'] = TeamProject::where('id',$inputs['team_project_id'])->value('name');
            $querys = $query->with(['teamProjectTask'=>function($query){
                $query->select('id','name');
            }])->whereIn('team_project_task_id',$task_ids);
        }
        $datas['count'] = $querys->count();
        $datas['datas'] = $querys->orderBy('created_at','DESC')->skip($start)->take($length)->get()->toArray();
        //当前时间大于计划结束时间则status改为3
        foreach($datas['datas'] as $key => $val){
            if($val != 2 && $val != 4 && ($val['deadline_date'] < date('Y-m-d'))) {
                $val['status'] = 3;
            }
            $datas['datas'][$key] = $val;
        }
        return $datas;
    }

    //获取我的子任务数据
    public function queryTaskMyChildDatas($inputs)
    {
        $start_date = $inputs['start_date'] ?? '';
        $end_date = $inputs['end_date'] ?? '';
        $keyword_name = $inputs['keyword_name'] ?? '';//任务名
        $priority = $inputs['priority'] ?? '';
        $status = $inputs['status'] ?? '';
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $executive_id = auth()->id();//执行人id
        $query = $this->with(['teamProjectTask'=>function($query){
            $query->select('id','name');
        }])->when($start_date && $end_date, function($query) use($start_date,$end_date){
            $query->whereBetween('created_at',[$start_date.' 00:00:00',$end_date.' 23:59:59']);
        })->when($keyword_name,function($query) use($keyword_name){
            $query->where('name','like','%'.$keyword_name.'%');
        })->when($priority,function($query)use($priority){
            $query->where('priority',$priority);
        })->when($status && $status != 3 ,function($query)use($status){//除了已超时的,其他都在数据库直接查
            $query->where('status',$status);
        })->when($status && $status == 3,function($query)use($status){//已超时的直接按日期查询
            $query->where('deadline_date','<',date('Y-m-d'));
        })->where('executive_id',$executive_id);
        $datas['count'] = $query->count();
        $datas['datas'] = $query->orderBy('created_at','DESC')->skip($start)->take($length)->get()->toArray();
        //当前时间大于计划结束时间则status改为3
        foreach($datas['datas'] as $key => $val){
            if ($val['status'] != 2 && $val['status'] != 4 && ($val['deadline_date'] < date('Y-m-d'))) {
                $val['status'] = 3;
            }
            $datas['datas'][$key] = $val;
        }
        return $datas;
    }

    //新增或修改任务
    public function storeData($inputs)
    {
        $host = url()->previous();
        $length = strlen($host);
        foreach($inputs['files'] as $key => $value){
            $inputs['files'][$key]['path_name'] = substr_replace($value['path_name'],'',$host,$length);
        }
        $inputs['supervisor_name'] = User::where('id',$inputs['supervisor_id'])->value('realname');//获取监督人名字
        $inputs['executive_name'] = User::where('id',$inputs['executive_id'])->value('realname');//获取执行人名字
        if($inputs['deadline_date'] < date('Y-m-d')){
            $inputs['status'] = 3;
        }
        DB::beginTransaction();
        try{
            if(isset($inputs['id']) && !empty($inputs['id']) && is_numeric($inputs['id'])) {
                $team_project_task_child = $this->where('id', $inputs['id'])->first();
                if($team_project_task_child['status'] == 2){
                    $inputs['status'] = 2;
                }elseif ($team_project_task_child['status'] == 4){
                    $inputs['status'] = 4;
                }
            }else {
                $team_project_task_child = $this;
                if(!isset($inputs['status'])){
                    $inputs['status'] = 5;//新增子任务状态默认为5
                }
                $creator_id = auth()->id();//发布人id
            }
            $team_project_task_child->team_project_task_id = $inputs['team_project_task_id'];
            $team_project_task_child->name = $inputs['name'];
            $team_project_task_child->supervisor_id = $inputs['supervisor_id'];
            $team_project_task_child->supervisor_name = $inputs['supervisor_name'];
            if(isset($inputs['status'])){
                $team_project_task_child->status = $inputs['status'];
            }
            $team_project_task_child->priority = $inputs['priority'];
            $team_project_task_child->deadline_date = $inputs['deadline_date'];
            $team_project_task_child->executive_id = $inputs['executive_id'];
            $team_project_task_child->executive_name = $inputs['executive_name'];
            $team_project_task_child->start_date = $inputs['start_date'];
            $team_project_task_child->describe = $inputs['describe'];
            if(isset($creator_id)){
                $team_project_task_child->creator_id = $creator_id;
            }
            $team_project_task_child->save();
            $task_child_id = $team_project_task_child->id;
            //添加数据到文档表
            if(isset($inputs['files']) && !empty($inputs['files'])) {
                $task_child_data = TeamProjectTaskChild::with(['teamProjectTask'=>function($query){
                    $query->select('id','team_project_id');
                }])->where('id',$task_child_id)
                    ->first(['id','team_project_task_id'])->toArray();
                //添加数据到文档表
                foreach($inputs['files'] as $key => $file){
                    if(!isset($file['team_project_task_document_id'])) {
                        $team_project_task_document = new TeamProjectTaskDocument;
                        $team_project_task_document->path_name = $file['path_name'];
                        $team_project_task_document->original_name = $file['original_name'];
                        $team_project_task_document->model_id = $task_child_id;
                        $team_project_task_document->model_type = 'App\Models\TeamProjectTaskChild';
                        $team_project_task_document->creator_id = auth()->id();
                        $team_project_task_document->team_project_id = $task_child_data['team_project_task']['team_project_id'];
                        $team_project_task_document->save();
                    }
                }
            }
            DB::commit();
            return $task_child_id;
        }catch (\Exception $e){
            DB::rollBack();
            return false;
        }
    }

    //修改子任务状态并添加成果数据
    public function finishMyTask($inputs)
    {
        $host = url()->previous();
        $length = strlen($host);
        if(!empty($inputs['files'])){
            foreach($inputs['files'] as $key => $value){
                $inputs['files'][$key]['path_name'] = substr_replace($value['path_name'],'',$host,$length);
            }
        }
        DB::beginTransaction();
        try{
            $team_project_task_child = TeamProjectTaskChild::where('id', $inputs['id'])->first();
            $datas['team_project_task_id'] = $team_project_task_child->team_project_task_id;
            $datas['user_ids'] = [$team_project_task_child->supervisor_id,$team_project_task_child->executive_id];
            $datas['child_name'] = $team_project_task_child->name;
            if(isset($inputs['request_type']) && $inputs['request_type'] == 'finish'){
                $team_project_task_child->status = 2;
            }else{
                $team_project_task_child->status = 4;
            }
            $team_project_task_child->save();
            $fruit = new TeamProjectTaskChildFruit;
            $fruit->team_project_task_child_id = $inputs['id'];
            $fruit->description = $inputs['description'];
            $fruit->creator_id = auth()->id();
            $fruit->save();
            if(isset($inputs['files']) && !empty($inputs['files'])){
                $fruit_id = $fruit->id;
                $team_project_task_id = $this->where('id',$inputs['id'])->value('team_project_task_id');
                $project_id = TeamProjectTask::where('id',$team_project_task_id)->value('team_project_id');
                foreach ($inputs['files'] as $val){
                    $document = new TeamProjectTaskDocument;
                    $document->path_name = $val['path_name'];
                    $document->original_name = $val['original_name'];
                    $document->model_id = $fruit_id;
                    $document->model_type = 'App\Models\TeamProjectTaskChildFruit';
                    $document->creator_id = auth()->id();
                    $document->team_project_id = $project_id;
                    $datas['doc_name'][] = $val['original_name'];
                    $document->save();
                }
            }
            DB::commit();
            return $datas;
        }catch (\Exception $e){
            DB::rollBack();
            return false;
        }
    }
}

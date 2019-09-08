<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeamProjectTask extends Model
{
    protected $table = 'team_project_tasks';

    //设置白名单
    protected $fillable = ['team_project_id','name','supervisor_id','supervisor_name','status','priority','deadline_date','executive_ids','executive_names','start_date','need_work_day','describe','document','creator_id'];

    //获取文档数据
    public function teamProjectTaskDocument()
    {
        return $this->hasMany('App\Models\TeamProjectTaskDocument','model_id','id');
    }

    //获取项目数据
    public function teamProject()
    {
        return $this->belongsTo('App\Models\TeamProject','team_project_id','id');
    }

    //获取项目对应的所有任务数据
    public function queryTaskDatas($inputs)
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
            $query->where('executive_names','like','%'.$keyword_executive.'%');
        })->when($status && ($status == 2 || $status == 4),function($query)use($status){
            $query->where('status',$status);
        })->when($status && $status == 1,function($query)use($status){
            $query->where('start_date','<=',date('Y-m-d'))->where('deadline_date','>=',date('Y-m-d'));
        })->when($status && $status == 3,function($query)use($status){
            $query->where('deadline_date','<',date('Y-m-d'));
        })->when($status && $status == 5,function($query)use($status){
            $query->where('start_date','>',date('Y-m-d'));
        })->where('team_project_id',$inputs['team_project_id']);
        $datas['count'] = $query->count();
        $datas['team_project'] = TeamProject::where('id',$inputs['team_project_id'])->value('name');
        $datas['datas'] = $query->orderBy('created_at','DESC')->skip($start)->take($length)->get()->toArray();
        foreach($datas['datas'] as $key => $val){
            if($val != 2 && $val != 4) {
                if ($val['deadline_date'] < date('Y-m-d')) {//超时
                    $val['status'] = 3;
                } elseif ($val['start_date'] > date('Y-m-d')) {//未开始
                    $val['status'] = 5;
                } elseif ($val['start_date'] <= date('Y-m-d') && $val['deadline_date'] >= date('Y-m-d')) {//进行中
                    $val['status'] = 1;
                }
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
        $supervisor_name = User::where('id',$inputs['supervisor_id'])->pluck('realname')->toArray();//获取监督人名字
        $executive_names = User::whereIn('id',$inputs['executive_ids'])->pluck('realname')->toArray();//获取执行人名字
        $inputs['supervisor_name'] = $supervisor_name[0];
        $inputs['executive_names'] = implode(',',$executive_names);
        $inputs['executive_ids'] = implode(',',$inputs['executive_ids']);
        if(empty($inputs['status'])){
            //status为空，则是时间判断状态
            if ($inputs['deadline_date'] < date('Y-m-d')) {//超时
                $inputs['status'] = 3;
            } elseif ($inputs['start_date'] > date('Y-m-d')) {//未开始
                $inputs['status'] = 5;
            } elseif ($inputs['start_date'] <= date('Y-m-d') && $inputs['deadline_date'] >= date('Y-m-d')) {//进行中
                $inputs['status'] = 1;
            }
        }
        DB::beginTransaction();
        try {
            if (isset($inputs['id']) && !empty($inputs['id']) && is_numeric($inputs['id'])) {
                $team_project_task = $this->where('id', $inputs['id'])->first();
                if($team_project_task['status'] == 2){
                    $inputs['status'] = 2;
                }elseif ($team_project_task['status'] == 4){
                    $inputs['status'] = 4;
                }
            } else {
                $team_project_task = $this;
                $creator_id = \auth()->id();
            }
            $team_project_task->team_project_id = $inputs['team_project_id'];
            $team_project_task->name = $inputs['name'];
            $team_project_task->supervisor_id = $inputs['supervisor_id'];
            $team_project_task->supervisor_name = $inputs['supervisor_name'];
            if(isset($inputs['status'])){
                $team_project_task->status = $inputs['status'];
            }
            $team_project_task->priority = $inputs['priority'];
            $team_project_task->deadline_date = $inputs['deadline_date'];
            $team_project_task->executive_ids = $inputs['executive_ids'];
            $team_project_task->executive_names = $inputs['executive_names'];
            $team_project_task->start_date = $inputs['start_date'];
            $team_project_task->describe = $inputs['describe'];
            if (isset($creator_id)){
                $team_project_task->creator_id = $creator_id;//发布人id
            }
            $team_project_task->save();
            $task_id = $team_project_task->id;
            if (isset($inputs['files']) && !empty($inputs['files'])) {
                foreach($inputs['files'] as $key => $file){
                    if(!isset($file['team_project_task_document_id'])){
                        $team_project_task_document = new TeamProjectTaskDocument;
                        $team_project_task_document->path_name = $file['path_name'];
                        $team_project_task_document->original_name = $file['original_name'];
                        $team_project_task_document->model_id = $task_id;
                        $team_project_task_document->model_type = 'App\Models\TeamProjectTask';
                        $team_project_task_document->creator_id = auth()->id();//发布人id
                        $team_project_task_document->team_project_id = $inputs['team_project_id'];
                        $team_project_task_document->save();
                    }
                }
            }
            DB::commit();
            return $task_id;
        }catch (\Exception $e){
            DB::rollBack();
            return false;
        }
    }
}

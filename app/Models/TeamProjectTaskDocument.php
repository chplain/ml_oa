<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamProjectTaskDocument extends Model
{
    protected $table = 'team_project_task_documents';

    //获取用户名
    public function user()
    {
        return $this->belongsTo('App\Models\User','user_id','id');
    }

    //获取项目文档列表数据
    public function queryTaskDocumentDatas($inputs)
    {
        $start_date = $inputs['start_date'] ?? '';
        $end_date = $inputs['end_date'] ?? '';
        $keyword_name = $inputs['keyword_name'] ?? '';//文件名
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $query = $this->when($start_date && $end_date, function($query) use($start_date,$end_date){
            $query->whereBetween('created_at',[$start_date.' 00:00:00',$end_date.' 23:59:59']);
        })->when($keyword_name,function($query) use($keyword_name){
            $query->where('original_name','like','%'.$keyword_name.'%');
        })->where('team_project_id',$inputs['team_project_id'])
        ->with(['user'=>function($query){
            $query->select('id','realname');
        }]);
        $count = $query->count();//获取数据总数
        $team_project_name = TeamProject::where('id',$inputs['team_project_id'])->value('name');
        $document_datas = $query->orderBy('created_at')->skip($start)->take($length)->get()->toArray();
        $team_project_task = [];
        $team_project_task_child = [];
        $team_project_task_child_fruit = [];
        foreach ($document_datas as $val){
            if($val['model_type'] == 'App\Models\TeamProjectTask'){
                $team_project_task[] = $val['model_id'];
            }elseif ($val['model_type'] == 'App\Models\TeamProjectTaskChild'){
                $team_project_task_child[] = $val['model_id'];
            }elseif ($val['model_type'] == 'App\Models\TeamProjectTaskChildFruit'){
                $team_project_task_child_fruit[] = $val['model_id'];
            }
        }
        $team_project_task = array_unique($team_project_task);
        $team_project_task_child = array_unique($team_project_task_child);
        $team_project_task_child_fruit = array_unique($team_project_task_child_fruit);
        //获取任务数据
        $team_project_task_datas = TeamProjectTask::whereIn('id',$team_project_task)->pluck('name','id');
        //获取子任务数据
        $team_project_task_child_datas = TeamProjectTaskChild::with(['teamProjectTask'=>function($query){
            $query->select('id','name');
        }])->whereIn('id',$team_project_task_child)
            ->get(['id','name','team_project_task_id'])->toArray();
        //获取子任务成果数据
        $team_project_task_child_fruit_datas = TeamProjectTaskChildFruit::with(['teamProjectTaskChild'=>function($query){
            $query->with(['teamProjectTask'=>function($query){
                $query->select('id','name');
            }])->select('id','name','team_project_task_id');
        }])->whereIn('id',$team_project_task_child_fruit)
            ->get(['id','team_project_task_child_id'])->toArray();
        $datas = [];
        $datas['count'] = $count;
        $datas['team_project_name'] = $team_project_name;
        foreach($document_datas as $val){
            if($val['model_type'] == 'App\Models\TeamProjectTask'){
                foreach($team_project_task_datas as $key => $data){
                    if($val['model_id'] == $key){
                        $val['team_project_task']['id'] = $key;
                        $val['team_project_task']['name'] = $data;
                        $val['team_project_task_child']['id'] = '0';
                        $val['team_project_task_child']['name'] = '--';
                        $datas['data'][] = $val;
                    }
                }
            }elseif($val['model_type'] == 'App\Models\TeamProjectTaskChild'){
                foreach($team_project_task_child_datas as $data){
                    if($val['model_id'] == $data['id']){
                        $val['team_project_task']['id'] = $data['id'];
                        $val['team_project_task']['name'] = $data['name'];
                        $val['team_project_task_child']['id'] = $data['team_project_task']['id'];
                        $val['team_project_task_child']['name'] = $data['team_project_task']['name'];
                        $datas['data'][] = $val;
                    }
                }
            }elseif($val['model_type'] == 'App\Models\TeamProjectTaskChildFruit'){
                foreach($team_project_task_child_fruit_datas as $data){
                    if($val['model_id'] == $data['id']){
                        $val['team_project_task']['id'] = $data['team_project_task_child']['team_project_task']['id'];
                        $val['team_project_task']['name'] = $data['team_project_task_child']['team_project_task']['name'];
                        $val['team_project_task_child']['id'] = $data['team_project_task_child']['id'];
                        $val['team_project_task_child']['name'] = $data['team_project_task_child']['name'];
                        $datas['data'][] = $val;
                    }
                }
            }
        }
        return $datas;
    }
}

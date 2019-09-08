<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingProject extends Model
{
    //培训项目管理
     protected $table = 'training_projects';

     //添加数据
     public function storeData($inputs){

     	$training = new TrainingProject;
     	if(isset($inputs['id']) && is_numeric($inputs['id'])){
     		$training = $training->where('id', $inputs['id'])->first();
     	}
     	$training->name = $inputs['name'];
     	$training->explain_people = $inputs['explain_people'];
     	$training->supervision_people = $inputs['supervision_people'];
        $training->time = $inputs['time'];
        $training->training_doc = $inputs['training_doc'] ?? '';
     	$training->test_doc = $inputs['test_doc'] ?? '';
     	return $training->save();
     }

     //获取数据列表
    public function getDataList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['keyword']) && !empty($inputs['keyword']), function ($query) use ($inputs){
                    	return $query->where('name', 'like', '%'.$inputs['keyword'].'%');
                    })
                    ->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs){
                        return $query->where('status', $inputs['status']);
                    });
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    public function getIdToData(){
        $list = $this->get();
        $id_name = $id_explain_people = $supervision_people = $time = $training_doc = $test_doc = array();
        foreach ($list as $key => $value) {
            $id_name[$value->id] = $value->name;
            $id_explain_people[$value->id] = $value->id_explain_people;
            $supervision_people[$value->id] = $value->supervision_people;
            $time[$value->id] = $value->time;
            $training_doc[$value->id] = $value->training_doc;
            $test_doc[$value->id] = $value->test_doc;
        }
        return ['id_name' => $id_name, 'id_explain_people' => $id_explain_people, 'id_supervision_people' => $supervision_people, 'id_time' => $time, 'id_training_doc' => $training_doc, 'id_test_doc' => $test_doc];

    }
}

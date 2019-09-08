<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class TrainingList extends Model
{
    //
    protected $table = 'training_lists';

    //关联申请表
    public function hasTraining()
    {
        return $this->belongsTo('App\Models\ApplyTraining', 'training_id', 'id');
    }

    //获取数据列表
    public function getDataList($inputs){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];

    	$where_query = $this->when(isset($inputs['keyword']) && !empty($inputs['keyword']), function ($query) use ($inputs) {
		    		return $query->where('name', 'like', '%'.$inputs['keyword'].'%');
		    	})
    			->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs) {
		    		return $query->where(function ($query) use ($inputs){
		    			return $query->whereBetween('start_time', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
		    		});
		    	})
		    	->when(isset($inputs['type_id']) && is_numeric($inputs['type_id']), function($query) use ($inputs) {
		    		return $query->where('type_id', $inputs['type_id']);
		    	})
		    	->when(isset($inputs['ids']) && is_array($inputs['ids']), function($query) use ($inputs) {
		    		return $query->whereIn('id', $inputs['ids']);
		    	})
		    	->when(isset($inputs['by_or_training_user']) && is_numeric($inputs['by_or_training_user']), function($query) use ($inputs) {
                    return $query->where(function($query)use($inputs){
                        return $query->where('by_training_user', $inputs['by_or_training_user'])->orWhere('training_user', $inputs['by_or_training_user']);
                    });
		    	})
		    	->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs) {
		    		return $query->where('user_id', $inputs['user_id']);
		    	})
		    	->when(isset($inputs['training_project']) && is_numeric($inputs['training_project']), function($query) use ($inputs) {
		    		return $query->where('training_project', $inputs['training_project']);
		    	})
		    	->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs) {
		    		if($inputs['status'] == 1){
		    			//未安排
		    			return $query->where('addr_id', 0);
		    		}else if($inputs['status'] == 2){
		    			//未开始
		    			return $query->where('start_time', '>', time());
		    		}else if($inputs['status'] == 3){
		    			//进行中
		    			return $query->where('start_time', '<', time())->where('end_time', '>', time());
		    		}else if($inputs['status'] == 4){
		    			//评价中
		    			return $query->whereNull('score');
		    		}else if($inputs['status'] == 5){
		    			//已结束
		    			return $query->where('end_time', '<', time());
		    		}
		    	});
    	$count = $where_query->count();
        $list = $where_query->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    public function getTrainingInfo($inputs) {
    	$training = new TrainingList;
    	$where_query = $training->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
    		return $query->where('id', $inputs['id']);
    	})
    	->with(['hasTraining' => function ($query) use ($inputs) {
    		return $query->select(['id', 'by_training_users', 'type_id', 'content']);
    	}]);
    	$info = $where_query->first(); 
    	return $info;
    }


    //批量更新
    public function updateBatch($multipleData = [])
    {
        if (empty($multipleData)) {
            return false;
        }
        $tableName = DB::getTablePrefix() . $this->getTable(); // 表名
        $firstRow  = current($multipleData);

        $updateColumn = array_keys($firstRow);
        // 默认以id为条件更新，如果没有ID则以第一个字段为条件
        $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
        unset($updateColumn[0]);
        // 拼接sql语句
        $updateSql = "UPDATE " . $tableName . " SET ";
        $sets      = [];
        $bindings  = [];
        foreach ($updateColumn as $uColumn) {
            $setSql = "`" . $uColumn . "` = CASE ";
            foreach ($multipleData as $data) {
                $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                $bindings[] = $data[$referenceColumn];
                $bindings[] = $data[$uColumn];
            }
            $setSql .= "ELSE `" . $uColumn . "` END ";
            $sets[] = $setSql;
        }
        $updateSql .= implode(', ', $sets);
        $whereIn   = collect($multipleData)->pluck($referenceColumn)->values()->all();
        $bindings  = array_merge($bindings, $whereIn);
        $whereIn   = rtrim(str_repeat('?,', count($whereIn)), ',');
        $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
        // 传入预处理sql语句和对应绑定数据
        return DB::update($updateSql, $bindings);
    }



    //生成培训信息
    public function createTraining($applyTraining){
        //通过后生成每个人每个项目的培训信息
        $training = new \App\Models\TrainingProject;
        $projects_list = $training->select(['id','explain_people'])->get();
        $projects_explain = array();
        foreach ($projects_list as $key => $value) {
            $projects_explain[$value['id']] = $value['explain_people'];//项目id对应的讲解人
        }
        $content = unserialize($applyTraining->content);
        $training_insert = array();
        $notice_users = $notice_users_by = array();//提醒
        foreach ($content as $key => $value) {
            if($applyTraining->type_id == 1){
                //入职培训
                foreach ($value['training_projects'] as $val) {
                    $items = array();
                    $items['training_id'] = $applyTraining->id;//关联申请id
                    $items['user_id'] = $applyTraining->user_id;//申请人
                    $items['name'] = $applyTraining->name;
                    $items['type_id'] = $applyTraining->type_id;
                    $items['training_project'] = $val;
                    $items['addr_id'] = $applyTraining->addr_id;
                    $items['by_training_user'] = $value['by_training_user'];
                    $items['training_user'] = $projects_explain[$val];//讲解人
                    $items['created_at'] = date('Y-m-d H:i:s');
                    $items['updated_at'] = date('Y-m-d H:i:s');
                    $training_insert[] = $items;
                    $notice_users[] = $value['by_training_user'];
                    $notice_users_by[] = $projects_explain[$val];
                }
            }else if($applyTraining->type_id == 2){
                //拓展培训 
                foreach ($value['by_training_user'] as $val) {
                    $items = array();
                    $items['training_id'] = $applyTraining->id;//关联申请id
                    $items['user_id'] = $applyTraining->user_id;//申请人
                    $items['name'] = $applyTraining->name;
                    $items['type_id'] = $applyTraining->type_id;
                    $items['training_project'] = $value['training_projects'];
                    $items['addr_id'] = $applyTraining->addr_id;
                    $items['by_training_user'] =$val;
                    $items['training_user'] = $value['training_user'];//讲解人
                    $items['start_time'] = $applyTraining->start_time;
                    $items['end_time'] = $applyTraining->end_time;
                    $items['created_at'] = date('Y-m-d H:i:s');
                    $items['updated_at'] = date('Y-m-d H:i:s');
                    $training_insert[] = $items;
                    $notice_users[] = $value['by_training_user'];
                    $notice_users_by[] = $value['training_user'];//讲解人
                }
            }
        }
        addNotice($notice_users, '培训', '您有一个新的培训^_^', '', 0, 'training-list-index','apply_training/mylist');//提醒被培训人
        addNotice($notice_users_by, '培训', '您有一个新的培训^_^', '', 0, 'training-list-index','apply_training/mylist');//提醒讲解人
        $training_list = new TrainingList;

        return $if_created_list = $training_list->insert($training_insert);;
    }
}

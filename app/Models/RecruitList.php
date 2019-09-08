<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitList extends Model
{
    //
    protected $table ='recruit_lists';

    // 获取部门信息
    public function hasDept()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    public function addData($inputs){
    	$this->apply_id = $inputs['apply_id']; //申请id
    	$this->number = $inputs['number']; //人数
    	$this->positions_id = $inputs['positions_id']; // 岗位id
    	$this->post = $inputs['post']; //岗位名称
    	$this->dept_id = $inputs['dept_id']; //部门
    	$this->type = $inputs['type']; //紧急度
    	$this->status = $inputs['status']; //状态
    	$this->apply_time = $inputs['apply_time']; //申请时间
    	return $this->save();

    }



    //获取数据列表
    public function getDataList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        
        $where_query = $this->when(isset($inputs['dept_id']) && is_numeric($inputs['dept_id']), function($query) use ($inputs){
                    return $query->where('dept_id', $inputs['dept_id']);
                })
                ->when(isset($inputs['type']) && is_numeric($inputs['type']), function($query) use ($inputs){
                    return $query->where('type', $inputs['type']);
                })
                ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                    return $query->whereBetween('apply_time', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                })
                ->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query) use ($inputs){
                    return $query->where('post', 'like', '%'.$inputs['keywords'].'%');
                })
                ->when(isset($inputs['search_status']) && is_numeric($inputs['search_status']), function($query) use ($inputs){
                    return $query->where('status', $inputs['search_status']);
                })
                ->with(['hasDept' => function($query){
                    return $query->select(['id','name','supervisor_id']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')
                ->when(!isset($inputs['export']), function ($query) use ($start,$length){
                    return $query->skip($start)->take($length);
                })
                ->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }



    /** 
    *  生成招聘信息
    *  @author molin
    *   @date 2018-11-12
    */
    public function createRecruit($applyRecruit){
        //通过后生成每个人每个项目的培训信息
        $recruit = new RecruitList;
        $items = array();
        $items['apply_id'] = $applyRecruit->id;
        $items['number'] = $applyRecruit->number;
        $items['positions_id'] = $applyRecruit->positions_id;
        $items['post'] = $applyRecruit->post;
        $items['dept_id'] = $applyRecruit->dept_id;
        $items['type'] = $applyRecruit->type;
        $items['status'] = 0;//待招聘
        $items['apply_time'] = $applyRecruit->created_at->format('Y-m-d H:i:s');//申请时间
        return $if_created_list = $recruit->addData($items);
    }

}

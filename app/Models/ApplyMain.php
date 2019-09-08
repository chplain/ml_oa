<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ApplyMain extends Model
{
    //
    protected $table = 'apply_mains';

    //1出勤申请 2物品领用申请 3采购申请 4招聘申请 5培训申请 6转正申请 7离职申请
    public $apply_types = [1=>'出勤申请',2=>'物品领用申请',3=>'采购申请',4=>'招聘申请',5=>'培训申请',6=>'转正申请',7=>'离职申请',8=>'报备申请'];

    // 获取用户信息
    public function applyUsers()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    // 获取部门信息
    public function applyDepts()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    // 获取表单类型
    public function applyTypes()
    {
        return $this->belongsTo('App\Models\ApplyType', 'type_id', 'id');
    }

    /*
    * 表单申请--保存数据 (通用表)
    * @apply_id  申请id
	* @type_id 申请类型  1出勤申请 2物品领用申请 3采购申请 4招聘申请 5培训申请 6转正申请 7离职申请
	* @table  关联表模型
	* @content 主要内容
    */
    public function storeData($apply, $type_id, $table, $content=''){
    	$items = array();
    	$items['type_id'] = $type_id;//转正
        $items['table'] = $table;//model
        $items['apply_id'] = $apply->id;//新增数据的id
        $items['content'] = $content;//内容
        $items['created_at'] = date('Y-m-d H:i:s');
        $items['updated_at'] = date('Y-m-d H:i:s');
    	$items['status'] = 0;
    	$items['status_txt'] = $apply->status_txt;
    	$items['user_id'] = auth()->user()->id;
    	$items['dept_id'] = $apply->dept_id;//部门
    	return $this->insert($items);
    }

    /*
    * 获取申请列表
    *
    */
    public function getQueryList($inputs){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $query_where = $this->with(array('applyDepts' => function($query) use ($inputs){
        					return $query->select(['id', 'name', 'supervisor_id']);
        				}))
                        ->with(array('applyTypes' => function($query) use ($inputs){
        					return $query->select(['id', 'name']);
        				}))
                        ->when(isset($inputs['apply_ids']) && is_array($inputs['apply_ids']), function($query) use ($inputs){
                            return $query->whereIn('apply_id', $inputs['apply_ids']);
                        })
        				->when(isset($inputs['dept_id']) && is_numeric($inputs['dept_id']), function($query) use ($inputs){
        					return $query->where('dept_id', $inputs['dept_id']);
        				})
                        ->when(isset($inputs['type_ids']) && is_array($inputs['type_ids']), function($query) use ($inputs){
                            return $query->whereIn('type_id', $inputs['type_ids']);
                        })
                        ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                            return $query->where('user_id', $inputs['user_id']);
                        })
        				->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query) use ($inputs){
                            return $query->where(function($query)use($inputs){
                                $query->whereHas('applyUsers', function($query)use($inputs){
                                    $query->where('realname', 'like', '%'.$inputs['keywords'].'%');
                                })->orWhere('content', 'like', '%'.$inputs['keywords'].'%');
                            });
        				})
        				->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
        					return $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
        				})
                        ->when(isset($inputs['is_mine']) && !empty($inputs['is_mine']), function($query) use ($inputs){
                            $query->where(function ($query) use ($inputs){
                                foreach ($inputs['mine_data'] as $val) {
                                    $query->orWhere(function($query) use ($val){
                                            $query->where('type_id', $val['type_id'])->where('apply_id', $val['apply_id']);
                                    });
                                }
                            })
                            ->where('user_id', $inputs['is_mine']);
                        });
        $count = $query_where->count();
        $list = $query_where->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AchievementAssignUser extends Model
{
    //分派给每个用户
    protected $table = 'achievement_assign_users';

    // 获取分派主表
    public function hasAssign()
    {
        return $this->belongsTo('App\Models\AchievementAssign', 'assign_id', 'id');
    }

    // 获取评分人、评分
    public function hasScore()
    {
        return $this->hasMany('App\Models\AchievementUserScore', 'assign_user_id', 'id');
    }

    // 获取审核人
    public function hasVerify()
    {
        return $this->hasMany('App\Models\AchievementUserVerify', 'assign_user_id', 'id');
    }

    // 关联用户表
    public function hasUser()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    //获取列表
    public function getDataList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['ids']) && is_array($inputs['ids']), function($query) use ($inputs){
                    return $query->whereIn('id', $inputs['ids']);
                })->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                    return $query->where('user_id', $inputs['user_id']);
                })
                ->when(isset($inputs['user_ids']) && is_array($inputs['user_ids']), function($query) use ($inputs){
                    return $query->whereIn('user_id', $inputs['user_ids']);
                })
                ->when(isset($inputs['cur_user_id']) && is_numeric($inputs['cur_user_id']), function($query) use ($inputs){
                    return $query->where('cur_user_id', $inputs['cur_user_id']);
                })
                ->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
                    return $query->where('status', $inputs['status']);
                })
                ->when(isset($inputs['in_status']) && is_array($inputs['in_status']), function($query) use ($inputs){
                    return $query->whereIn('status', $inputs['in_status']);
                })
                ->when(isset($inputs['year_month']) && !empty($inputs['year_month']), function($query) use ($inputs){
                    return $query->where('year_month', $inputs['year_month']);
                })
                ->when(isset($inputs['in_year_month']) && is_array($inputs['in_year_month']), function($query) use ($inputs){
                    return $query->whereIn('year_month', $inputs['in_year_month']);
                })
                ->when(isset($inputs['if_over_pingfen']) && !empty($inputs['if_over_pingfen']), function($query) use ($inputs){
                    return $query->where('if_over_pingfen', $inputs['if_over_pingfen']);
                })
                ->when(isset($inputs['dept_id']) && is_numeric($inputs['dept_id']), function($query) use ($inputs){
                    return $query->where('dept_id', $inputs['dept_id']);
                })
                ->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query) use ($inputs){
                    return $query->whereHas('hasUser', function($query) use ($inputs){
                        return $query->where('realname', 'like', '%'.$inputs['keywords'].'%');
                    });
                })
                ->when(isset($inputs['assign_user_id']) && is_numeric($inputs['assign_user_id']), function ($query) use ($inputs) {
                	return $query->whereHas('hasAssign', function($query)use($inputs){
	                	return $query->where('assign_user_id', $inputs['assign_user_id'])->select(['id','assign_user_id']);
	                });
                })
                ->with(['hasAssign'=>function($query){
                    return $query->select(['id','assign_user_id']);
                }]);
                
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')
        		->when(!isset($inputs['all']), function($query)use($start, $length){
        			return $query->skip($start)->take($length);
        		})->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    public function getDataInfo($inputs){
    	$where_query = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
			    		return $query->where('id', $inputs['id']);
			    	})
    				->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
			    		return $query->where('user_id', $inputs['user_id']);
			    	})
			    	->when(isset($inputs['cur_user_id']) && is_numeric($inputs['cur_user_id']), function ($query) use ($inputs) {
			    		return $query->where('cur_user_id', $inputs['cur_user_id']);
			    	})
			    	->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
	                    return $query->where('status', $inputs['status']);
	                })
                    ->when(isset($inputs['edit_score']) && is_numeric($inputs['edit_score']), function($query) use ($inputs){
                        return $query->where('edit_score', $inputs['edit_score']);
                    })
    				->with(['hasScore'=>function($query){
    					$query->select(['id','assign_user_id','score_user_id','score','percent','if_view','remarks']);
    				}])
                    ->with(['hasVerify'=>function($query){
                        $query->select(['id','assign_user_id','verify_user_id','status','remarks']);
                    }])
    				->when(isset($inputs['assign_user_id']) && is_numeric($inputs['assign_user_id']), function ($query) use ($inputs) {
	                	return $query->whereHas('hasAssign', function($query)use($inputs){
		                	return $query->where('assign_user_id', $inputs['assign_user_id'])->select(['id','assign_user_id']);
		                });
	                });
    	$data = $where_query->first();
    	return $data;
    }
}

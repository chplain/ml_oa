<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectLinkLog extends Model
{
    //链接分配记录表
    protected $table = 'project_link_logs';

    //关联链接表
    public function hasLink()
    {
        return $this->belongsTo('App\Models\BusinessOrderLink','link_id','id');
    }

    /*
    * start_time=end_time时  说明该链接并未分配给项目
    *
    */
    public function getLinkLogs($inputs){
    	if(!isset($inputs['project_id']) || !isset($inputs['start_time']) || !isset($inputs['end_time'])) return [];
    	$log = new ProjectLinkLog;
    	$query_where =  $log->where('project_id', $inputs['project_id'])
    						->whereRaw('start_time != end_time')
    						->when(isset($inputs['link_id']) && is_numeric($inputs['link_id']), function ($query) use ($inputs){
		                        $query->where('link_id', $inputs['link_id']);
		                    })
		                    ->when(isset($inputs['link_name']) && !empty($inputs['link_name']), function($query)use($inputs){
		                        $query->whereHas('hasLink',function($query)use($inputs){
		                            $query->where('link_name', 'like','%'.$inputs['link_name'].'%');
		                        });
		                    })
				    		->where(function($query)use($inputs){
					    		$query->where(function($query)use($inputs){
					    			$query->where(function($query)use($inputs){
					    				$query->where('start_time', '<=', strtotime($inputs['start_time']))->where('end_time', '>', strtotime($inputs['start_time']));
					    			})
					    			->orWhere(function($query)use($inputs){
						    			$query->where('start_time', '<=', strtotime($inputs['start_time']))->where('end_time', '=', 0);
						    		});
					    		})
					    		->orWhere(function($query)use($inputs){
						    		$query->where(function($query)use($inputs){
						    			$query->where('start_time', '<=', strtotime($inputs['end_time']))->where('end_time', '>', strtotime($inputs['end_time']));
						    		})
						    		->orWhere(function($query)use($inputs){
						    			$query->where('start_time', '<=', strtotime($inputs['end_time']))->where('end_time', '=', 0);
						    		});
						    	});
					    	})
					    	
					    	->with(['hasLink'=>function($query){
					    		$query->select(['id', 'link_name']);
					    	}]);
		
		$log_list = $query_where->get();
		$link_data = [];
		foreach ($log_list as $key => $value) {
			$tmp = [];
			$tmp['link_id'] = $value->link_id;
			$tmp['link_name'] = $value->hasLink->link_name;
			$link_data[] = $tmp;
		}
		return $link_data;
    }

    //当前项目当天使用的链接
    public function getProjectLinkDateInfo($project_id = 0, $date=''){
    	if($project_id == 0 || $date == '') return [];
    	$log = new ProjectLinkLog;
	    $query_where =  $log->where('project_id', $project_id)
	    					->whereRaw('start_time != end_time')
					    	->where(function($query)use($date){
					    		$query->where(function($query)use($date){
					    			$query->where('start_time', '<=', strtotime($date))->where('end_time', '>', strtotime($date));
					    		})
					    		->orWhere(function($query)use($date){
					    			$query->where('start_time', '<=', strtotime($date))->where('end_time', '=', 0);
					    		});
					    	})
					    	->with(['hasLink'=>function($query){
					    		$query->select(['id', 'link_name']);
					    	}]);
		$log_list = $query_where->get();
		$link_data = [];
		foreach ($log_list as $key => $value) {
			$tmp = [];
			$tmp['link_id'] = $value->link_id;
			$tmp['link_name'] = $value->hasLink->link_name;
			$link_data[] = $tmp;
		}
		return $link_data;
    }

    //当前链接当天使用的项目
    public function getLinkProjectDateInfo($link_id = 0, $date=''){
    	if($link_id == 0 || $date == '') return [];
    	$log = new ProjectLinkLog;
	    $query_where =  $log->where('link_id', $link_id)
	    					->whereRaw('start_time != end_time')
					    	->where(function($query)use($date){
					    		$query->where(function($query)use($date){
					    			$query->where('start_time', '<=', strtotime($date))->where('end_time', '>', strtotime($date));
					    		})
					    		->orWhere(function($query)use($date){
					    			$query->where('start_time', '<=', strtotime($date))->where('end_time', '=', 0);
					    		});
					    	});
		
		return $query_where->first();
    }
}

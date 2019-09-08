<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class LinkFeedback extends Model
{
    //链接反馈表
    protected $table = 'link_feedbacks';

    //关联链接表
    public function hasLink()
    {
        return $this->belongsTo('App\Models\BusinessOrderLink','link_id','id');
    }

    public function getLinkFeedbackByProjectId($inputs){
        if(!isset($inputs['project_id'])) return ['records_total' => 0, 'records_filtered' => 0, 'datalist' => []];
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $query_where = $this->where('project_id', $inputs['project_id'])
                    ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs){
                        $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                    })
                    ->with(['hasLink'=>function($query){
                        $query->select(['id', 'link_name', 'pricing_manner']);
                    }]);
        $count = $query_where->count();
        $list = $query_where->select(DB::raw('link_id,SUM(cpa_price) as cpa_price,SUM(cpa_amount) as cpa_amount,SUM(cps_price) as cps_price,SUM(cps_amount) as cps_amount,SUM(cpc_price) as cpc_price,SUM(cpd_price) as cpd_price,SUM(money) as money'))
                ->groupBy('link_id')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    public function getLinkFeedbackByLinkId($inputs){
        if(!isset($inputs['project_id']) || !isset($inputs['link_id'])) return ['records_total' => 0, 'records_filtered' => 0, 'datalist' => []];
        $query_where = $this->where('link_id', $inputs['link_id'])->where('project_id', $inputs['project_id'])
                    ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs){
                        $query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
                    })
                    ->with(['hasLink'=>function($query){
                        $query->select(['id', 'link_name', 'pricing_manner']);
                    }]);
        return $query_where->select(['link_id','date','cpa_price','cps_price','cpa_amount','cpd_price','money'])->get();
    }

    public function getLinkFeedback($inputs){
    	$query_where = $this->when(isset($inputs['project_ids']) && is_array($inputs['project_ids']), function ($query) use ($inputs){
			    		$query->whereIn('project_id', $inputs['project_ids']);
			    	})
    				->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs){
    					$query->whereBetween('date', [$inputs['start_time'], $inputs['end_time']]);
    				})
    				->with(['hasLink'=>function($query){
    					$query->select(['id', 'link_name']);
    				}]);
    	return $query_where->get();
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

}

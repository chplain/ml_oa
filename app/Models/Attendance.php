<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class Attendance extends Model
{
    //
    protected $table = 'attendances';

    //白名单
    protected $fillable = ['date', 'type', 'year', 'month', 'day', 'created_at', 'updated_at'];

    //添加节假日 获取当年节假日
    public function addHolidays($h_arr){
    	
		return $this->insert($h_arr);

    }

    //获取数据
    public function getDataList($inputs){
    	$where_query = $this->when(isset($inputs['month']) && !empty($inputs['month']), function($query) use ($inputs) {
    			return $query->where('month', $inputs['month']);
    		})
    		->when(isset($inputs['year']) && !empty($inputs['year']), function($query) use ($inputs){
    			return $query->where('year', $inputs['year']);
    		});

    	$list = $where_query->select(['id','date','type'])->get();
    	return $list;

    }

    //更新数据
    public function storeData($inputs){
    	$attend =  new Attendance;	
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$attend = $attend->where('id', $inputs['id'])->first();
    	}
    	$attend->type = $inputs['type'];

   		return $attend->save();
    }

    //批量更新
    public function updateBatch($multipleData = [])
    {
        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
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
        } catch (\Exception $e) {
            return false;
        }
    }

    //日期对应工作日、节假日
    public function getDateWork($inputs){
        $att = new Attendance;
        $att_list = $att->when(isset($inputs['year']) && !is_numeric($inputs['year']), function ($query) use ($inputs){
                        return $query->where('year', $inputs['year']);
                    })
                    ->when(isset($inputs['month']) && !empty($inputs['month']), function ($query) use ($inputs){
                        return $query->where('month', $inputs['month']);
                    })
                    ->when(isset($inputs['start_date']) && !empty($inputs['start_date']) && isset($inputs['end_date']) && !empty($inputs['end_date']), function ($query) use ($inputs){
                        return $query->whereBetween('date', [$inputs['start_date'], $inputs['end_date']]);
                    })
                    ->get();
        if(empty($att_list)){
            return array();
        }
        $items = array();
        foreach ($att_list as $key => $value) {
            $items[$value->date] = $value->type;
        }
        return $items;
    }
}

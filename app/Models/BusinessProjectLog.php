<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BusinessProjectLog extends Model
{
    protected $table = 'business_project_logs';

    //获取执行人名称
    public function queryExecute()
    {
        return $this->belongsTo('App\Models\User','execute_id','id');
    }

    //获取负责人名称
    public function queryCharge()
    {
        return $this->belongsTo('App\Models\User','charge_id','id');
    }

    //获取负责人名称
    public function businessProjectLogType()
    {
        return $this->belongsTo('App\Models\BusinessProjectLogType','business_project_log_type_id','id');
    }

    //查询项目的操作记录
    public function queryDatas($inputs)
    {
        $start_date = $inputs['start_date'] ?? '';
        $end_date = $inputs['end_date'] ?? '';
        $type = $inputs['business_project_log_type_id'] ?? '';//操作类型
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $query = $this->with(['queryExecute'=>function($query){
            $query->select('id','realname');
        },'queryCharge'=>function($query){
            $query->select('id','realname');
        },'businessProjectLogType'=>function($query){
            $query->select('id','type');
        }])->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
            $query->whereBetween('date', [$start_date , $end_date]);
        })->when($type, function ($query) use ($type) {
            $query->where('business_project_log_type_id', $type);
        })->where('business_project_id', $inputs['business_project_id']);
        $datas['count'] = $query->count();
        $datas['datas'] = $query->orderBy('date', 'DESC')->skip($start)->take($length)->get();
        return $datas;
    }

    //新增或修改操作记录
    public function storeDatas($inputs)
    {
        try{
            if(isset($inputs['id']) && !empty($inputs['id']) && is_numeric($inputs['id'])){
                //修改操作日志
                $business_project_log = BusinessProjectLog::where('id',$inputs['id'])->first();
            }else{
                $business_project_log = $this;
            }
            $project_datas = BusinessProject::where('id',$inputs['business_project_id'])->first(['charge_id','execute_id'])->toArray();
            $business_project_log->business_project_id = $inputs['business_project_id'];
            $business_project_log->business_project_log_type_id = $inputs['business_project_log_type_id'];
            $business_project_log->content = $inputs['content'];
            $business_project_log->charge_id = $project_datas['charge_id'];
            $business_project_log->execute_id = $project_datas['execute_id'];
            $business_project_log->date = $inputs['date'];
            $business_project_log->save();
            return true;
        }catch (\Exception $e){
            return false;
        }
    }
}

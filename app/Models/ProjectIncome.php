<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ProjectIncome extends Model
{
    //项目收入表
    protected $table = 'project_incomes';

    public $invoice_type = [['id'=>1, 'name'=>'普票'],['id'=>2, 'name'=>'专票'],['id'=>3, 'name'=>'通用机打发票'],['id'=>4, 'name'=>'地税服务票']];
    public $invoice_content = [['id'=>1, 'name'=>'技术咨询服务费'],['id'=>2, 'name'=>'技术信息服务费'],['id'=>3, 'name'=>'网络技术服务费'],['id'=>4, 'name'=>'广告发布费'],['id'=>5, 'name'=>'广告宣传费'],['id'=>6, 'name'=>'广告推广费'],['id'=>7, 'name'=>'服务费'],['id'=>8, 'name'=>'信息服务费']];


    //关联表
    public function hasIncomeInvoice()
    {
        return $this->hasMany('App\Models\ProjectIncomeInvoice', 'income_id', 'id');
    }

    public function storeData($insert_data){
        if(empty($insert_data)) return false;
        $income = new ProjectIncome;
        return $income->insert($insert_data);
    }

    //获取收入数据列表
    public function getIncomeList($inputs){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query =  $this->when(isset($inputs['ids']) && is_array($inputs['ids']), function($query) use ($inputs){
				            $query->whereIn('id', $inputs['ids']);
				        })
        				->when(isset($inputs['project_name']) && !empty($inputs['project_name']), function ($query) use ($inputs){
                            $query->where('project_name', 'like', '%'.$inputs['project_name'].'%');
                        })
                        ->when(isset($inputs['customer_name']) && !empty($inputs['customer_name']), function ($query) use ($inputs){
                            $query->where('customer_name', 'like', '%'.$inputs['customer_name'].'%');
                        })
                        ->when(isset($inputs['sale_man']) && !empty($inputs['sale_man']), function ($query) use ($inputs){
                            $query->where('sale_man', 'like', '%'.$inputs['sale_man'].'%');
                        })
                        ->when(isset($inputs['settle_month']) && !empty($inputs['settle_month']), function ($query) use ($inputs){
                            $query->where('month', date('Ym',strtotime($inputs['settle_month'])));
                        })
                        ->when(isset($inputs['arrival_date']) && !empty($inputs['arrival_date']), function ($query) use ($inputs){
                            $query->whereBetween('arrival_date', [$inputs['arrival_date'].' 00:00:00', $inputs['arrival_date'].' 23:59:59']);
                        })
                        ->when(isset($inputs['resource']) && !empty($inputs['resource']), function ($query) use ($inputs){
                            $query->where('resource', $inputs['resource']);
                        })
                        ->when(isset($inputs['income_main']) && !empty($inputs['income_main']), function ($query) use ($inputs){
                            $query->where('income_main', $inputs['income_main']);
                        })
                        ->when(isset($inputs['trade_id']) && is_numeric($inputs['trade_id']), function ($query) use ($inputs){
                            $query->where('trade_id', $inputs['trade_id']);
                        })
                        ->when(isset($inputs['business_id']) && is_numeric($inputs['business_id']), function($query) use ($inputs){
				            return $query->where('business_id', $inputs['business_id']);
				        })
				        ->when(isset($inputs['project_id']) && is_numeric($inputs['project_id']), function($query) use ($inputs){
				            return $query->where('project_id', $inputs['project_id']);
				        })
				        ->when(isset($inputs['if_invoice']) && is_numeric($inputs['if_invoice']), function($query) use ($inputs){
				            return $query->where('if_invoice', $inputs['if_invoice']);
				        })
                        ->with(['hasIncomeInvoice']);
        $count = $where_query->count();
        if(isset($inputs['all'])){
            $list = [];//分块查询  --这里做个例子
            $where_query->orderBy('id', 'desc')->chunk(1000, function ($query) use (&$list){
                foreach ($query as $value) {
                    $list[] = $value;
                }
            });
        }else{
            $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        }
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取单条
    public function getIncomeInfo($inputs){
        $query_where = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function($query) use ($inputs){
			            return $query->where('id', $inputs['id']);
			        })
			        ->when(isset($inputs['charge_id']) && is_numeric($inputs['charge_id']), function($query) use ($inputs){
			            return $query->where('charge_id', $inputs['charge_id']);
			        });
        $info = $query_where->first();
        return $info;
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
}

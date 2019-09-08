<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class ProjectInvoice extends Model
{
    //发票表
    protected $table = 'project_invoices';

    //保存数据
    public function storeData($inputs){
    	$invoice = new ProjectInvoice;
    	$insert = array();
    	foreach ($inputs['receipt_data'] as $key => $value) {
    		$invoice->user_id = auth()->user()->id;
    		$invoice->customer_id = $inputs['customer_id'];
    		$invoice->customer_name = $inputs['customer_name'];
    		$invoice->sale_man = $inputs['sale_man'];
    		$invoice->company = $value['name'];
    		$invoice->invoice_type = $value['invoice_type'];
    		$invoice->invoice_content = $value['invoice_content'];
    		$invoice->taxpayer = $value['taxpayer'];
    		$invoice->address = $value['address'];
    		$invoice->tel = $value['tel'];
    		$invoice->bank = $value['bank'];
    		$invoice->bank_account = $value['bank_account'];
    		$invoice->income_ids = serialize($value['income_ids']);
    		$invoice->status = 0;
    		$invoice->month = $value['month'];
    		$invoice->total_amount = $value['total_amount'];
            $res = $invoice->save();
            if(!$res) return false;
            foreach ($value['income_ids'] as $k => $v) {
                $tmp = array();
                $tmp['invoice_id'] = $invoice->id;
                $tmp['income_id'] = $v['id'];
                $tmp['money'] = $v['money'];
                $tmp['created_at'] = date('Y-m-d H:i:s');
                $tmp['updated_at'] = date('Y-m-d H:i:s');
                $insert[] = $tmp;
            }
    	}
    	if(!empty($insert)){
            $income_invice = new \App\Models\ProjectIncomeInvoice;
    		$res2 = $income_invice->insert($insert);
            if(!$res2) return;
    	}
    	return true;
    }

    //获取发票列表
    public function getInvoiceList($inputs){        
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query =  $this->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function($query)use($inputs){
                            $query->where(function($query)use($inputs){
                            	$query->where('customer_name', 'like', '%'.$inputs['keywords'].'%')->orWhere('sale_man', 'like', '%'.$inputs['keywords'].'%')->orWhere('company', 'like', '%'.$inputs['keywords'].'%');
                            });
                        })
                        ->when(isset($inputs['ids']) && is_array($inputs['ids']), function($query)use($inputs){
                            $query->whereIn('id', $inputs['ids']);
                        })
                        ->when(isset($inputs['user_id']), function($query)use($inputs){
                            $query->where('user_id', $inputs['user_id']);
                        })
                        ->when(isset($inputs['status']), function($query)use($inputs){
                            $query->where('status', $inputs['status']);
                        })
                        ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query)use($inputs){
                        	$start_time = date('Ym', strtotime($inputs['start_time']));
                        	$end_time = date('Ym', strtotime($inputs['end_time']));
                            $query->whereBetween('month', [$start_time, $end_time]);
                        });
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->when(!isset($inputs['all']),function($query)use($start,$length){
                    $query->skip($start)->take($length);
                })->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //开票、作废、到账操作
    public function setAccountEntry($income, $invoice_info, $update_data,$income_invoice_where=[],$income_invoice_update=[]){
    	DB::transaction(function () use ($income, $invoice_info, $update_data, $income_invoice_where,$income_invoice_update) {
			$re = $invoice_info->save();
			if(!$re) return false; 
            if(!empty($update_data)){
                $re2 = $income->updateBatch($update_data);//更改到帐金额
                if(!$re2) return false; 
            }
            $income_invoice = new \App\Models\ProjectIncomeInvoice;
            if(!empty($income_invoice_update)){
                $re3 =$income_invoice->where($income_invoice_where)->update($income_invoice_update);
                if(!$re3) return false; 
            }
		}, 5);
		return true;
    }

    //删除发票操作
    public function delAccountEntry($income, $update_data, $invoice_id){
        DB::transaction(function () use ($income, $update_data, $invoice_id) {
            $invoice = new ProjectInvoice;
            $re = $invoice->where('id', $invoice_id)->delete();
            if(!$re) return false; 
            $re2 = $income->updateBatch($update_data);//更改到帐金额
            if(!$re2) return false; 
            $income_invoice = new \App\Models\ProjectIncomeInvoice;
            $re3 = $income_invoice->where('invoice_id', $invoice_id)->delete();//删除
            if(!$re3) return false; 
        }, 5);
        return true;
    }

    //批量开票
    public function setOpen($income, $invoice, $invoice_update, $income_update){
        DB::transaction(function () use ($income, $invoice, $invoice_update, $income_update) {
            $re = $invoice->updateBatch($invoice_update);
            if(!$re) return false; 
            $re = $income->updateBatch($income_update);//更改到帐金额
            if(!$re) return false; 
        }, 5);
        return true;
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectIncomeInvoice extends Model
{
    //
    protected $table = 'project_income_invoices';

    //关联表
    public function hasInvoice()
    {
        return $this->belongsTo('App\Models\ProjectInvoice', 'invoice_id', 'id');
    }

    public function storeData($inputs){
    	if(count($inputs) == 0) return;
    	$obj = new ProjectIncomeInvoice;
    	return $obj->insert($inputs);
    }

    public function getInvoiceListByIncomeId($inputs){
    	$obj = new ProjectIncomeInvoice;
    	$query_where = $obj->when(isset($inputs['income_id']) && is_numeric($inputs['income_id']), function($query)use($inputs){
    				$query->where('income_id', $inputs['income_id']);
    			})
    			->with(['hasInvoice']);
    	$list = $query_where->orderBy('id', 'desc')->get();
    	return $list;
    }
}

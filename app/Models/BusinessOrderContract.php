<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOrderContract extends Model
{
    //客户合同
    protected $table = 'business_order_contracts';

    public function storeData($inputs){
    	$contract = new BusinessOrderContract;
    	$contract->customer_id = $inputs['customer_id'];
    	$contract->customer_name = $inputs['customer_name'];
    	$contract->type = $inputs['type'];
    	$contract->deadline = $inputs['deadline'];
    	$contract->number = $inputs['number'];
        $contract->if_auto = $inputs['if_auto'];
    	$contract->file_name = $inputs['file_name'];
        $contract->file_url = $inputs['file_url'];
    	$contract->user_id = auth()->user()->id;//上传人id
    	return $contract->save();
    }
}

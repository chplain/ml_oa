<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOrderReceipt extends Model
{
    //客户开票公司
    protected $table = 'business_order_receipts';

    //保存数据
    public function storeData($inputs){
    	$receipt = new BusinessOrderReceipt;
    	$insert = array();
    	foreach ($inputs['receipt'] as $key => $value) {
    		$insert[$key]['customer_id'] = $inputs['id'];
    		$insert[$key]['name'] = $value['name'];
    		$insert[$key]['remarks'] = $value['remarks'] ?? '';
            $insert[$key]['invoice_type'] = $value['invoice_type'] ?? 0;
            $insert[$key]['invoice_content'] = $value['invoice_content'] ?? 0;
            $insert[$key]['taxpayer'] = $value['taxpayer'] ?? '';
            $insert[$key]['address'] = $value['address'] ?? '';
            $insert[$key]['tel'] = $value['tel'] ?? '';
            $insert[$key]['bank'] = $value['bank'] ?? '';
            $insert[$key]['bank_account'] = $value['bank_account'] ?? '';
    		$insert[$key]['created_at'] = date('Y-m-d H:i:s');
    		$insert[$key]['updated_at'] = date('Y-m-d H:i:s');
    	}
    	return $receipt->insert($insert); 
    }

    public function updateData($inputs){
        $receipt = new BusinessOrderReceipt;
        if(isset($inputs['receipt_id']) && is_numeric($inputs['receipt_id'])){
            $receipt = $receipt->where('id', $inputs['receipt_id'])->first();
        }
        $receipt->customer_id = $inputs['customer_id'];
        $receipt->name = $inputs['name'];
        $receipt->remarks = $inputs['remarks'];
        $receipt->invoice_type = $inputs['invoice_type'];
        $receipt->invoice_content = $inputs['invoice_content'];
        $receipt->taxpayer = $inputs['taxpayer'];
        $receipt->address = $inputs['address'];
        $receipt->tel = $inputs['tel'];
        $receipt->bank = $inputs['bank'];
        $receipt->bank_account = $inputs['bank_account'];
        return $receipt->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class BusinessOrderCustomer extends Model
{
    //客户表
	protected $table = 'business_order_customers';

	// 获取用户
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'sale_user_id', 'id');
    }

    // 合同
    public function contract()
    {
        return $this->hasMany('App\Models\BusinessOrderContract', 'customer_id', 'id');
    }

    // 商务单
    public function order()
    {
        return $this->hasMany('App\Models\BusinessOrder', 'customer_id', 'id');
    }

    // 开票公司
    public function receipt()
    {
        return $this->hasMany('App\Models\BusinessOrderReceipt', 'customer_id', 'id');
    }

    //添加
	public function storeData($inputs){
		$customer = new BusinessOrderCustomer;
		$customer->customer_name = $inputs['customer_name'];
		$customer->customer_type = $inputs['customer_type'];
		$customer->sale_user_id = $inputs['sale_user_id'];
		$customer->contacts = $inputs['contacts'];
		$customer->customer_tel = $inputs['customer_tel'];
		$customer->customer_email = $inputs['customer_email'] ?? '';
		$customer->customer_qq = $inputs['customer_qq'] ?? '';
		$customer->bank_accounts = $inputs['bank_accounts'] ?? '';
		$customer->customer_address = $inputs['customer_address'] ?? '';
		
		DB::transaction(function () use($customer,$inputs){
		    $re1 = $customer->save();
		    if(!$re1) return false;
    		//合同
            if(isset($inputs['number']) && !empty($inputs['number'])){
                //合同非必填
                $contract = new \App\Models\BusinessOrderContract;
                $contract->customer_id = $customer->id;//客户id
                $contract->customer_name = $customer->customer_name;
                $contract->type = $inputs['type'];
                $contract->deadline = $inputs['deadline'];
                $contract->number = $inputs['number'];
                $contract->if_auto = $inputs['if_auto'];
                $contract->file_url = $inputs['file_url'];
                $contract->file_name = $inputs['file_name'];
                $re2 = $contract->save();
                if(!$re2) return false;
            }
    		
		}, 5);
		return true;
	}

	//获取数据列表
    public function getQueryList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['customer_type']) && is_numeric($inputs['customer_type']), function ($query) use ($inputs){
                    return $query->where('customer_type', $inputs['customer_type']);
                })
                ->when(isset($inputs['customer_name']) && !empty($inputs['customer_name']), function ($query) use ($inputs) {
                    return $query->where('customer_name', 'like', '%'.$inputs['customer_name'].'%');
                })
                ->when(isset($inputs['sale_user']) && !empty($inputs['sale_user']), function ($query) use ($inputs) {
                    return $query->whereHas('user', function ($query2) use ($inputs){
                    	return $query2->where('realname', 'like', '%'.$inputs['sale_user'].'%');
                    });
                })
                ->with(['user' => function ($query) {
                	return $query->select(['id', 'realname']);
                }])
                ->with(['contract' => function ($query) {
                	return $query->select(['customer_id', 'number']);
                }])
                ->with(['receipt' => function ($query) {
                    return $query->select(['id','customer_id', 'name', 'remarks']);
                }])
                ->with(['order' => function ($query) {
                	return $query->select(['id', 'customer_id', 'swd_id']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取单条记录
    public function getQueryInfo($inputs){
        $where_query = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs){
                    return $query->where('id', $inputs['id']);
                })
                ->with(['user' => function ($query) {
                	return $query->select(['id', 'realname']);
                }])
                ->with(['contract' => function ($query) {
                	return $query->select(['id','customer_id','number','deadline','type']);
                }])
                ->with(['receipt'])
                ->with(['order' => function ($query) {
                	return $query->select(['id', 'customer_id', 'swd_id']);
                }]);
        $info = $where_query->first();
        return $info;
    }

    //编辑
    public function updateData($inputs){
		$customer = new BusinessOrderCustomer;
		if(isset($inputs['id']) && is_numeric($inputs['id'])){
			$customer = $customer->where('id', $inputs['id'])->first();
		}
		$customer->customer_name = $inputs['customer_name'];
		$customer->customer_type = $inputs['customer_type'];
		$customer->sale_user_id = $inputs['sale_user_id'];
		$customer->contacts = $inputs['contacts'];
		$customer->customer_tel = $inputs['customer_tel'];
		$customer->customer_email = $inputs['customer_email'];
		$customer->customer_qq = $inputs['customer_qq'];
		$customer->bank_accounts = $inputs['bank_accounts'];
		$customer->customer_address = $inputs['customer_address'];
		return $customer->save();
	}
	
}

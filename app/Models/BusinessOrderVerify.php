<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class BusinessOrderVerify extends Model
{
    //商务单审核表
    protected $table = 'business_order_verifys';

    //配置下一位审核人
    public function setNextVerify($verify_info, $order_info, $inputs){
    	DB::transaction(function () use($verify_info,$order_info,$inputs){
            $verify_info->save();
            $order_info->save();
            if($inputs['pass'] == 1 && $inputs['verify_type'] == 1){
            	//添加下一位审核人
            	$verify = new \App\Models\BusinessOrderVerify;
            	$verify->order_id = $order_info->id;
            	$verify->user_id = $inputs['next_user_id'];
            	$verify->status = 0;
            	$verify->save();
            }
        }, 5);
        return true;
    }
}

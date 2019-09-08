<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsUseChange extends Model
{
    //
    protected $table = 'goods_use_changes';

    public function storeData($inputs){
		$change = new GoodsUseChange;
    	$change->use_id = $inputs['id'];
    	$change->number = $inputs['number'];
    	$change->type = $inputs['type'];//类型 1归还 2更换
    	$change->status = 0;
    	$change->remarks = $inputs['remarks'] ?? '';
    	$change->user_id = auth()->user()->id;//点击申请人id
    	return $change->save();
    }
}

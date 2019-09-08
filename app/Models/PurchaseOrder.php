<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    //
    protected $table = "purchase_orders";

    public function storeData($inputs){
    	$order = new PurchaseOrder;
    	$order->apply_id = $inputs['id'];
    	$order->way = $inputs['way'];
    	$order->spec = $inputs['spec'];
    	$order->images = implode(',', $inputs['images']);
    	$order->price = $inputs['price'];
    	$order->num = $inputs['num'];
    	$order->order_sn = $inputs['order_sn'];
    	$order->express_sn = $inputs['express_sn'];
    	$order->user_id = auth()->user()->id;
    	return $order->save();

    }
}

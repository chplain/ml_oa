<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingAddr extends Model
{
    //
    protected $table = 'training_addrs';

    public function getAddrData(){
    	$addr_list = TrainingAddr::all();
    	$items = array();
    	if(!empty($addr_list)){
    		foreach ($addr_list as $key => $value) {
    			$items[$value['id']] = $value['name'];
    		}
    	}
    	return $items;
    }
}

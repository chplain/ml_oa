<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HolidayType extends Model
{
    //
    protected $table = 'holiday_types';
 	
    //保存数据
 	public function storeData($inputs){
 		$type = new HolidayType;
 		if(isset($inputs['id']) && is_numeric($inputs['id'])){
 			$type = $type->where('id', $inputs['id'])->first();
 		}

 		$type->name = $inputs['name'];
 		$type->way = 1;
 		$type->if_lianxiu = $inputs['if_lianxiu'];
 		$type->if_nianjia = $inputs['if_nianjia'];
 		$type->lianxiu_date = $inputs['lianxiu_date'];
 		$type->if_cancel_full_att = $inputs['if_cancel_full_att'];
 		$type->if_cancel_salary = $inputs['if_cancel_salary'];
 		$type->salary_percent = $inputs['salary_percent'];
 		$type->condition = serialize($inputs['condition']);
 		$type->suit = $inputs['suit'];
 		$type->suit_sex = $inputs['suit_sex'];
 		return $type->save();
 	}   

 	//id对应name
 	public function getIdToData(){
 		$holiday = new HolidayType;
        $list = $holiday->select(['id','name'])->get();
        $data = array();
        foreach ($list as $key => $value) {
        	$data[$value->id] = $value->name;
        }
        return $data;
 	}
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditProcessStepRecord extends Model
{
    //
    protected $table = 'audit_process_step_records';

    public function storeData($inputs){
    	$this->type_id = $inputs['type_id'];
    	$this->apply_id = $inputs['apply_id'];
    	$this->step = $inputs['step'];
    	$this->status = $inputs['status'];
    	$this->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectFeedbackLog extends Model
{
    protected $table = 'project_feedback_logs';

    public function storeData($inputs = []){
    	if(empty($inputs)) return;
    	$log = new ProjectFeedbackLog;
    	$log->fid = $inputs['fid'] ?? 0;
    	$log->lid = $inputs['lid'] ?? 0;
    	$log->project_id = $inputs['project_id'];
    	$log->user_id = auth()->user()->id;
    	$log->pricing_manner = $inputs['pricing_manner'];
    	$log->date = date('Y-m-d');
    	$log->amount =$inputs['amount'];
    	$log->e_amount =$inputs['e_amount'];
    	return $log->save();
    }
}

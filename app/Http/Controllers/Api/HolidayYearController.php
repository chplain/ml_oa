<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HolidayYearController extends Controller
{
    //年假配置
    public function store(){
    	$inputs = request()->all();
    	$year_set = new \App\Models\HolidayYear;
    	$set_info = $year_set->first();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		if(!empty($set_info['days_setting'])){
    			$set_info['days_setting'] = unserialize($set_info['days_setting']);
    		}
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $set_info]);
    	}
    	$rules = [
            'if_use' => 'required|integer',
            'redate' => 'required',
            'days' => 'required|integer',
            'days_setting' => 'required|array'
    	];
    	$attributes = [
            'if_use' => 'if_use 是否启用',
            'redate' => 'redate 重新计算日期',
            'days' => 'days 年假数',
            'days_setting' => 'days_setting 司龄'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        if(!empty($set_info) && !isset($inputs['id'])){
        	return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        foreach ($inputs['days_setting'] as $key => $value) {
        	if(!is_numeric($value['min'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数min']);
        	}
        	if(!is_numeric($value['max'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数days']);
        	}
        	if(!is_numeric($value['days'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数days']);
        	}
        }
        // dd($inputs);
        $result = $year_set->storeData($inputs);
        if($result){
            systemLog('考勤管理', '编辑了年假规则');
        	return response()->json(['code' => 1, 'message' => '保存成功']);
        }
        return response()->json(['code' => 0, 'message' => '保存失败']);
    }

    /**
     * 奖励列表
     * @Author: molin
     * @Date:   2019-03-08
     */
    public function reward(){
        $inputs = request()->all();
        $reward = new \App\Models\Reward;
        $inputs['type'] = 1;//奖励
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'store'){
            //保存
            $rules = [
                'user_id' => 'required|integer',
                'year' => 'required|integer',
                'days' => 'required|numeric',
                'remarks' => 'required|max:100'
            ];
            $attributes = [
                'user_id' => '用户id',
                'year' => '年份',
                'days' => '奖励天数',
                'remarks' => '备注'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if($inputs['days'] < 0.5 || fmod($inputs['days'], 0.5) > 0){
                return response()->json(['code' => 0, 'message' => '奖励天数必须为0.5的倍数']);
            }
            $user = new \App\Models\User;
            $user_info = $user->where('id', $inputs['user_id'])->select(['id','dept_id','realname'])->first();
            $user_data = $user->getIdToData();
            $inputs['dept_id'] = $user_info->dept_id;
            $inputs['realname'] = $user_info->realname;
            $inputs['dept_name'] = $user_data['id_dept'][$inputs['user_id']];
            $result = $reward->storeData($inputs);
            if($result){
                $reward_list = $reward->getRewardList($inputs);
                systemLog('年假奖励', '新增了对'.$user_data['id_realname'][$inputs['user_id']].'的年假奖励:'.$inputs['year'].'年份'.$inputs['days'].'天');
                return response()->json(['code' => 1, 'message' => '操作成功', 'data' => ['reward_list'=>$reward_list]]);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit'){
            //保存
            $rules = [
                'id' => 'required|integer',
                'user_id' => 'required|integer',
                'year' => 'required|integer',
                'days' => 'required|numeric',
                'remarks' => 'required|max:100'
            ];
            $attributes = [
                'id' => 'id',
                'user_id' => '用户id',
                'year' => '年份',
                'days' => '奖励天数',
                'remarks' => '备注'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if($inputs['days'] < 0.5 || fmod($inputs['days'], 0.5) > 0){
                return response()->json(['code' => 0, 'message' => '奖励天数必须为0.5的倍数']);
            }
            $user = new \App\Models\User;
            $user_info = $user->where('id', $inputs['user_id'])->select(['id','dept_id','realname'])->first();
            $user_data = $user->getIdToData();
            $inputs['dept_id'] = $user_info->dept_id;
            $inputs['realname'] = $user_info->realname;
            $inputs['dept_name'] = $user_data['id_dept'][$inputs['user_id']];
            $result = $reward->storeData($inputs);
            if($result){
                $reward_list = $reward->getRewardList($inputs);
                systemLog('年假奖励', '编辑了对'.$user_data['id_realname'][$inputs['user_id']].'的年假奖励:'.$inputs['year'].'年份'.$inputs['days'].'天');
                return response()->json(['code' => 1, 'message' => '操作成功', 'data' => ['reward_list'=>$reward_list]]);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'delete'){
            //删除
            $rules = [
                'id' => 'required|integer',
            ];
            $attributes = [
                'id' => 'id',
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }

            $reward_info = $reward->where('id', $inputs['id'])->first();
            $result = $reward->where('id', $inputs['id'])->delete();
            if($result){
                $reward_list = $reward->getRewardList($inputs);
                $user = new \App\Models\User;
                $user_data = $user->getIdToData();
                systemLog('年假奖励', '删除了对'.$user_data['id_realname'][$reward_info->user_id].'的年假奖励:'.$reward_info->year.'年份'.$reward_info->days.'天');
                return response()->json(['code' => 1, 'message' => '删除成功', 'data' => ['reward_list'=>$reward_list]]);
            }
            return response()->json(['code' => 0, 'message' => '删除失败']);
        }
        
        $reward_list = $reward->getRewardList($inputs);
        $data = array();
        $data['reward_list'] = $reward_list;
        $user = new \App\Models\User;
        $user_list = $user->select(['id','realname','dept_id'])->get();
        $data['user_list'] = $user_list;
        $dept = new \App\Models\Dept;
        $dept_list = $dept->select(['id','name'])->get();
        $data['dept_list'] = $dept_list;
        $year_list = array();
        $year = date('Y');
        for($i = 0; $i < 3; $i++ ){
            $y = date('Y', strtotime("$year +$i year"));
            $year_list[] = ['id' => $y, 'name' => $y]; 
        }
        $data['year_list'] = $year_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }
    /**
     * 年假扣减
     * @Author: molin
     * @Date:   2019-05-29
     */
    public function deduct(){
        $inputs = request()->all();
        $reward = new \App\Models\Reward;
        $inputs['type'] = 2;//扣减
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'store'){
            //保存
            $rules = [
                'user_id' => 'required|integer',
                'year' => 'required|integer',
                'days' => 'required|numeric',
                'remarks' => 'required|max:100'
            ];
            $attributes = [
                'user_id' => '用户id',
                'year' => '年份',
                'days' => '扣减天数',
                'remarks' => '备注'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if($inputs['days'] < 0.5 || fmod($inputs['days'], 0.5) > 0){
                return response()->json(['code' => 0, 'message' => '扣减天数必须为0.5的倍数']);
            }
            $has_days = (new \App\Models\HolidayYear)->getYearHoliday($inputs['user_id'], $inputs['year'].'-01-02');
            if(($inputs['days'] - $has_days) > 0){
                return response()->json(['code' => 0, 'message' => '扣减天数大于剩余年假天数，请修改后保存']);
            }
            $user = new \App\Models\User;
            $user_info = $user->where('id', $inputs['user_id'])->select(['id','dept_id','realname'])->first();
            $user_data = $user->getIdToData();
            $inputs['dept_id'] = $user_info->dept_id;
            $inputs['realname'] = $user_info->realname;
            $inputs['dept_name'] = $user_data['id_dept'][$inputs['user_id']];
            $result = $reward->storeData($inputs);
            if($result){
                $reward_list = $reward->getRewardList($inputs);
                systemLog('年假扣减', '新增了对'.$user_data['id_realname'][$inputs['user_id']].'的年假扣减:'.$inputs['year'].'年份'.$inputs['days'].'天');
                return response()->json(['code' => 1, 'message' => '操作成功', 'data' => ['reward_list'=>$reward_list]]);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'edit'){
            //保存
            $rules = [
                'id' => 'required|integer',
                'user_id' => 'required|integer',
                'year' => 'required|integer',
                'days' => 'required|numeric',
                'remarks' => 'required|max:100'
            ];
            $attributes = [
                'id' => 'id',
                'user_id' => '用户id',
                'year' => '年份',
                'days' => '扣减天数',
                'remarks' => '备注'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            if($inputs['days'] < 0.5 || fmod($inputs['days'], 0.5) > 0){
                return response()->json(['code' => 0, 'message' => '扣减天数必须为0.5的倍数']);
            }
            $has_days = (new \App\Models\HolidayYear)->getYearHoliday($inputs['user_id'], $inputs['year'].'-01-01', $inputs['year'].'-01-02',$inputs['id']);
            if(($has_days - $inputs['days']) < 0){
                return response()->json(['code' => 0, 'message' => '扣减天数大于剩余年假天数，请修改后保存']);
            }
            $user = new \App\Models\User;
            $user_info = $user->where('id', $inputs['user_id'])->select(['id','dept_id','realname'])->first();
            $user_data = $user->getIdToData();
            $inputs['dept_id'] = $user_info->dept_id;
            $inputs['realname'] = $user_info->realname;
            $inputs['dept_name'] = $user_data['id_dept'][$inputs['user_id']];
            $result = $reward->storeData($inputs);
            if($result){
                $reward_list = $reward->getRewardList($inputs);
                systemLog('年假扣减', '编辑了对'.$user_data['id_realname'][$inputs['user_id']].'的年假扣减:'.$inputs['year'].'年份'.$inputs['days'].'天');
                return response()->json(['code' => 1, 'message' => '操作成功', 'data' => ['reward_list'=>$reward_list]]);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'delete'){
            //删除
            $rules = [
                'id' => 'required|integer',
            ];
            $attributes = [
                'id' => 'id',
            ];
            
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }

            $reward_info = $reward->where('id', $inputs['id'])->first();
            $result = $reward->where('id', $inputs['id'])->delete();
            if($result){
                $reward_list = $reward->getRewardList($inputs);
                $user = new \App\Models\User;
                $user_data = $user->getIdToData();
                systemLog('年假扣减', '删除了对'.$user_data['id_realname'][$reward_info->user_id].'的年假扣减:'.$reward_info->year.'年份'.$reward_info->days.'天');
                return response()->json(['code' => 1, 'message' => '删除成功', 'data' => ['reward_list'=>$reward_list]]);
            }
            return response()->json(['code' => 0, 'message' => '删除失败']);
        }
        $reward_list = $reward->getRewardList($inputs);
        $data = array();
        $data['reward_list'] = $reward_list;
        $user = new \App\Models\User;
        $user_list = $user->select(['id','realname','dept_id'])->get();
        $data['user_list'] = $user_list;
        $dept = new \App\Models\Dept;
        $dept_list = $dept->select(['id','name'])->get();
        $data['dept_list'] = $dept_list;
        $year_list = array();
        $year = date('Y');
        for($i = 0; $i < 3; $i++ ){
            $y = date('Y', strtotime("$year +$i year"));
            $year_list[] = ['id' => $y, 'name' => $y]; 
        }
        $data['year_list'] = $year_list;
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }
}

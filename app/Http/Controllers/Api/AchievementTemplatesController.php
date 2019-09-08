<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AchievementTemplatesController extends Controller
{
    //绩效模板
     /*
    * 创建绩效模板
    * @author molin
    * @date 2018-11-29
    */
     public function store(){
     	$inputs = request()->all();
     	if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
     		$user = new \App\Models\User;
     		$user_list = $user->where('status', 1)->select(['id','realname'])->get();
            $data['user_list'] = $user_list;
     		$data['type_list'] = [['id'=>1,'name'=>'部门负责人'],['id'=>2,'name'=>'被考核人'],['id'=>3,'name'=>'指定人员']];
     		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
     	}
     	$rules = [
            'name' => 'required|max:50|unique:achievement_templates,name',
            'user_ids' => 'required|array',
            'th' => 'required|array',
            'tbody' => 'required|array',
            'score_user_ids' => 'required|array',
            'verify_user_ids' => 'required|array'
        ];
        $attributes = [
            'name' => '考核方案名称',
            'user_ids' => '适用对象',
            'th' => '表格头部',
            'tbody' => '表格内容',
            'score_user_ids' => '评分人信息',
            'verify_user_ids' => '审核人信息'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        //type  1部门负责人  2被考核人 3指定人
        foreach($inputs['score_user_ids'] as $key => $val){
        	if(!isset($val['type']) || !is_numeric($val['type'])){
        		return response()->json(['code' => -1, 'message' => '评分人信息缺少参数type']);
        	}
        	if(!isset($val['percent']) || !is_numeric($val['percent'])){
        		return response()->json(['code' => -1, 'message' => '评分人信息缺少参数percent']);
        	}
        	if(!isset($val['if_view']) || !is_numeric($val['if_view'])){
        		return response()->json(['code' => -1, 'message' => '评分人信息缺少参数if_view']);
        	}
        	if(isset($val['type']) && $val['type'] == 3 && !is_numeric($val['user_id']) && $val['user_id'] > 0){
        		return response()->json(['code' => -1, 'message' => '请选择评分人']);
        	}
        }
        foreach($inputs['verify_user_ids'] as $key => $val){
        	if(!isset($val['type']) || !is_numeric($val['type'])){
        		return response()->json(['code' => -1, 'message' => '审核人信息缺少参数type']);
        	}
        	if(isset($val['type']) && $val['type'] == 3 && !is_numeric($val['user_id'])){
        		return response()->json(['code' => -1, 'message' => '审核人信息缺少参数user_id']);
        	}
        }
        $achie = new \App\Models\AchievementTemplate;
        $result = $achie->storeData($inputs);
        if($result){
            systemLog('绩效', '添加了绩效模板['.$inputs['name'].']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
     }


     /*
    * 删除
    * @author molin
    * @date 2018-12-03
    */
     public function delete(){
     	$inputs = request()->all();
     	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
     		return response()->json(['code' => -1, 'message' => '缺少参数id']);
     	}
     	$achie = new \App\Models\AchievementTemplate;
        $info = $achie->where('id', $inputs['id'])->select(['name'])->first();
        if(empty($info)){
            return response()->json(['code' => 0, 'message' => '数据不存在']);
        }
     	$res = $achie->destroyTpl($inputs['id']);
     	if($res){
            systemLog('绩效', '删除了绩效模板['.$inputs['id'].'-'.$info->name.']');
     		return response()->json(['code' => 1, 'message'=>'删除成功']);
     	}
     	return response()->json(['code' => 0, 'message' => '删除失败']);
     }

     /*
    * 分派
    * @author molin
    * @date 2018-11-29
    */
    public function assign(){
     	$inputs = request()->all();
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
     	$achie = new \App\Models\AchievementTemplate;
     	if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
     		$info = $achie->where('id', $inputs['id'])->first();
            $user_list = $user->where('status', 1)->select(['id','realname'])->get();
            $user_ids = explode(',', $info->user_ids);
            $user_names = array();
            foreach ($user_ids as $uid) {
                $user_names[] = $user_data['id_realname'][$uid];
            }
            $user_names = implode('、', $user_names);
            $data = array();
            $data['id'] = $inputs['id'];
            $data['name'] = $info->name;
            $data['user_names'] = $user_names;
            $data['user_ids'] = $user_ids;
            $data['user_list'] = $user_list;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
     	}
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'assign'){
            //保存数据  分派多条模板
            $rules = [
                'ids' => 'required|array',
                'type' => 'required',
                'month' => 'required|date_format:Y-m'
            ];
            $attributes = [
                'ids' => '模板id',
                'type' => 'type',
                'month' => '考核月份'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $achie_list = $achie->whereIn('id', $inputs['ids'])->select(['id','user_ids','name'])->get()->toArray();
            $all_uids = array();
            foreach ($achie_list as $key => $value) {
                $uids = explode(',', $value['user_ids']);
                foreach ($uids as $u) {
                    if(in_array($u, $all_uids)){
                        return response()->json(['code' => 0, 'message' => '同一个人不能分派多个绩效模板,请检查模板考核对象']);
                    }
                    $all_uids[] = $u;
                }
            }

            $inputs['year'] = date('Y', strtotime($inputs['month']));//年份
            $inputs['month'] = date('m', strtotime($inputs['month']));;//月份
            $assign_user = new \App\Models\AchievementAssignUser;
            $info = $assign_user->select(['id','user_id'])
                              ->whereIn('user_id', $all_uids)
                              ->where('year_month', $inputs['year'].'-'.$inputs['month'])
                              ->where('status', '<>', 4)
                              ->get()->toArray();
            if(!empty($info)){
                $realnames = array();
                $ids = array();
                foreach ($info as $key => $value) {
                    $realnames[] = $user_data['id_realname'][$value['user_id']];
                    $ids[] = $value['id'];
                }
                $realnames = implode(',', $realnames);
            }
            if(!isset($inputs['type'])){
                return response()->json(['code' => -1, 'message' => '请传入type']);
            }
            if(!in_array($inputs['type'], ['check','cover'])){
                return response()->json(['code' => -1, 'message' => '请传入type']);
            }
            if($inputs['type'] == 'check' && !empty($info)){
                //检查是否已分派过
                return response()->json(['code' => 1, 'message' => $realnames.'该月份已经分派过了', 'bounce' => 1]);
            }
            if($inputs['type'] == 'cover' && !empty($info)){
                //将已分派绩效覆盖(将已有的改成撤销状态) status = 4 撤销
                $re = $assign_user->whereIn('id', $ids)->update(['status'=>4, 'total_score'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
                if(!$re){
                    return response()->json(['code' => 0, 'message' => '分派失败']);
                }
            }
            $assign = new \App\Models\AchievementAssign;
            foreach ($achie_list as $key => $value) {
                $assign_inputs = array();
                $assign_inputs['id'] = $value['id'];
                $assign_inputs['user_ids'] = explode(',', $value['user_ids']);
                $assign_inputs['year'] = $inputs['year'];
                $assign_inputs['month'] = $inputs['month'];
                $result = $assign->storeData($assign_inputs);        
            }
            if($result){
                $tpl_names = $achie->whereIn('id', $inputs['ids'])->pluck('name')->toArray();
                $user_names = $user->whereIn('id', $all_uids)->pluck('realname')->toArray();
                systemLog('绩效', '分派了'.$inputs['year'].'-'.$inputs['month'].'月份绩效模板['.implode('|', $inputs['ids']).'-'.implode('|', $tpl_names).']['.implode(',', $user_names).']');
                addNotice($all_uids, '绩效', '您有一条绩效内容待确认', '', 0, 'achievement-list-index','achievement_user/index');//提醒被考核人
                return response()->json(['code' => 1, 'message' => '操作成功', 'bounce' => 0]);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);

        }
        //保存数据
        $rules = [
            'id' => 'required',
            'user_ids' => 'required|array',
            'type' => 'required',
            'month' => 'required|date_format:Y-m'
        ];
        $attributes = [
            'id' => 'id',
            'user_ids' => '考核对象',
            'type' => 'type',
            'month' => '考核月份'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $inputs['year'] = date('Y', strtotime($inputs['month']));//年份
        $inputs['month'] = date('m', strtotime($inputs['month']));;//月份
     	$assign_user = new \App\Models\AchievementAssignUser;
        $info = $assign_user->select(['id','user_id'])
                          ->whereIn('user_id', $inputs['user_ids'])
                          ->where('year_month', $inputs['year'].'-'.$inputs['month'])
                          ->where('status', '<>', 4)
                          ->get()->toArray();
        if(!empty($info)){
            $realnames = array();
            $ids = array();
            foreach ($info as $key => $value) {
                $realnames[] = $user_data['id_realname'][$value['user_id']];
                $ids[] = $value['id'];
            }
            $realnames = implode(',', $realnames);
        }
        if(isset($inputs['type']) && $inputs['type'] == 'check' && !empty($info)){
            //检查是否已分派过
            return response()->json(['code' => 1, 'message' => $realnames.'该月份已经分派过了', 'bounce' => 1]);
        }
        if(isset($inputs['type']) && $inputs['type'] == 'cover' && !empty($info)){
            //将已分派绩效覆盖(将已有的改成撤销状态) status = 4 撤销
            $re = $assign_user->whereIn('id', $ids)->update(['status'=>4, 'total_score'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
            if(!$re){
                return response()->json(['code' => 0, 'message' => '分派失败']);
            }
        }
        $assign = new \App\Models\AchievementAssign;
        $result = $assign->storeData($inputs);        
        if($result){
            $tpl_info = $achie->where('id', $inputs['id'])->select(['name'])->first();
            $user_names = $user->whereIn('id', $inputs['user_ids'])->pluck('realname')->toArray();
            systemLog('绩效', '分派了绩效模板['.$inputs['id'].'-'.$tpl_info->name.']['.implode(',', $user_names).']');
            addNotice($inputs['user_ids'], '绩效', '您有一条绩效内容待确认', '', 0, 'achievement-list-index','achievement_user/index');//提醒被考核人
            return response()->json(['code' => 1, 'message' => '操作成功', 'bounce' => 0]);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /*
    * 列表
    * @author molin
    * @date 2018-12-10
    */
    public function index(){
        $inputs = request()->all();
        $tpl = new \App\Models\AchievementTemplate;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //查看详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $tpl_info = $tpl->where('id', $inputs['id'])->first();
            $tpl_info->th = explode(',', $tpl_info->th);
            $tpl_info->tbody = unserialize($tpl_info->tbody);
            $tpl_info->score_user_ids = unserialize($tpl_info->score_user_ids);
            $tpl_info->verify_user_ids = unserialize($tpl_info->verify_user_ids);
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();
            $user_names = array();
            $tpl_info->user_ids = explode(',', $tpl_info->user_ids);
            foreach ($tpl_info->user_ids as $key => $value) {
                $user_names[] = $user_data['id_realname'][$value];
            }
            $tpl_info->user_names = implode(',', $user_names);
            $user = new \App\Models\User;
            $user_list = $user->select(['id', 'realname'])->get();
            $data['tpl_info'] = $tpl_info;
            $data['user_list'] = $user_list;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        $data = $tpl->getDataList($inputs);
        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            $items['name'] = $value->name;
            $items['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $data['datalist'][$key] = $items;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  分派撤销
    *  @author molin
    *   @date 2018-12-13
    */
    public function revoke(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $assign_users = new \App\Models\AchievementAssignUser;
        $user = new \App\Models\User;
        $user_data = $user->getIdToData();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //查看详情
            
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $info = $assign_users->getDataInfo($inputs);
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $tmp = array();
            $tmp['id'] = $info->id;
            $tmp['name'] = $info->name;
            $tmp['realname'] = $user_data['id_realname'][$info->user_id];
            $tmp['year_month'] = $info->year_month;
            $th = explode(',', $info->th);
            $tbody = unserialize($info->tbody);
            $th_end = array($th[count($th) - 1]);
            unset($th[count($th) - 1]);
            $other_th_score = array();
            $user_score_total = array(); 
            foreach ($info->hasScore as $key => $value) {
                if(!empty($value->score)){
                    $other_th_score[] = $user_data['id_realname'][$value->score_user_id].'的评分';
                    $score_tmp = explode(',', $value->score);
                    foreach ($tbody as $k => $v) {
                        $user_score_total[$k] = $user_score_total[$k] ?? 0;
                        $user_score_total[$k] += $score_tmp[$k] * ($value->percent / 100);//得分
                        array_splice($tbody[$k], -1, 1, $score_tmp[$k]);//替换最后一个元素
                        array_push($tbody[$k], $user_score_total[$k]);//追加最后一个元素
                    }
                }
            }
            $th = array_merge($th, $other_th_score,$th_end);
            $tmp['th'] = $th;
            $tmp['tbody'] = $tbody;
            
            $score_users = array();
            foreach ($info->hasScore as $key => $value) {
                $score_users[$key]['realname'] = $user_data['id_realname'][$value->score_user_id];
                $score_users[$key]['percent'] = $value->percent.'%';
            }
            $tmp['score_users'] = $score_users;
            $verify_users = explode(',', $info->score_user_ids);
            $tmp_verify_users = array();
            foreach ($verify_users as $key => $value) {
                $tmp_verify_users[] = $user_data['id_realname'][$value];
            }
            $tmp['verfiy_users'] = $tmp_verify_users;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $tmp]);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'submit'){
            //撤销操作
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $inputs['assign_user_id'] = auth()->user()->id;
            $info = $assign_users->getDataInfo($inputs);
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '没有符合条件的数据']);
            }
            $info->status = 4;//撤销
            $result = $info->save();
            if($result){
                systemLog('绩效', '撤销了绩效['.$inputs['id'].'-'.$info->name.']['.$user_data['id_realname'][$info->user_id].']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);
        }
        $inputs['tpl_id'] = $inputs['id'];
        $inputs['assign_user_id'] = auth()->user()->id;
        $inputs['in_status'] = array(0,1,2);//未确认，已评分的可以撤销；已审核的不能撤销
        if(isset($inputs['keywords']) && !empty($inputs['keywords'])){
            $user_ids = $user->where('realname', 'like', '%'.$inputs['keywords'].'%')->orWhere('username', 'like', '%'.$inputs['keywords'].'%')->pluck('id')->toArray();
            $inputs['user_ids'] = $user_ids;
        }
        $data = $assign_users->getDataList($inputs);
        $tmp = array();
        foreach ($data['datalist'] as $key => $value) {
            $tmp['id'] = $value->id;
            $tmp['realname'] = $user_data['id_realname'][$value->user_id];
            $tmp['year_month'] = $value->year_month;
            $tmp['name'] = $value->name;
            $tmp['created_at'] = $value->created_at->format('Y-m-d H:i:s');
            $data['datalist'][$key] = $tmp;
        }
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /** 
    *  模板编辑
    *  @author molin
    *   @date 2019-03-06
    */
    public function update(){
        $inputs = request()->all();
        if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
            return response()->json(['code' => -1, 'message' => '缺少参数id']);
        }
        $achie = new \App\Models\AchievementTemplate;
        $info = $achie->where('id', $inputs['id'])->first();
        if(empty($info)){
            return response()->json(['code' => 0, 'message' => '数据不存在']);
        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            $data = array();
            $info->user_ids = explode(',', $info->user_ids);
            $info->th = explode(',', $info->th);
            $info->tbody = unserialize($info->tbody);
            $info->score_user_ids = unserialize($info->score_user_ids);
            $info->verify_user_ids = unserialize($info->verify_user_ids);
            $data['info'] = $info;
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select(['id','realname'])->get();
            $data['user_list'] = $user_list;
            $data['type_list'] = [['id'=>1,'name'=>'部门负责人'],['id'=>2,'name'=>'被考核人'],['id'=>3,'name'=>'指定人员']];
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        $rules = [
            'id' => 'required|integer',
            'name' => 'required|max:50|unique:achievement_templates,name,'.$inputs['id'],
            'user_ids' => 'required|array',
            'th' => 'required|array',
            'tbody' => 'required|array',
            'score_user_ids' => 'required|array',
            'verify_user_ids' => 'required|array'
        ];
        $attributes = [
            'id' => 'id',
            'name' => '考核方案名称',
            'user_ids' => '适用对象',
            'th' => '表格头部',
            'tbody' => '表格内容',
            'score_user_ids' => '评分人信息',
            'verify_user_ids' => '审核人信息'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        //type  1部门负责人  2被考核人 3指定人
        foreach($inputs['score_user_ids'] as $key => $val){
            if(!isset($val['type']) || !is_numeric($val['type'])){
                return response()->json(['code' => -1, 'message' => '评分人信息缺少参数type']);
            }
            if(!isset($val['percent']) || !is_numeric($val['percent'])){
                return response()->json(['code' => -1, 'message' => '评分人信息缺少参数percent']);
            }
            if(!isset($val['if_view']) || !is_numeric($val['if_view'])){
                return response()->json(['code' => -1, 'message' => '评分人信息缺少参数if_view']);
            }
            if(isset($val['type']) && $val['type'] == 3 && !is_numeric($val['user_id']) && $val['user_id'] > 0){
                return response()->json(['code' => -1, 'message' => '请选择评分人']);
            }
        }
        foreach($inputs['verify_user_ids'] as $key => $val){
            if(!isset($val['type']) || !is_numeric($val['type'])){
                return response()->json(['code' => -1, 'message' => '审核人信息缺少参数type']);
            }
            if(isset($val['type']) && $val['type'] == 3 && !is_numeric($val['user_id'])){
                return response()->json(['code' => -1, 'message' => '审核人信息缺少参数user_id']);
            }
        }
        $result = $achie->storeData($inputs);
        if($result){
            systemLog('绩效', '编辑了绩效模板['.$info->name.']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /** 
    *  模板复制
    *  @author molin
    *   @date 2019-03-06
    */
    public function copy(){
        $inputs = request()->all();
        $achie = new \App\Models\AchievementTemplate;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'load'){
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $info = $achie->where('id', $inputs['id'])->first();
            if(empty($info)){
                return response()->json(['code' => 0, 'message' => '数据不存在']);
            }
            $data = array();
            $info->user_ids = explode(',', $info->user_ids);
            $info->th = explode(',', $info->th);
            $info->tbody = unserialize($info->tbody);
            $info->score_user_ids = unserialize($info->score_user_ids);
            $info->verify_user_ids = unserialize($info->verify_user_ids);
            $data['info'] = $info;
            $user = new \App\Models\User;
            $user_list = $user->where('status', 1)->select(['id','realname'])->get();
            $data['user_list'] = $user_list;
            $data['type_list'] = [['id'=>1,'name'=>'部门负责人'],['id'=>2,'name'=>'被考核人'],['id'=>3,'name'=>'指定人员']];
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
        }
        $rules = [
            'name' => 'required|max:50|unique:achievement_templates,name',
            'user_ids' => 'required|array',
            'th' => 'required|array',
            'tbody' => 'required|array',
            'score_user_ids' => 'required|array',
            'verify_user_ids' => 'required|array'
        ];
        $attributes = [
            'name' => '考核方案名称',
            'user_ids' => '适用对象',
            'th' => '表格头部',
            'tbody' => '表格内容',
            'score_user_ids' => '评分人信息',
            'verify_user_ids' => '审核人信息'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        //type  1部门负责人  2被考核人 3指定人
        foreach($inputs['score_user_ids'] as $key => $val){
            if(!isset($val['type']) || !is_numeric($val['type'])){
                return response()->json(['code' => -1, 'message' => '评分人信息缺少参数type']);
            }
            if(!isset($val['percent']) || !is_numeric($val['percent'])){
                return response()->json(['code' => -1, 'message' => '评分人信息缺少参数percent']);
            }
            if(!isset($val['if_view']) || !is_numeric($val['if_view'])){
                return response()->json(['code' => -1, 'message' => '评分人信息缺少参数if_view']);
            }
            if(isset($val['type']) && $val['type'] == 3 && !is_numeric($val['user_id']) && $val['user_id'] > 0){
                return response()->json(['code' => -1, 'message' => '请选择评分人']);
            }
        }
        foreach($inputs['verify_user_ids'] as $key => $val){
            if(!isset($val['type']) || !is_numeric($val['type'])){
                return response()->json(['code' => -1, 'message' => '审核人信息缺少参数type']);
            }
            if(isset($val['type']) && $val['type'] == 3 && !is_numeric($val['user_id'])){
                return response()->json(['code' => -1, 'message' => '审核人信息缺少参数user_id']);
            }
        }
        $result = $achie->storeData($inputs);
        if($result){
            systemLog('绩效', '复制了绩效模板');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败']);
    }

}

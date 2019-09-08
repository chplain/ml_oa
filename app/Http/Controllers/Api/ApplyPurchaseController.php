<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class ApplyPurchaseController extends Controller
{
    /*
    * 采购申请
    * @author molin
    * @date 2018-10-19
    */
    public function store(){
    	$inputs = request()->all();
        $applyPurchase = new \App\Models\ApplyPurchase;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'apply_purchase'){
    		$data = $tmp = array();
    		$tmp['realname'] = auth()->user()->realname;
    		$data['user_info'] = $tmp;
    		$dept = new \App\Models\Dept;
    		$dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
    		$data['dept_list'] = $dept_list;
    		$data['type_list'] = [['type_id' => 1, 'name' => '固定资产'], ['type_id' => 2, 'name' => '消耗品']];
    		$where = array();
    		$where['status'] = 1;//获取正常大类
    		$where['type'] = 1;//默认获取固定资产
    		$cate = new \App\Models\GoodsCategory;
    		$cate_list = $cate->getCateList($where);
    		$data['cate_list'] = $cate_list;
    		$data['degree'] = [['degree_id' => 1, 'name' => '紧急'], ['degree_id' => 2, 'name' => '一般'], ['degree_id' => 3, 'name' => '不紧急']];

    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'storage'){
            //补库存加载
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $data = $tmp = array();
            $data['id'] = $inputs['id'];
            $user = new \App\Models\User;
            $inputs['user_id'] = auth()->user()->id;
            $user_info = $user->queryUserInfo($inputs);
            $tmp['realname'] = $user_info->realname;
            $tmp['dept'] = $user_info->dept->name;
            $data['user_info'] = $tmp;
            $goods = new \App\Models\Goods;
            $goods_info = $goods->where('id', $inputs['id'])->first();
            if($goods_info['type'] == 1){
                $data['type'] = '固定资产';
            }else if($goods_info['type'] == 2){
                $data['type'] = '消耗品';
            }
            $cate = new \App\Models\GoodsCategory;
            $cate_data = $cate->getIdToData();
            $data['cate'] = $cate_data[$goods_info['cate_id']];
            $data['name'] = $goods_info['name'];
            $data['unit'] = $goods_info['unit'];
            $data['degree'] = [['degree_id' => 1, 'name' => '紧急'], ['degree_id' => 2, 'name' => '一般'], ['degree_id' => 3, 'name' => '不紧急']];
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'get_cate'){
    		//根据资产类别 获取大类
    		if(!isset($inputs['type_id']) || !is_numeric($inputs['type_id'])){
    			return response()->json(['code' => -1, 'message' => '缺少资产类别type_id']);
    		}
    		$inputs['status'] = 1;//获取正常大类
    		$inputs['type'] = $inputs['type_id'];
    		$cate = new \App\Models\GoodsCategory;
    		$cate_list = $cate->getCateList($inputs);
    		$data = array();
    		$data['cate_list'] = $cate_list;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'get_goods'){
    		//根据资产类别 获取大类
    		if(!isset($inputs['cate_id']) || !is_numeric($inputs['cate_id'])){
    			return response()->json(['code' => -1, 'message' => '缺少资产类别cate_id']);
    		}
    		$goods = new \App\Models\Goods;
    		$goods_list = $goods->where('status', 1)->where('cate_id', $inputs['cate_id'])->select(['id', 'name'])->get();
    		$data = array();
    		$data['goods_list'] = $goods_list;
    		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'upload'){
    		//上传图片
    		$upload_file = \request()->file('file');
	        if(!empty($upload_file)){
	            $ext = $upload_file->getClientOriginalExtension();//扩展名
	            $type_arr = ['jpg','png','gif','jpeg'];
	            if(!in_array($ext, $type_arr)){
	                return response()->json(['code' => -1, 'message' => '只能上传jpg/png/gif图片！！', 'data' => ['type' => $ext]]);
	            }
	            $size = $upload_file->getSize();//文件大小  限制大小2M
	            if($size > 2097152){
	                return response()->json(['code' => -1, 'message' => '只能上传2M以内的图片！！', 'data' => null]);
	            }
	            //重命名
	            $fileName = date('YmdHis').uniqid().'.'.$ext;
	            $upload_file->move(storage_path('app/public/uploads/purchase/'), $fileName);
	            $data = array();
	            $data['file'] = '/storage/uploads/purchase/'.$fileName;
	            $data['file_path'] = asset('/storage/uploads/purchase/'.$fileName);
	            return response()->json(['code' => 1, 'message' => '上传成功', 'data' => $data]);
	        }else{
	            return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
	        }

    	}
        //表单是否启用
        $apply_type = new \App\Models\ApplyType;
        $type_info = $apply_type->where('id', $applyPurchase::type)->where('if_use', 1)->first();
        if(empty($type_info)){
            return response()->json(['code' => 0, 'message' => '提交失败，该表单尚未启用']);
        }
        if(isset($inputs['id']) && is_numeric($inputs['id'])){
            //补库存提交
            $user = new \App\Models\User;
            $inputs['user_id'] = auth()->user()->id;
            $inputs['dept_id'] = auth()->user()->dept_id;
            $goods = new \App\Models\Goods;
            $goods_info = $goods->where('id', $inputs['id'])->first();
            $inputs['type_id'] = $goods_info['type'];
            $inputs['cate_id'] = $goods_info['cate_id'];
            $inputs['goods_id'] = $goods_info['id'];
            $inputs['goods_name'] = $goods_info['name'];
        }
		//保存数据
    	$rules = [
            'dept_id' => 'required|integer',
            'type_id' => 'required|integer',
            'cate_id' => 'required|integer',
            'goods_id' => 'required|integer',
            'goods_name' => 'required|max:50',
            'num' => 'required|integer',
            'spec' => 'max:50',
            'uses' => 'required',
            'degree_id' => 'required|integer',
            'rdate' => 'required|date',
        ];
        $attributes = [
            'dept_id' => '领用部门,dept_id',
            'type_id' => '资产类别,type_id',
            'cate_id' => '大类,cate_id',
            'goods_id' => '小类,goods_id',
            'goods_name' => '物品名称,goods_name',
            'num' => '数量,num',
            'spec' => '规格,spec',
            'uses' => '用途,uses',
            'degree_id' => '紧急度,degree_id',
            'rdate' => '最后期限,rdate',
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }

        //取出流程审核配置
        $setting = new \App\Models\ApplyProcessSetting;
    	$setting_info = $setting->where('type_id', $applyPurchase::type)->orderBy('id', 'desc')->first();//获取最新的配置
    	if(empty($setting_info)){
    		return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
    	}
        $steps = new \App\Models\AuditProcessStep;
        $step1 = $steps->where('setting_id', $setting_info->id)->where('step', 'step1')->first();
        if(empty($step1)){
            return response()->json(['code' => 0, 'message' => '请先配置好表单审核人员']);
        }
    	$setting_info['setting_content'] = unserialize($setting_info['setting_content']);
        $inputs['user_id'] = auth()->user()->id;
        $inputs['dept_id'] = $inputs['dept_id'];//领用部门
        
        $result = $applyPurchase->storeData($inputs, $setting_info);
        if ($result) {
            systemLog('采购申请', '提交了采购申请['.$inputs['goods_name'].']');
        	return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请联系管理员']);

    }

    /*
    * 采购申请-详情
    * @author molin
    * @date 2018-10-19
    */
    public function show(){
    	$inputs = request()->all();
    	if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数id']);
    	}
    	$inputs['apply_id'] = $inputs['id'];
    	unset($inputs['id']);
    	$audit_proces =new \App\Models\AuditProces;
    	$proces_info = $audit_proces->getPurchaseInfo($inputs);
    	if(empty($proces_info)){
    		return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
    	}
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();

    	$cate = new \App\Models\GoodsCategory;
    	$cate_data = $cate->getIdToData();

    	$goods = new \App\Models\Goods;
    	$goods_data = $goods->getIdToData();

    	//拼接数据
		$data = $user_info = $goods_info = array();
		//领用部门、申请人信息
    	$user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
		$user_info['dept'] = $proces_info->hasDept->name;
        $user_info['created_at'] = $proces_info->applyPurchase->created_at->format('Y-m-d H:i:s');
        $user_info['status_txt'] = $proces_info->applyPurchase->status_txt;
		$data['user_info'] = $user_info;
		//物品信息
		if($proces_info->applyPurchase->type_id == 1){
			$goods_info['type'] = '固定资产';
		}else if($proces_info->applyPurchase->type_id == 2){
			$goods_info['type'] = '消费品资产';
		}
		$goods_info['cate'] = $cate_data[$proces_info->applyPurchase->cate_id];
		$goods_info['goods'] = $goods_data['id_name'][$proces_info->applyPurchase->goods_id];
		$goods_info['goods_name'] = $proces_info->applyPurchase->goods_name;
		$goods_info['unit'] = $goods_data['id_unit'][$proces_info->applyPurchase->goods_id];
		$goods_info['num'] = $proces_info->applyPurchase->num;
		$goods_info['spec'] = $proces_info->applyPurchase->spec;
		if(!empty($proces_info->applyPurchase->images)){
			$images = explode(',', $proces_info->applyPurchase->images);
			foreach ($images as $k => $img) {
				$goods_info['images'][$k] = asset($img);
			}
		}else{
			$goods_info['images'] = array();
		}
		$goods_info['uses'] = $proces_info->applyPurchase->uses;
		if($proces_info->applyPurchase->degree_id == 1){
			$goods_info['degree'] = '紧急';
		}else if($proces_info->applyPurchase->degree_id == 2){
			$goods_info['degree'] = '一般';
		}else if($proces_info->applyPurchase->degree_id == 3){
			$goods_info['degree'] = '不紧急';
		}
		$goods_info['rdate'] = $proces_info->applyPurchase->rdate;
		$data['goods_info'] = $goods_info;

		//加载已经审核的人的评价
    	$pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyPurchase)::type);
    	$data['pre_audit_opinion'] = $audit_opinions = array();
    	if(!empty($pre_verify_users_data)){
    		foreach ($pre_verify_users_data as $key => $value) {
    			$audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
    			$audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
    		}
    		$data['pre_audit_opinion'] = $audit_opinions;
    	}
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /*
    * 采购申请-汇总
    * @author molin
    * @date 2018-10-22
    */
    public function index(){
    	$inputs = request()->all();

    	$applyPurchase = new \App\Models\ApplyPurchase;
        $data = $applyPurchase->getDataList($inputs);
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$cate = new \App\Models\GoodsCategory;
    	$cate_data = $cate->getIdToData();
    	$goods = new \App\Models\Goods;
    	$goods_data = $goods->getIdToData();

    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['type'] = $cate_data[$value->type_id];
    		$items['goods_name'] = $value->goods_name;
    		$items['dept'] = $value->hasDept->name;
    		$items['num'] = $value->num;
    		if($value->degree_id == 1){
    			$items['degree'] = '紧急';
    		}else if($value->degree_id == 2){
    			$items['degree'] = '一般';
    		}else{
    			$items['degree'] = '不紧急';
    		}
    		$items['created_at'] = $value['created_at']->format('Y-m-d H:i:s'); 
    		$items['status_txt'] = $value->status_txt;
    		$data['datalist'][$key]  = $items;
    	}
    	
    	$data['type_list'] = [['type_id' => 1, 'name' => '固定资产'], ['type_id' => 2, 'name' => '消耗品']];
    	$dept = new \App\Models\Dept;
		$dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
		$data['dept_list'] = $dept_list;
		$status = [['id' => 0,'name'=>'审核中'],['id' => 1, 'name'=>'已通过'],['id' => 2, 'name'=>'已驳回']];
		$data['search_status'] = $status;
    	
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /*
    * 我的采购申请-汇总
    * @author molin
    * @date 2018-10-22
    */
    public function mylist(){
    	$inputs = request()->all();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
    		//加载详情
    		if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
    			return response()->json(['code' => -1, 'message' => '缺少参数id']);
    		}
    		$inputs['apply_id'] = $inputs['id'];
	    	unset($inputs['id']);
	    	$audit_proces =new \App\Models\AuditProces;
	    	$proces_info = $audit_proces->getPurchaseInfo($inputs);
	    	if(empty($proces_info)){
	    		return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
	    	}
	    	$user = new \App\Models\User;
	    	$user_data = $user->getIdToData();

	    	$cate = new \App\Models\GoodsCategory;
	    	$cate_data = $cate->getIdToData();

	    	$goods = new \App\Models\Goods;
	    	$goods_data = $goods->getIdToData();

	    	//拼接数据
			$data = $user_info = $goods_info = array();
			//领用部门、申请人信息
	    	$user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
			$user_info['dept'] = $proces_info->hasDept->name;
			$data['user_info'] = $user_info;
			//物品信息
			if($proces_info->applyPurchase->type_id == 1){
				$goods_info['type'] = '固定资产';
			}else if($proces_info->applyPurchase->type_id == 2){
				$goods_info['type'] = '消费品资产';
			}
			$goods_info['cate'] = $cate_data[$proces_info->applyPurchase->cate_id];
			$goods_info['goods'] = $goods_data['id_name'][$proces_info->applyPurchase->goods_id];
			$goods_info['goods_name'] = $proces_info->applyPurchase->goods_name;
			$goods_info['unit'] = $goods_data['id_unit'][$proces_info->applyPurchase->goods_id];
			$goods_info['num'] = $proces_info->applyPurchase->num;
			$goods_info['spec'] = $proces_info->applyPurchase->spec;
			if(!empty($proces_info->applyPurchase->images)){
				$images = explode(',', $proces_info->applyPurchase->images);
				foreach ($images as $k => $img) {
					$goods_info['images'][$k] = asset($img);
				}
			}else{
				$goods_info['images'] = array();
			}
			$goods_info['uses'] = $proces_info->applyPurchase->uses;
			if($proces_info->applyPurchase->degree_id == 1){
				$goods_info['degree'] = '紧急';
			}else if($proces_info->applyPurchase->degree_id == 2){
				$goods_info['degree'] = '一般';
			}else if($proces_info->applyPurchase->degree_id == 3){
				$goods_info['degree'] = '不紧急';
			}
			$goods_info['rdate'] = $proces_info->applyPurchase->rdate;
			$data['goods_info'] = $goods_info;

			//加载已经审核的人的评价
	    	$pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyPurchase)::type);
	    	$data['pre_audit_opinion'] = $audit_opinions = array();
	    	if(!empty($pre_verify_users_data)){
	    		foreach ($pre_verify_users_data as $key => $value) {
	    			$audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
	    			$audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
	    		}
	    		$data['pre_audit_opinion'] = $audit_opinions;
	    	}
	    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    	}
    	$inputs['user_id'] = auth()->user()->id;
    	$applyPurchase = new \App\Models\ApplyPurchase;
        $data = $applyPurchase->getDataList($inputs);
    	$user = new \App\Models\User;
    	$user_data = $user->getIdToData();
    	$cate = new \App\Models\GoodsCategory;
    	$cate_data = $cate->getIdToData();
    	$goods = new \App\Models\Goods;
    	$goods_data = $goods->getIdToData();

    	$items = array();
    	foreach ($data['datalist'] as $key => $value) {
    		$items['id'] = $value->id;
    		$items['type'] = $cate_data[$value->type_id];
    		$items['goods_name'] = $value->goods_name;
    		$items['dept'] = $value->hasDept->name;
    		$items['num'] = $value->num;
    		if($value->degree_id == 1){
    			$items['degree'] = '紧急';
    		}else if($value->degree_id == 2){
    			$items['degree'] = '一般';
    		}else{
    			$items['degree'] = '不紧急';
    		}
    		$items['created_at'] = $value['created_at']->format('Y-m-d H:i:s'); 
    		$items['status_txt'] = $value->status_txt;
    		if($value->status == 1 && $value->if_check == 0){
    			$items['if_check'] = 1;//是否验收
    		}else{
    			$items['if_check'] = 0;
    		}
    		$data['datalist'][$key]  = $items;
    	}
    	
    	$data['type_list'] = [['type_id' => 1, 'name' => '固定资产'], ['type_id' => 2, 'name' => '消耗品']];
    	$dept = new \App\Models\Dept;
		$dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
		$data['dept_list'] = $dept_list;
		$status = [['id' => 0,'name'=>'审核中'],['id' => 1, 'name'=>'已通过'],['id' => 2, 'name'=>'已驳回']];
		$data['search_status'] = $status;
    	
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    

    /** 
    *  资产入库
    *  @author molin
    *   @date 2018-10-30
    */
    public function put(){
        $inputs = request()->all();
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'view'){
            //加载详情
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $inputs['apply_id'] = $inputs['id'];
            unset($inputs['id']);
            $audit_proces =new \App\Models\AuditProces;
            $proces_info = $audit_proces->getPurchaseInfo($inputs);
            if(empty($proces_info)){
                return response()->json(['code' => -1, 'message' => '数据不存在，请刷新后重试']);
            }
            $user = new \App\Models\User;
            $user_data = $user->getIdToData();

            $cate = new \App\Models\GoodsCategory;
            $cate_data = $cate->getIdToData();

            $goods = new \App\Models\Goods;
            $goods_data = $goods->getIdToData();

            //拼接数据
            $data = $user_info = $goods_info = $caigou_info = $yanshou_info = array();
            //领用部门、申请人信息
            $user_info['realname'] = $user_data['id_realname'][$proces_info->user_id];
            $user_info['dept'] = $proces_info->hasDept->name;
            $data['user_info'] = $user_info;
            //物品信息
            if($proces_info->applyPurchase->type_id == 1){
                $goods_info['type'] = '固定资产';
            }else if($proces_info->applyPurchase->type_id == 2){
                $goods_info['type'] = '消费品资产';
            }
            $goods_info['cate'] = $cate_data[$proces_info->applyPurchase->cate_id];
            $goods_info['goods'] = $goods_data['id_name'][$proces_info->applyPurchase->goods_id];
            $goods_info['goods_name'] = $proces_info->applyPurchase->goods_name;
            $goods_info['unit'] = $goods_data['id_unit'][$proces_info->applyPurchase->goods_id];
            $goods_info['num'] = $proces_info->applyPurchase->num;
            $goods_info['spec'] = $proces_info->applyPurchase->spec;
            if(!empty($proces_info->applyPurchase->images)){
                $images = explode(',', $proces_info->applyPurchase->images);
                foreach ($images as $k => $img) {
                    $goods_info['images'][$k] = asset($img);
                }
            }else{
                $goods_info['images'] = array();
            }
            $goods_info['uses'] = $proces_info->applyPurchase->uses;
            if($proces_info->applyPurchase->degree_id == 1){
                $goods_info['degree'] = '紧急';
            }else if($proces_info->applyPurchase->degree_id == 2){
                $goods_info['degree'] = '一般';
            }else if($proces_info->applyPurchase->degree_id == 3){
                $goods_info['degree'] = '不紧急';
            }
            $goods_info['rdate'] = $proces_info->applyPurchase->rdate;
            $data['goods_info'] = $goods_info;

            //加载已经审核的人的评价
            $pre_verify_users_data = $audit_proces->getProcessList(array('apply_id'=>$proces_info['apply_id']), (new \App\Models\ApplyPurchase)::type);
            $data['pre_audit_opinion'] = $audit_opinions = array();
            if(!empty($pre_verify_users_data)){
                foreach ($pre_verify_users_data as $key => $value) {
                    $audit_opinions[$key]['user'] = $user_data['id_rank'][$value->current_verify_user_id].$user_data['id_realname'][$value->current_verify_user_id].'评价';
                    $audit_opinions[$key]['pre_audit_opinion'] = $value->audit_opinion;
                }
                $data['pre_audit_opinion'] = $audit_opinions;
            }
            //采购部分
            $order = new \App\Models\PurchaseOrder;
            $order_info = $order->where('apply_id', $proces_info->applyPurchase->id)->first();
            if(!empty($order_info)){
                $tmp = array();
                $tmp['user'] = $user_data['id_realname'][$order_info->user_id];
                $tmp['way'] = $order_info->way;
                $tmp['spec'] = $order_info->spec;
                $images = array();
                if(!empty($order_info->images)){
                    foreach (explode(',', $order_info->images) as $key => $value) {
                        $images[] = asset($value);
                    }
                }
                $tmp['images'] = $images;
                $tmp['price'] = $order_info->price;
                $tmp['num'] = $order_info->num;
                $tmp['total_price'] = $tmp['price'] * $tmp['num'];
                $tmp['order_sn'] = $order_info->order_sn;
                $tmp['express_sn'] = $order_info->express_sn;
                $caigou_info = $tmp;
            }
            $data['caigou_info'] = $caigou_info;
            //验收部分
            if($proces_info->applyPurchase->if_check == 1){
                $yanshou_info['user'] = $user_data['id_realname'][$proces_info->applyPurchase->check_user];
                $yanshou_info['put_num'] = $order_info->put_num;
                $yanshou_info['remarks'] = $proces_info->applyPurchase->remarks;
            }
            $data['yanshou_info'] = $yanshou_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'caigou_load'){
            //采购加载
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $purchase =new \App\Models\ApplyPurchase;
            $info = $purchase->getDataInfo($inputs);
            //拼接数据
            $data = $goods_info = array();
            $data['id'] = $inputs['id'];
            //物品信息
            $goods_info['goods_name'] = $info->goods_name;
            $goods_info['num'] = $info->num;
            $goods_info['spec'] = $info->spec;
            if(!empty($info->images)){
                $images = explode(',', $info->images);
                foreach ($images as $k => $img) {
                    $goods_info['images'][$k] = asset($img);
                }
            }else{
                $goods_info['images'] = array();
            }
            $data['goods_info'] = $goods_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'upload'){
            //上传图片
            $upload_file = \request()->file('file');
            if(!empty($upload_file)){
                $ext = $upload_file->getClientOriginalExtension();//扩展名
                $type_arr = ['jpg','png','gif','jpeg'];
                if(!in_array($ext, $type_arr)){
                    return response()->json(['code' => -1, 'message' => '只能上传jpg/png/gif图片！！', 'data' => ['type' => $ext]]);
                }
                $size = $upload_file->getSize();//文件大小  限制大小2M
                if($size > 2097152){
                    return response()->json(['code' => -1, 'message' => '只能上传2M以内的图片！！', 'data' => null]);
                }
                //重命名
                $fileName = date('YmdHis').uniqid().'.'.$ext;
                $upload_file->move(storage_path('app/public/uploads/purchase/'), $fileName);
                $data = array();
                $data['file'] = '/storage/uploads/purchase/'.$fileName;
                $data['file_path'] = asset('/storage/uploads/purchase/'.$fileName);
                return response()->json(['code' => 1, 'message' => '上传成功', 'data' => $data]);
            }else{
                return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
            }

        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'caigou'){
            //采购单提交
            $rules = [
                'id' => 'required|integer',
                'way' => 'required|max:50',
                'spec' => 'required|max:255',
                'images' => 'required|array',
                'price' => 'required|numeric',
                'num' => 'required|integer',
                'order_sn' => 'required',
                'express_sn' => 'required'
            ];
            $attributes = [
                'id' => '参数id',
                'way' => '途径',
                'spec' => '物品规格',
                'images' => '图片',
                'price' => '单价',
                'num' => '数量',
                'order_sn' => '订单号',
                'express_sn' => '物流单号'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }
            $purchase =new \App\Models\ApplyPurchase;
            $info = $purchase->where('id', $inputs['id'])->where('status', 1)->where('state', 0)->first();
            if(empty($info)){
                return response()->json(['code' => -1, 'message' => '数据不存在或该流程未审核通过']);
            }
            $order = new \App\Models\PurchaseOrder;
            $if_exist = $order->where('apply_id', $inputs['id'])->select(['id'])->first();
            if(!empty($if_exist)){
                return response()->json(['code' => 0, 'message' => '操作成功']);
            }
            $res = $order->storeData($inputs);
            if($res){
                $info->state = 1;//采购中
                $info->save();
                systemLog('物资管理', '操作了开始采购['.$info->goods_name.']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);

        }
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'yanshou_load'){
            //验收加载
            if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
                return response()->json(['code' => -1, 'message' => '缺少参数id']);
            }
            $order =new \App\Models\PurchaseOrder;
            $order_info = $order->where('apply_id', $inputs['id'])->first();
            //拼接数据
            $data = $goods_info = array();
            //物品信息
            $goods_info['id'] = $order_info->id;
            $goods_info['spec'] = $order_info->spec;
            if(!empty($order_info->images)){
                $images = array();
                foreach (explode(',', $order_info->images) as $key => $value) {
                    $images[] = asset($value);
                }
                $goods_info['images'] = $images;
            }else{
                $goods_info['images'] = [];
            }
            $goods_info['price'] = $order_info->price;
            $goods_info['num'] = $order_info->num;
            $goods_info['total_price'] = $goods_info['price'] * $goods_info['num'];
            $data['goods_info'] = $goods_info;
            return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

        }

        if(isset($inputs['request_type']) && $inputs['request_type'] == 'yanshou'){
            //验收提交 
            $rules = [
                'id' => 'required|integer',
                'number' => 'required|integer',
                'remarks' => 'required|max:255'
            ];
            $attributes = [
                'id' => '参数id',
                'number' => '入库数量',
                'remarks' => '说明'
            ];
            $validator = validator($inputs, $rules, [], $attributes);
            if ($validator->fails()) {
                return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
            }

            $order = new \App\Models\PurchaseOrder;
            $order_info = $order->where('id', $inputs['id'])->first();
            $order_info->put_num = $inputs['number'];
            if($order_info->num < $inputs['number']){
                return response()->json(['code' => 0, 'message' => '入库数量不能大于采购数量']);
            }
            //验收入库 加库存  生成资产编号等
            $purchase = new \App\Models\ApplyPurchase;
            $purchase_info = $purchase->where('id', $order_info->apply_id)->first();
            if($purchase_info->if_check == 1 || $purchase_info->state == 2){
                return response()->json(['code' => 0, 'message' => '该申请已经验收，请勿重复提交']);
            }
            $purchase_info->remarks = $inputs['remarks'];
            $purchase_info->if_check = 1;//已验收
            $purchase_info->check_user = auth()->user()->id;//验收人
            $purchase_info->state = 2;//验收完成状态
            $goods = new \App\Models\Goods;
            $record = new \App\Models\GoodsStorageRecord;//入库记录
            $goods_info = $goods->where('id', $purchase_info->goods_id)->first();
            $record->goods_id = $goods_info->id;
            $record->pre_storage = $goods_info->storage;//修改前的库存
            $record->cur_storage = $goods_info->storage + $inputs['number'];//修改后的库存
            $record->user_id = auth()->user()->id;//验收人
            $record->type = 2;//1为普通修改 2为入库
            $goods_info->storage += $inputs['number'];//增加库存 

            $detail = new \App\Models\GoodsDetail;
            $detail_info = $detail->where('goods_id', $purchase_info->goods_id)->orderBy('number','desc')->first();
            if(!empty($detail_info)){
                $start_str = substr($detail_info->number, -4);//如编号为010010001  则获取0001
                $id_str = '';
                $insert_arr = array();
                $m = intval($start_str);
                $pre_str = str_pad($purchase_info->cate_id, 2, '0', STR_PAD_LEFT).str_pad($purchase_info->goods_id, 3, '0', STR_PAD_LEFT);
                for($i = 0; $i < intval($inputs['number']); $i++){
                    $m = $m + 1;
                    $id_str = str_pad($m, 4, '0', STR_PAD_LEFT);
                    $str = $pre_str.$id_str;
                    $insert_arr[$m]['number'] = $str;
                    $insert_arr[$m]['goods_id'] = $purchase_info->goods_id;
                    $insert_arr[$m]['name'] = $purchase_info->goods_name;
                    $insert_arr[$m]['status'] = 1;
                    $insert_arr[$m]['created_at'] = date('Y-m-d H:i:s');
                    $insert_arr[$m]['updated_at'] = date('Y-m-d H:i:s');
                }
            }else{
                $id_str = '';
                $insert_arr = array();
                $m = 0;
                $pre_str = str_pad($purchase_info->cate_id, 2, '0', STR_PAD_LEFT).str_pad($purchase_info->goods_id, 3, '0', STR_PAD_LEFT);
                for($i = 0; $i < $inputs['number']; $i++){
                    $m = $m + 1;
                    $id_str = str_pad($m, 4, '0', STR_PAD_LEFT);
                    $str = $pre_str.$id_str;
                    $insert_arr[$m]['number'] = $str;
                    $insert_arr[$m]['goods_id'] = $purchase_info->goods_id;
                    $insert_arr[$m]['name'] = $purchase_info->goods_name;
                    $insert_arr[$m]['status'] = 1;
                    $insert_arr[$m]['created_at'] = date('Y-m-d H:i:s');
                    $insert_arr[$m]['updated_at'] = date('Y-m-d H:i:s');
                }
            }
            $res = false;
            DB::transaction(function () use ($order_info, $purchase_info, $goods_info, $detail, $insert_arr, $record) {
                $order_info->save();
                $purchase_info->save();
                $goods_info->save();
                $record->save();
                $detail->insert($insert_arr);
            }, 5);
            $res = true;
            if($res){
                systemLog('物资管理', '验收入库['.$goods_info->name.']');
                return response()->json(['code' => 1, 'message' => '操作成功']);
            }
            return response()->json(['code' => 0, 'message' => '操作失败']);

        }
        $inputs['status'] = 1;//获取审核通过的数据
        $applyPurchase = new \App\Models\ApplyPurchase;
        $data = $applyPurchase->getDataList($inputs);

        $items = array();
        foreach ($data['datalist'] as $key => $value) {
            $items['id'] = $value->id;
            if($value['type_id'] == 1){
                $items['type'] = '固定资产';
            }else{
                $items['type'] = '消耗品';
            }
            $items['goods_name'] = $value->goods_name;
            $items['num'] = $value->num;
            if($value->degree_id == 1){
                $items['degree'] = '紧急';
            }else if($value->degree_id == 2){
                $items['degree'] = '一般';
            }else{
                $items['degree'] = '不紧急';
            }
            $items['created_at'] = $value['created_at']->format('Y-m-d H:i:s'); 
            $items['rdate'] = $value['rdate']; 
            $cai_num = $put_num = 0;//采购数和入库数
            if(isset($value->hasOrder) && !empty($value->hasOrder)){
                foreach ($value->hasOrder as $k => $val) {
                    $cai_num += $val->num;
                    $put_num += $val->put_num;
                }
            }else{
                $cai_num = $put_num = '--';
            }
            $items['cai_num'] = $cai_num;
            $items['put_num'] = $put_num;
            $items['state'] = $value->state;
            if($value->state == 0){
                $items['status_txt'] = '未采购';
            }else if($value->state == 1){
                $items['status_txt'] = '采购中';
            }else if($value->state == 2){
                $items['status_txt'] = '已完成';
            }
            $data['datalist'][$key]  = $items;
        }
        
        $data['type_list'] = [['type_id' => 1, 'name' => '固定资产'], ['type_id' => 2, 'name' => '消耗品']];
        $dept = new \App\Models\Dept;
        $dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
        $data['dept_list'] = $dept_list;
        $status = [['id' => 0, 'name'=>'未采购'],['id' => 1, 'name' =>'采购中'],['id' => 2,'name'=>'已完成']];
        $data['search_state'] = $status;
        
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }


}

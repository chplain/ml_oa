<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use DB;

class BusinessOrder extends Model
{
    //商务单
    protected $table = 'business_orders';

    public $customer_types = [['id'=> 1,'name' => '直客'], ['id'=> 2, 'name' => '渠道']];
    public $project_types = [['id'=> 1,'name' => '平台'], ['id'=> 2, 'name' => '非平台']];
    public $test_cycles = [['id'=> 1,'name' => '免费一天'], ['id'=> 2, 'name' => '一周'], ['id'=> 3, 'name' => '一个月']];
    public $settlement_lists = [['id'=>'CPA','name'=>'CPA'],['id'=>'CPS','name'=>'CPS'],['id'=>'CPC','name'=>'CPC'],['id'=>'CPD','name'=>'CPD'],['id'=>'CPA+CPS','name'=>'CPA+CPS']];
    public $settlement_types = [['id'=> 1,'name' => '月结'], ['id'=> 2, 'name' => '预付费'], ['id'=> 3, 'name' => '季度结算'], ['id'=> 4, 'name' => '项目性支付'], ['id'=> 5, 'name' => '其它'], ['id'=> 6, 'name' => '周结']];
    public $link_types = [['id'=> 1,'name' => '分链接'], ['id'=> 2, 'name' => '自适应']];
    public $deliver_types = [['id'=>0, 'name'=> '收件箱（默认QQ）'],['id'=>1, 'name' => '垃圾箱'],['id'=>2, 'name' => '非QQ'],['id'=>3, 'name' => '收件箱（公司内部项目）'], ['id' => 4, 'name' => '投递补量']];
    public $cooperation_cycles = [['id'=>0, 'name'=>'未知'],['id'=>1, 'name' => '长期'],['id'=>2, 'name' => '短期']];

    public $type_lists = [['id' => 1, 'name' => 'EDM技术服务合同'], ['id' => 2, 'name' => '系统开发合同'], ['id' => 3, 'name' => '服务器托管合同'], ['id' => 4, 'name' => '代理合同'], ['id' => 5, 'name' => '购销合同'], ['id' => 6, 'name' => 'API']];
    public $send_groups = [['id'=> 0,'name' => '测试段'], ['id'=> 1, 'name' => '运营段'], ['id'=> 2, 'name' => '陈董段'], ['id'=> 3, 'name' => '非QQ段'], ['id'=> 4, 'name' => '外发段']];

    public $resource_types = [['id'=> 1,'name' => '正常投递'], ['id'=> 2, 'name' => '触发'], ['id'=> 3, 'name' => '特殊组段']];
    public $income_main_types = [['id'=> 1,'name' => '神灯'], ['id'=> 2, 'name' => '技术']];

    public $project_status = [['id'=>0,'name'=>'待投递'],['id'=>1,'name'=>'投递中'],['id'=>2,'name'=>'投递完毕'],['id'=>3,'name'=>'投递暂停']];

    // 关联行业
    public function trade()
    {
        return $this->belongsTo('App\Models\Trade', 'trade_id', 'id');
    }

     // 
    public function businessUser()
    {
        return $this->belongsTo('App\Models\User', 'project_business', 'id');
    }

    //关联投放链接
    public function hasLinks()
    {
        return $this->hasMany('App\Models\BusinessOrderLink', 'order_id', 'id');
    }

	//关联通知人员
    public function hasNotices()
    {
        return $this->hasMany('App\Models\BusinessOrderNotice', 'order_id', 'id');
    }

    //关联项目
    public function hasProject()
    {
        return $this->hasMany('App\Models\BusinessProject', 'order_id', 'id');
    }

    // 关联客户
    public function hasCustomer()
    {
        return $this->belongsTo('App\Models\BusinessOrderCustomer', 'customer_id', 'id');
    }

    // 关联审核人
    public function hasVerify()
    {
        return $this->hasMany('App\Models\BusinessOrderVerify', 'order_id', 'id');
    }

    public function saleUser(){
        return $this->belongsTo('App\Models\User', 'project_sale', 'id');
    }

    //保存数据
    public function storeData($inputs){
    	$business_order = new BusinessOrder;
    	if(isset($inputs['id']) && is_numeric($inputs['id']) && $inputs['id'] > 0){
    		//编辑
    		$business_order = $business_order->where('id', $inputs['id'])->first();
    	}else{
    		//新增
    		$max_id = $business_order->max('id');
	    	$max_id = $max_id ? $max_id : 0;
	    	$swd_id = $max_id + 1;
	    	if(strlen($swd_id) <= 4){
	    		$n = 4;
	    	}else{
	    		$n = strlen($swd_id);
	    	}
	    	$business_order->swd_id = 'SWD'.str_pad($swd_id, $n, '0', STR_PAD_LEFT);
	    	$business_order->user_id = auth()->user()->id;
    	}
    	
    	$business_order->customer_id = $inputs['customer_id'];
    	$business_order->project_name = $inputs['project_name'];
    	$business_order->project_type = $inputs['project_type'];
    	$business_order->project_sale = $inputs['project_sale'];
    	$business_order->project_business = $inputs['project_business'];
    	$business_order->trade_id = $inputs['trade_id'];
    	$business_order->test_cycle = $inputs['test_cycle'] ?? '';
    	$business_order->direct_area = $inputs['direct_area'] ?? '';
    	$business_order->remarks = $inputs['remarks'] ?? '';
    	$business_order->settlement_type = implode(',', $inputs['settlement_type']);
    	$business_order->definition = $inputs['definition'] ?? '';
    	$business_order->feedback = $inputs['feedback'] ?? '';
    	$business_order->get_data = $inputs['get_data'] ?? '';
    	$business_order->tpl_demand = $inputs['tpl_demand'] ?? '';
    	$business_order->if_verify = $inputs['if_verify'] ?? 0;
    	$business_order->if_has = $inputs['if_has'] ?? 0;
    	$business_order->logo_demand = $inputs['logo_demand'] ?? '';
    	$business_order->website = $inputs['website'] ?? '';
    	$business_order->theme = $inputs['theme'] ?? '';
    	$business_order->feature = $inputs['feature'] ?? '';
    	$business_order->other = isset($inputs['other']) && !empty($inputs['other']) ? serialize($inputs['other']) : '';
    	$business_order->verify_user_id = $inputs['verify_user_id'];//
        $business_order->comment = $inputs['comment'] ?? '提交商务单';//提交备注

    	//投放链接
    	$business_order_link = new \App\Models\BusinessOrderLink;
    	//通知人 
        $business_order_notice = new \App\Models\BusinessOrderNotice;
    	$business_order_verify = new \App\Models\BusinessOrderVerify;
        $business_order_price = new \App\Models\BusinessOrderPrice;
    	DB::transaction(function () use ($business_order, $business_order_link, $business_order_notice, $inputs, $business_order_verify, $business_order_price) {
			$business_order->save();

			if(!isset($inputs['id'])){
				//添加
		    	foreach ($inputs['links'] as $key => $value) {
                    //链接保存
                    $business_order_link->order_id = $business_order->id;
                    $business_order_link->link_type = $value['link_type'];
                    $business_order_link->link_name = $value['link_name'];
                    $business_order_link->pc_link = $value['pc_link'] ?? '';
                    $business_order_link->wap_link = $value['wap_link'] ?? '';
                    $business_order_link->zi_link = $value['zi_link'] ?? '';
                    $business_order_link->remarks = $value['remarks'];
                    $business_order_link->project_id = 0;//未分配
                    $business_order_link->if_use = $value['if_use'];//是否启用
                    $business_order_link->pricing_manner = $value['pricing_manner'];//计价方式 CPA/CPS/CPC/CPD/CPA+CPS
                    $business_order_link->market_price = serialize($value['market_price']);
                    $business_order_link->save();

                    //记录单价记录
                    $insert_price_log = array();
                    $insert_price_log['order_id'] = $business_order->id;
                    $insert_price_log['old_pricing_manner'] = '';
                    $insert_price_log['old_market_price'] = '';
                    $insert_price_log['pricing_manner'] = $value['pricing_manner'];
                    $insert_price_log['market_price'] = serialize($value['market_price']);
                    $insert_price_log['remarks'] = '新增';
                    $insert_price_log['link_id'] = $business_order_link->id;
                    $insert_price_log['start_time'] = 0;
                    $insert_price_log['end_time'] = 0;
                    $insert_price_log['user_id'] = auth()->user()->id;
                    $insert_price_log['notice_user_ids'] = '';
                    $insert_price_log['created_at'] = date('Y-m-d H:i:s');
                    $insert_price_log['updated_at'] = date('Y-m-d H:i:s');
                    $business_order_price->insert($insert_price_log);
		    	}
			}else{
				//编辑
				$link_ids = $business_order_link->where('order_id', $business_order->id)->pluck('id')->toArray();
				$inputs_link_ids = array();
				foreach ($inputs['links'] as $key => $value) {
					$inputs_link_ids[] = $value['id'];
					if($value['id'] > 0){
                        //更新
                        $update_link = $business_order_link->where('id', $value['id'])->first();
                        $update_link->order_id = $business_order->id;
                        $update_link->link_type = $value['link_type'];
                        $update_link->link_name = $value['link_name'];
                        $update_link->pc_link = $value['pc_link'] ?? '';
                        $update_link->wap_link = $value['wap_link'] ?? '';
                        $update_link->zi_link = $value['zi_link'] ?? '';
                        $update_link->remarks = $value['remarks'];
                        $update_link->project_id = 0;//未分配
                        $update_link->if_use = $value['if_use'];//是否启用
                        $update_link->pricing_manner = $value['pricing_manner'];//计价方式 CPA/CPS/CPC/CPD/CPA+CPS
                        $update_link->market_price = serialize($value['market_price']);
                        $update_link->save();
                        
                        //记录单价记录
                        $price_log_info = $business_order_price->where('order_id', $business_order->id)->where('link_id', $value['id'])->first();
                        if(!empty($price_log_info)){
                            $price_log_info->pricing_manner = $value['pricing_manner'];
                            $price_log_info->market_price = serialize($value['market_price']);
                            $price_log_info->save();
                        }

					}else{
                        //新增链接
                        $business_order_link->order_id = $business_order->id;
                        $business_order_link->link_type = $value['link_type'];
                        $business_order_link->link_name = $value['link_name'];
                        $business_order_link->pc_link = $value['pc_link'] ?? '';
                        $business_order_link->wap_link = $value['wap_link'] ?? '';
                        $business_order_link->zi_link = $value['zi_link'] ?? '';
                        $business_order_link->remarks = $value['remarks'];
                        $business_order_link->project_id = 0;//未分配
                        $business_order_link->if_use = $value['if_use'];//是否启用
                        $business_order_link->pricing_manner = $value['pricing_manner'];//计价方式 CPA/CPS/CPC/CPD/CPA+CPS
                        $business_order_link->market_price = serialize($value['market_price']);
                        $business_order_link->save();
                        
                        //记录单价记录
                        $insert_price_log = array();
                        $insert_price_log['order_id'] = $business_order->id;
                        $insert_price_log['old_pricing_manner'] = '';
                        $insert_price_log['old_market_price'] = '';
                        $insert_price_log['pricing_manner'] = $value['pricing_manner'];
                        $insert_price_log['market_price'] = serialize($value['market_price']);
                        $insert_price_log['remarks'] = '新增';
                        $insert_price_log['link_id'] = $business_order_link->id;
                        $insert_price_log['start_time'] = 0;
                        $insert_price_log['end_time'] = 0;
                        $insert_price_log['user_id'] = auth()->user()->id;
                        $insert_price_log['notice_user_ids'] = '';
                        $insert_price_log['created_at'] = date('Y-m-d H:i:s');
                        $insert_price_log['updated_at'] = date('Y-m-d H:i:s');
                        $business_order_price->insert($insert_price_log);
                        
					}
				}
				$del_ids = array();
				foreach ($link_ids as $val) {
					if(!in_array($val, $inputs_link_ids)){
						$del_ids[] = $val;
					}
				}
				if(!empty($del_ids)){
					$business_order_link->destroy($del_ids);//软删除
				}
				
			}
			
	    	//通知
	    	if(isset($inputs['id']) && is_numeric($inputs['id']) && $inputs['id'] > 0){
				$business_order_notice->where('order_id', $business_order->id)->delete();// 编辑时 先删除
			}
	    	$notice_insert  = array();
	    	foreach ($inputs['notice_users'] as $key => $value) {
                $tmp = array();
	    		$tmp['order_id'] = $business_order->id;
	    		$tmp['user_id'] = $value;
	    		$tmp['status'] = 0;
	    		$tmp['created_at'] = date('Y-m-d H:i:s');
	    		$tmp['updated_at'] = date('Y-m-d H:i:s');
	    		$notice_insert[] = $tmp;
	    	}
	    	$business_order_notice->insert($notice_insert);

            //审核
            if(isset($inputs['id']) && is_numeric($inputs['id']) && $inputs['id'] > 0){
                $business_order_verify->where('order_id', $business_order->id)->delete();// 编辑时 先删除
            }
            $business_order_verify->order_id = $business_order->id;
            $business_order_verify->user_id = $inputs['verify_user_id'];
            $business_order_verify->status = 0;
            $business_order_verify->save();
		}, 5);
		$result = true;
		return $result;
    }

    //获取单条数据
    public function getOrderInfo($inputs){
    	$business_order = new BusinessOrder;
    	$query_where = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function($query) use ($inputs){
    		return $query->where('id', $inputs['id']);
    	})
        ->when(isset($inputs['swd_id']) && !empty($inputs['swd_id']), function($query) use ($inputs){
            return $query->where('swd_id', $inputs['swd_id']);
        })
    	->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
    		return $query->where('status', $inputs['status']);
    	})
    	->when(isset($inputs['verify_user_id']) && is_numeric($inputs['verify_user_id']), function($query) use ($inputs){
    		return $query->where('verify_user_id', $inputs['verify_user_id']);
    	})
    	->when(isset($inputs['status_in']) && is_array($inputs['status_in']), function($query) use ($inputs){
    		return $query->whereIn('status', $inputs['status_in']);
    	})
    	->with(['trade' => function ($query) use ($inputs) {
        	return $query->select(['id', 'name']);
        }])
        ->with(['hasCustomer'=> function ($query) use ($inputs){
            $query->select(['id', 'customer_name', 'customer_type', 'customer_tel', 'contacts', 'customer_email', 'customer_qq', 'bank_accounts', 'customer_address']);
        }])
    	->with(['hasLinks'=> function ($query) use ($inputs){
    		$query->with(['hasProject'=> function ($query) use ($inputs){
                $query->select(['id', 'project_name']);
            }])->select(['id', 'order_id', 'link_type', 'link_name', 'pc_link', 'wap_link', 'zi_link', 'remarks', 'if_use', 'pricing_manner', 'market_price', 'project_id', 'created_at', 'updated_at']);
    	}])
    	->with(['hasNotices'=> function ($query) use ($inputs){
    		$query->select(['id', 'order_id', 'user_id']);
    	}])
        ->with(['hasProject'=> function ($query) use ($inputs){
            $query->select(['id', 'order_id', 'charge_id', 'execute_id', 'created_at']);
        }])
        ->with(['hasVerify'=> function ($query) use ($inputs){
            $query->select(['id', 'order_id', 'user_id', 'status', 'comment','created_at']);
        }]);
    	$info = $query_where->first();
    	return $info;
    }


    //获取列表数据
    public function getDataList($inputs){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['customer_type']) && is_numeric($inputs['customer_type']), function($query) use ($inputs){
                    return $query->whereHas('hasCustomer', function($query)use($inputs){
                        $query->where('customer_type', $inputs['customer_type']);
                    });
                })
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                    return $query->where(function($query)use($inputs){
                        $query->where('user_id', $inputs['user_id'])->orWhere('project_business', $inputs['user_id']);
                    });
                })
                ->when(isset($inputs['verify_user_id']) && is_numeric($inputs['verify_user_id']), function($query) use ($inputs){
                    return $query->where('verify_user_id', $inputs['verify_user_id']);
                })
                ->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
                    return $query->where('status', $inputs['status']);
                })
                ->when(isset($inputs['customer_name']) && !empty($inputs['customer_name']), function($query) use ($inputs){
                    return $query->whereHas('hasCustomer', function($query)use($inputs){
                        $query->where('customer_name', 'like', '%'.$inputs['customer_name'].'%');
                    });
                })
                ->when(isset($inputs['project_name']) && !empty($inputs['project_name']), function($query) use ($inputs){
                    return $query->where('project_name', 'like', '%'.$inputs['project_name'].'%');
                })
                ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                    return $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                })
                ->when(isset($inputs['project_business']) && !empty($inputs['project_business']), function($query) use ($inputs){
                    return $query->whereHas('businessUser', function ($query2) use ($inputs){
                    	return $query2->where('realname', 'like', '%'.$inputs['project_business'].'%');
                    });
                })
                ->when(isset($inputs['my_verify_id']) && is_numeric($inputs['my_verify_id']), function($query) use ($inputs){
                    $query->where(function($query)use($inputs){
                        return $query->whereHas('hasVerify', function ($query2) use ($inputs){
                            return $query2->where('user_id', $inputs['my_verify_id']);
                        });
                    })->orWhere('create_user_id', $inputs['my_verify_id']);
                    
                })
                ->with(['hasCustomer'=> function ($query) use ($inputs){
                    $query->select(['id', 'customer_name', 'customer_type', 'customer_tel', 'contacts', 'customer_email', 'customer_qq', 'bank_accounts', 'customer_address']);
                }])
                ->with(['trade' => function ($query) use ($inputs) {
                	return $query->select(['id', 'name']);
                }])
                ->with(['hasNotices' => function ($query) use ($inputs) {
                	return $query->select(['order_id', 'user_id']);
                }])
                ->with(['hasLinks' => function ($query) use ($inputs){
                    $query->select(['order_id', 'link_type', 'link_name', 'pc_link', 'wap_link', 'zi_link', 'remarks', 'if_use', 'pricing_manner', 'market_price', 'project_id', 'created_at', 'updated_at']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')
        		->when(!isset($inputs['export']), function ($query) use ($start, $length){
        			return $query->skip($start)->take($length);
        		})
                ->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }


}

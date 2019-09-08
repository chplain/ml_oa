<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOrderLinkApply extends Model
{
    //链接申请表
    protected $table = 'business_order_link_applys';

    // 关联项目信息
    public function hasOrder()
    {
        return $this->belongsTo('App\Models\BusinessOrder', 'order_id', 'id');
    }


    //保存数据
    public function storeData($inputs){
    	//申请表
    	$apply_link = new BusinessOrderLinkApply;
        $apply_link->user_id = auth()->user()->id;
        $apply_link->project_id = $inputs['project_id'];
        $apply_link->order_id = $inputs['order_id'];
        $apply_link->degree_id = $inputs['degree_id'];
        $apply_link->remarks = $inputs['remarks'];
        $apply_link->business_id = $inputs['business_id'];
        $apply_link->old_links = $inputs['old_links'];
        $apply_link->status = 0;
        return $apply_link->save();

    }

    //获取数据列表
    public function getLinkList($inputs){
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        
        $where_query = $this->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function($query) use ($inputs){
                    return $query->where('user_id', $inputs['user_id']);
                })
                ->when(isset($inputs['status']) && is_numeric($inputs['status']), function($query) use ($inputs){
                    return $query->where('status', $inputs['status']);
                })
                ->when(isset($inputs['business_id']) && is_numeric($inputs['business_id']), function($query) use ($inputs){
                    return $query->where('business_id', $inputs['business_id']);
                })
                ->when(isset($inputs['degree_id']) && is_numeric($inputs['degree_id']), function($query) use ($inputs){
                    return $query->where('degree_id', $inputs['degree_id']);
                })
                ->when(isset($inputs['project_name']) && !empty($inputs['project_name']), function($query) use ($inputs){
                    return $query->whereHas('hasOrder', function($query)use($inputs){
                        $query->where('project_name', 'like', '%'.$inputs['project_name'].'%');
                    });
                })
                ->when(isset($inputs['customer_name']) && !empty($inputs['customer_name']), function($query) use ($inputs){
                    return $query->whereHas('hasOrder', function($query)use($inputs){
                        $query->whereHas('hasCustomer', function($query)use($inputs){
                            $query->where('customer_name', 'like', '%'.$inputs['customer_name'].'%');
                        });
                    });
                })
                ->when(isset($inputs['business']) && !empty($inputs['business']), function($query) use ($inputs){
                    return $query->whereHas('hasOrder', function($query)use($inputs){
                        $query->whereHas('saleUser', function($query)use($inputs){
                            $query->where('realname', 'like', '%'.$inputs['business'].'%');
                        });
                    });
                })
                ->with(['hasOrder' => function($query){
                    return $query->with(['hasCustomer' => function ($query){
                        $query->select(['id','customer_name']);
                    }])->select(['id','swd_id','customer_id','project_name','project_sale','project_business']);
                }]);
        $count = $where_query->count();
        $list = $where_query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }
}

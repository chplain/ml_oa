<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class BusinessOrderLink extends Model
{
	use SoftDeletes;
    //投放链接表
    protected $table = 'business_order_links';

    //关联投放链接
    public function hasProject()
    {
        return $this->hasOne('App\Models\BusinessProject', 'id', 'project_id');
    }

    //关联价格记录表
    public function hasPrice()
    {
        return $this->hasMany('App\Models\BusinessOrderPrice', 'link_id', 'id');
    }

    //获取数据
    public function getLinkList($inputs = [])
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->when(isset($inputs['order_id']) && is_numeric($inputs['order_id']), function($query) use ($inputs){
                    $query->where('order_id', $inputs['order_id']);
                })
                ->with(['hasProject'=>function ($query){
                    $query->select(['id','project_name']);
                }]);
        $count = $where_query->count();
        $list = $where_query->when(isset($inputs['export']), function ($query) use ($start, $length){
                    return $query->orderBy('id', 'asc');
                })
                ->when(!isset($inputs['export']), function ($query) use ($start, $length){
                    return $query->orderBy('id', 'desc')->skip($start)->take($length);
                })
                ->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //单条数据
    public function getLinkInfo($inputs = []){
        $query_where = $this->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs){
            $query->where('id', $inputs['id']);
        })
        ->with(['hasProject'=>function ($query){
            $query->select(['id','project_name']);
        }])
        ->with(['hasPrice'=>function ($query){
            $query->select(['id','link_id','user_id','pricing_manner','old_pricing_manner','old_market_price','market_price','remarks','start_time','end_time','created_at']);
        }]);
        return $query_where->first();
    }


    public function addLink($inputs = array()){
        if(empty($inputs)) return false;
        $link = new BusinessOrderLink;
        $price_log = new \App\Models\BusinessOrderPrice;
        $link_insert  = array();
        foreach ($inputs['links'] as $key => $value) {
            $insert_link = array();
            $insert_link['order_id'] = $inputs['order_id'];
            $insert_link['link_type'] = $value['link_type'];
            $insert_link['link_name'] = $value['link_name'];
            $insert_link['pc_link'] = $value['pc_link'] ?? '';
            $insert_link['wap_link'] = $value['wap_link'] ?? '';
            $insert_link['zi_link'] = $value['zi_link'] ?? '';
            $insert_link['remarks'] = $value['remarks'];
            $insert_link['project_id'] = $inputs['project_id'] ?? 0;
            $insert_link['if_use'] = $value['if_use'];
            $insert_link['pricing_manner'] = $value['pricing_manner'];
            $insert_link['market_price'] = serialize($value['market_price']);
            $insert_link['created_at'] = date('Y-m-d H:i:s');
            $insert_link['updated_at'] = date('Y-m-d H:i:s');
            $link_id = $link->insertGetId($insert_link); 
            if(!$link_id){
                return false;
            }
            $insert_price_log = array();
            $insert_price_log['order_id'] = $inputs['order_id'];
            $insert_price_log['old_pricing_manner'] = '';
            $insert_price_log['old_market_price'] = '';
            $insert_price_log['pricing_manner'] = $value['pricing_manner'];
            $insert_price_log['market_price'] = serialize($value['market_price']);
            $insert_price_log['remarks'] = '新增';
            $insert_price_log['link_id'] = $link_id;
            $insert_price_log['start_time'] = 0;
            $insert_price_log['end_time'] = 0;
            $insert_price_log['user_id'] = auth()->user()->id;
            $insert_price_log['notice_user_ids'] = '';
            $insert_price_log['created_at'] = date('Y-m-d H:i:s');
            $insert_price_log['updated_at'] = date('Y-m-d H:i:s');
            $res = $price_log->insert($insert_price_log);
            if(!$res) return false;
        }
        
        return true;
    }

    //根据多个链接id 判断多个链接是否存在冲突
    public function ifConflict($inputs){
        if(isset($inputs['link_ids']) && is_array($inputs['link_ids']) && !empty($inputs['link_ids']) && count($inputs['link_ids']) > 1){
            //分配多条链接的时候   CPA和CPS可以共存   CPC、CPD只能单独存在
            $link = new \App\Models\BusinessOrderLink;
            $link_arr = $link->whereIn('id', $inputs['link_ids'])->select(['id','project_id','pricing_manner','market_price'])->get();
            $link_tmp = [];
            foreach ($link_arr as $key => $value) {
                $link_tmp[] = $value->pricing_manner;
                if(!isset($inputs['pid']) && $value->project_id > 0){
                    //新增时
                    return ['code' => 0, 'message' => '链接id'.$value->id.'已被占用'];
                }
                if(isset($inputs['pid']) && $inputs['pid'] != $value->project_id && $value->project_id > 0){
                    //编辑时
                    return ['code' => 0, 'message' => '链接id'.$value->id.'已被占用'];
                }
            }
            if(in_array('CPA', $link_tmp) && in_array('CPC', $link_tmp)){
                return ['code' => 0, 'message' => 'CPA和CPC不能共存'];
            }
            if(in_array('CPA', $link_tmp) && in_array('CPD', $link_tmp)){
                return ['code' => 0, 'message' => 'CPA和CPD不能共存'];
            }
            if(in_array('CPS', $link_tmp) && in_array('CPC', $link_tmp)){
                return ['code' => 0, 'message' => 'CPS和CPC不能共存'];
            }
            if(in_array('CPS', $link_tmp) && in_array('CPD', $link_tmp)){
                return ['code' => 0, 'message' => 'CPS和CPD不能共存'];
            }
            if(in_array('CPC', $link_tmp) && in_array('CPD', $link_tmp)){
                return ['code' => 0, 'message' => 'CPC和CPD不能共存'];
            }
            $tmp_type_price = [];
            foreach ($link_arr as $key => $value) {
                $market_price = unserialize($value->market_price);
                foreach ($market_price as $k => $p) {
                    if(in_array($k, ['CPC', 'CPD'])){
                        //一个项目里面多条cpc或者cpd链接   那么cpc链接/cpd链接的价格必须一致   cpa/cps的价格可以不一样
                        if(isset($tmp_type_price[$k]) && $tmp_type_price[$k] > 0 && $tmp_type_price[$k] != $p){
                            return ['code' => 0, 'message' => $k.'价格不一致，请选择其他链接'];
                        }
                    }
                    if($p > 0){
                        $tmp_type_price[$k] = $p;
                    }
                }
            }
            return ['code' => 1];
        }
        return ['code' => 0, 'message' => '请传入链接id集'];
    }
}

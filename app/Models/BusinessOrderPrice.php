<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOrderPrice extends Model
{
    // 商务单项目单价表
    protected $table = 'business_order_prices';

    // 单价字段反序列化
    // public function getMarketPriceAttribute($value)
    // {
    //     if (empty($value)) {
    //         return '';
    //     }
    //     return unserialize($value);
    // }

    //获取价格列表
    public function getDataList($inputs){        
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query =  $this->when(isset($inputs['order_id']) && is_numeric($inputs['order_id']), function ($query) use ($inputs){
                            $query->where('order_id', $inputs['order_id']);
                        })
                        ->when(isset($inputs['link_id']) && is_numeric($inputs['link_id']), function ($query) use ($inputs){
                            $query->where('link_id', $inputs['link_id']);
                        })
                        ->when(isset($inputs['start_date']) && !empty($inputs['start_date']) && isset($inputs['end_date']) && !empty($inputs['end_date']), function ($query) use ($inputs){
                            $query->where(function($query)use($inputs){
                                $query->where(function($query)use($inputs){
                                    $query->whereBetween('start_time', [$inputs['start_date'], $inputs['end_date']]);
                                })
                                ->orWhere(function($query)use($inputs){
                                    $query->whereBetween('end_time', [$inputs['start_date'], $inputs['end_date']]);
                                })
                                ->orWhere(function($query)use($inputs){
                                    $query->where('start_time', '=', 0)->where('end_time', '=', 0)->where('created_at', '<', date('Y-m-d H:i:s', $inputs['end_date']));
                                });
                            });
                            
                        });
        $count = $where_query->count();
        $list = $where_query->orderBy('created_at', 'desc')->when(!isset($inputs['all']),function($query)use($start,$length){
                    $query->skip($start)->take($length);
                })->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $table = 'ranks';

    /**
     * 保存职级数据
     */
    public function storeData($inputs = array())
    {
        $rank = new Rank;
        if (!empty($inputs['id']) && is_numeric($inputs['id'])) {
            $rank = $rank->where('id', $inputs['id'])->first();
        }
        $rank->name = $inputs['name'];
        $rank->status = !empty($inputs['status']) || is_numeric($inputs['status']) ||  $inputs['status'] > 0 ? $inputs['status'] : 0;
        return $rank->save();
    }
}

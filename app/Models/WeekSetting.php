<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13
 * Time: 15:09
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WeekSetting extends Model
{
    //
    protected $table = 'week_settings';

    //白名单
    protected $fillable = ['date', 'holiday_id', 'created_at', 'updated_at'];


    //批量更新
    public function updateReport($multiple_data = [])
    {
        DB::beginTransaction();
        try{
            $this->where('year',$multiple_data[0]['year'])->where('month',$multiple_data[0]['month'])->delete();
            $this->insert($multiple_data);
            DB::commit();
        }catch (\Illuminate\Database\QueryException $e){
            DB::rollback();
            return false;
        }
        return true;
    }
}
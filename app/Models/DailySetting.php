<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DailySetting extends Model
{
    protected $table = 'daily_settings';

    protected $fillable = ['user_id'];

    // 日报类型
    public function dailyTypes()
    {
        return $this->hasMany(DailyType::class, 'daily_setting_id', 'id');
    }

    /**
     * 保存日报配置
     * @param $inputs
     * @return array
     */
    public function storeSettings($inputs)
    {
        $response = ['code' => 1, 'message' => '操作成功'];
        try {
            DB::transaction(function () use ($inputs) {
                $daily_setting = new DailySetting;
                $daily_setting->user_id = auth()->id();
                $daily_setting->save();
                $daily_types = $inputs['daily_types'] ?? [];
                foreach ($daily_types as $key => $val) {
                    $daily_type = DailyType::create([
                        'daily_setting_id' => $daily_setting->id,
                        'daily_type_name' => $val['daily_type_name'],
                        'if_relation' => $val['if_relation'] ?? 0,
                        'status' => $val['status'] ?? 0
                    ]);
                }
            }, 5);
            systemLog('日报管理','保存日报设置');
        } catch (\Exception $e) {
            $response = ['code' => 0, 'message' => $e->getCode() . ' ' . $e->getMessage()];
        }
        return $response;
    }
}

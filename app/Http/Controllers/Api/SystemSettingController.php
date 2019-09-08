<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SystemSettingController extends Controller
{

    /*
    * 系统设置
    * @author qinjintian
    * @date 2018-11-13
    */
    public function setting()
    {
        $inputs = \request()->all();
        $request_type = empty($inputs['request_type']) ? '' : $inputs['request_type'];
        switch ($request_type) {
            case '1':
                // 系统设置页面数据
                $system_setting = new \App\Models\SystemSetting();
                $system_settings = $system_setting->get(['key', 'value', 'description']);
                return ['code' => 1, 'message' => '获取数据成功', 'data' => $system_settings];
                break;
            default:
                // 保存系统设置页面信息
                $param = \request()->input('param', []);
                try {
                    \DB::transaction(function () use ($param) {
                        foreach ($param as $key => $value) {
                            $system_setting = new \App\Models\SystemSetting();
                            $system_setting = $system_setting->where('key', $value['key'])->first();
                            $system_setting = $system_setting ? $system_setting : new \App\Models\SystemSetting();
                            $system_setting->key = $value['key'];
                            $system_setting->value = $value['value'];
                            $system_setting->description = $value['description'];
                            $system_setting->save();
                        }
                    });
                    systemLog('系统设置', '编辑了系统设置');
                    $response = ['code' => 1, 'message' => '操作成功'];
                } catch (\Exception $e) {
                    $response = ['code' => 0, 'message' => '操作失败：' . $e->getCode() . $e->getMessage()];
                }
                return $response;
        }
    }
}

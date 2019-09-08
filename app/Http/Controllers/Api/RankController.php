<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RankController extends Controller
{
    /**
     * 添加职级
     * @Author: qinjintian
     * @Date:   2018-09-14
     */
    public function store()
    {
        $inputs = request()->all();
        $rules = [
            'name' => 'required|min:1|max:30|unique:ranks',
        ];
        $attributes = [
            'name' => '职级名称',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $rank = new \App\Models\Rank;
        $result = $rank->storeData($inputs);
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('职级管理','添加了职级['.$inputs["name"].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return response()->json($response);
    }

    /**
     * 添加职级
     * @Author: qinjintian
     * @Date:   2018-09-14
     */
    public function update()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 'switch':
                // 启用/禁用职级
                $rank_id = $inputs['id'] ?? 0;
                $rank = new \App\Models\Rank;
                $rank_obj = $rank->where('id', $rank_id)->first();
                $rank_name = $rank_obj['name'];
                if (!$rank_obj) {
                    return response()->json(['code' => 0, 'message' => '该职级不存在，请检查']);
                }
                $rank_obj->status = !empty($inputs['status']) && is_numeric($inputs['status']) && $inputs['status'] > 0 ? 1 : 0;
                $save_result = $rank_obj->save();
                if($save_result){
                    $response = ['code' => 1, 'message' => '操作成功'];
                    systemLog('职级管理','启用/禁用职级['.$rank_name.']');
                }else{
                    $response = ['code' => 0, 'message' => '操作失败，请重试'];
                }
                return response()->json($response);
                break;
            default:
                // 更新职级
        }
    }

    /**
     * 职级列表
     * @Author: qinjintian
     * @Date:   2018-09-14
     */
    public function index()
    {
        $inputs = request()->all();
        $ranks = (new \App\Models\Rank)->all();
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $ranks]);
    }
}

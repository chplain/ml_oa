<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PositionController extends Controller
{
    /**
     * 岗位管理 -> 添加岗位
     * @Author: qinjintian
     * @Date:   2018-11-12
     */
    public function store()
    {
        $inputs = \request()->all();
        $rules = [
            'name' => 'required|max:32',
        ];
        $attributes = [
            'name' => '岗位名称',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $position_model = new \App\Models\Position();
        $result = $position_model->savePositions($inputs);
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('岗位管理','添加了岗位['.$inputs["name"].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 岗位管理 -> 修改岗位
     * @Author: qinjintian
     * @Date:   2018-11-12
     */
    public function update()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case '1':
                // 进入编辑岗位页面需要的表单数据
                if (!isset($inputs['id']) && empty($inputs['id'])) {
                    return ['code' => -1, 'message' => '岗位ID不能为空'];
                }
                $data = array();
                $position_model = new \App\Models\Position();
                $position = $position_model->where('id', $inputs['id'])->first();
                $data['position'] = $position;
                return ['code' => 1, 'message' => '获取数据成功', 'data' => $data];
                break;
            default:
                // 提交表单数据
                $position_model = new \App\Models\Position();
                $result = $position_model->savePositions($inputs);
                if($result){
                    $response = ['code' => 1, 'message' => '操作成功'];
                    systemLog('岗位管理','修改了岗位['.$inputs["name"].']');
                }else{
                    $response = ['code' => 0, 'message' => '操作失败，请重试'];
                }
                return $response;
        }
    }

    /**
     * 岗位管理 -> 岗位列表
     * @Author: qinjintian
     * @Date:   2018-11-12
     */
    public function index()
    {
        $inputs = \request()->all();
        $position_model = new \App\Models\Position();
        $resources = $position_model->queryPositionList($inputs);
        return ['code' => 1, 'message' => '获取数据成功', 'data' => $resources];
    }

    /**
     * 岗位管理 -> 岗位详情
     * @Author: qinjintian
     * @Date:   2018-11-12
     */
    public function show()
    {
        $position_id = \request()->input('id', '0');
        if (empty($position_id)) {
            return ['code' => -1, 'message' => '岗位ID参数错误，请检查'];
        }
        $position_model = new \App\Models\Position();
        $position = $position_model->detail($position_id);
        return ['code' => 1, 'message' => '获取数据成功', 'data' => $position];
    }

    /**
     * 岗位管理 -> 删除岗位
     * @Author: qinjintian
     * @Date:   2018-11-12
     */
    public function destroy()
    {
        $position_id = \request()->input('id', '0');
        if (empty($position_id)) {
            return ['code' => -1, 'message' => '岗位ID参数错误，请检查'];
        }
        $position_model = new \App\Models\Position();
        $user_model = new \App\Models\User();
        $user_count = $user_model->where('position_id', $position_id)->count();
        if ($user_count > 0) {
            return ['code' => 0, 'message' => '有' . $user_count . '个用户正在使用该岗位，请先解绑'];
        }
        $position = $position_model->where('id', $position_id)->first();
        $position_name = $position['name'];
        $result = $position->delete();
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('岗位管理','删除了岗位['.$position_name.']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }
}

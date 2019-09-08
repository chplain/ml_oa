<?php

namespace App\Http\Controllers\Api;

use App\Models\NoticeType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class NoticeTypeController extends Controller
{
    //添加公告类型
    public function store()
    {
        $inputs = \request()->all();
        $rules = [
            'name' => 'required|min:1|max:30|unique:notice_types',
            'is_using' => 'required|numeric|min:0',
        ];
        $attributes = [
            'name' => '公告类型名称',
            'is_using' => '是否启用',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $type = new NoticeType;
        $result = $type->create($inputs);
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('通知公告类型','添加通知公告类型['.$inputs["name"].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return response()->json($response);
    }

    //编辑类型状态
    public function edit(NoticeType $noticeType)
    {
        $inputs = \request()->input();
        $request_type = empty($inputs['request_type']) ? '' : $inputs['request_type'];
        switch ($request_type){
            case 'edit':
                //查询类型数据
                $datas = $noticeType->get();
                $response = $datas ? ['code'=> 1, 'message' => '数据获取成功', 'data' => $datas] : ['code'=> 0, 'message' => '数据获取失败,请重试'];
                return $response;
                break;
            default:
                //启用或禁用该公告类型
                $is_using = $noticeType->where('id',$inputs['id'])->value('is_using');
                $is_using = $is_using == 1 ? 0 : 1;
                $type = $noticeType->find($inputs['id']);
                $type->is_using = $is_using;
                $result = $type->save();
                if($result){
                    $response = ['code' => 1, 'message' => '操作成功'];
                    systemLog('通知公告类型','启/禁用通知公告类型['.$type["name"].']状态');
                }else{
                    $response = ['code' => 0, 'message' => '操作失败，请重试'];
                }
                return $response;
        }
    }
}

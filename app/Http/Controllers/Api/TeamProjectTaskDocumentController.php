<?php

namespace App\Http\Controllers\Api;

use App\Models\TeamProjectTaskDocument;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TeamProjectTaskDocumentController extends Controller
{
    //项目文档列表
    public function index()
    {
        $inputs = \request()->all();
        if(!isset($inputs['team_project_id'])){
            return response()->json(['code' => -1,'message' => '项目id不存在']);
        }
        $document_model = new TeamProjectTaskDocument;
        $datas = $document_model->queryTaskDocumentDatas($inputs);
        if($datas){
            return response()->json(['code' => 1,'message' => '获取成功','data'=>$datas]);
        }
        return response()->json(['code'=>0,'message'=>'获取数据失败，请重试']);
    }

    //删除项目文档
    public function delete()
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'文档id不存在,请输入']);
        }
        $result = TeamProjectTaskDocument::where('id',$inputs['id'])->delete();
        if($result){
            return response()->json(['code'=>1,'message'=>'操作成功']);
        }
        return response()->json(['code'=>0,'message'=>'操作失败，请重试']);
    }
}

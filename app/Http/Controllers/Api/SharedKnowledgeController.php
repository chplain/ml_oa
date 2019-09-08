<?php

namespace App\Http\Controllers\Api;

use App\Models\Dept;
use App\Models\Rank;
use App\Models\SharedKnowledge;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SharedKnowledgeController extends Controller
{
    /**
     * 共享知识库列表
     * @Author: renxianyong
     * @Date:   2019-04-18
     */
    public function index()
    {
        $inputs = \request()->all();
        $shared_knowledge = new SharedKnowledge;
        $datas = $shared_knowledge->queryKnowledgeDatas($inputs);
        if($datas){
            return response()->json(['code'=>1,'message'=>'获取成功','data'=>$datas]);
        }
        return response()->json(['code'=>0,'message'=>'获取失败，请重试']);
    }

    /**
     * 共享知识库新增
     * @Author: renxianyong
     * @Date:   2019-04-18
     */
    public function store()
    {
        $inputs = \request()->all();
        $rules = [
            'name' =>   'required|string|unique:shared_knowledges',
            'content'   =>  'required|string'
        ];

        $attributes = [
            'name' =>   '文件名',
            'content'   =>  '正文'
        ];

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $shared_knowledge = new SharedKnowledge;
        $result = $shared_knowledge->storeKnowledgeData($inputs);
        if($result){
            systemLog('文档管理', '新增了共享知识[' . $inputs['name'] . ']');
            return response()->json(['code'=>1,'message'=>'操作成功']);
        }
        return response()->json(['code'=>0,'message'=>'操作失败，请重试']);
    }

    /**
     * 共享知识库查看更新
     * @Author: renxianyong
     * @Date:   2019-04-18
     */
    public function update()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type){
            //获取编辑数据
            case 'data':
                if(!isset($inputs['id'])){
                    return response()->json(['code'=>-1,'message'=>'共享知识库id不能为空，请输入']);
                }
                $data = SharedKnowledge::where('id',$inputs['id'])->first();
                if($data){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
                }
                return response()->json(['code'=>0,'message'=>'获取失败，请重试']);
                break;
            //获取设置权限数据
            case 'permission_data':
                $datas['permission_type'] = [
                    ['id'=>1,'name'=>'允许'],
                    ['id'=>2,'name'=>'不允许']
                ];
                $datas['permission_range'] = [
                    ['id'=>1,'name'=>'用户'],
                    ['id'=>2,'name'=>'部门'],
                    ['id'=>3,'name'=>'职级']
                ];
                //获取所有用户数据
                $id = auth()->id();
                $datas['permission_user_data'] = User::where('id', '!=', $id)->get([ 'id','realname'])->toArray();
                //部门数据
                $datas['permission_dept_data'] = Dept::get([ 'id','name'])->toArray();
                //职级数据
                $datas['permission_rank_data'] = Rank::get([ 'id','name'])->toArray();
                return response()->json(['code'=>1,'message'=>'获取成功','data'=>$datas]);
                break;
            //设置权限
            case 'permission':
                return $this->setPermission($inputs);
                break;
            //编辑共享知识文件
            default :
                return $this->editKnowledge($inputs);
        }
    }

    /**
     * 共享知识库查看详情
     * @Author: renxianyong
     * @Date:   2019-04-18
     */
    public function detail()
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'共享知识库id不能为空，请输入']);
        }
        $data = SharedKnowledge::where('id',$inputs['id'])->first();

        if(!$data){
            return response()->json(['code'=>0,'message'=>'获取失败，请重试']);
        }
        $data = $data->toArray();
        $user_data = auth()->user()->toArray();
        if($data['permission_type'] == 0){//允许所有
            return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
        }
        if ($data['permission_type'] == 1){
            if($data['permission_range'] == 1){//允许指定的用户
                if(strpos($data['permission_ids'],(string)$user_data['id']) !== false){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
                }
                return response()->json(['code'=>0,'message'=>'您暂无权限查看此文档，请联系系统管理员']);
            }elseif ($data['permission_range'] == 2){//允许指定的部门
                if(strpos($data['permission_ids'],(string)$user_data['dept_id']) !== false){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
                }
                return response()->json(['code'=>0,'message'=>'您暂无权限查看此文档，请联系系统管理员']);
            }elseif ($data['permission_range'] == 3){//允许指定的职级
                if(strpos($data['permission_ids'],(string)$user_data['rank_id']) !== false){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
                }
                return response()->json(['code'=>0,'message'=>'您暂无权限查看此文档，请联系系统管理员']);
            }
        }elseif ($data['permission_type'] == 2){
            if($data['permission_range'] == 1){//不允许指定的用户
                if(strpos($data['permission_ids'],(string)$user_data['id']) === false){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
                }
                return response()->json(['code'=>0,'message'=>'您暂无权限查看此文档，请联系系统管理员']);
            }elseif ($data['permission_range'] == 2){//不允许指定的部门
                if(strpos($data['permission_ids'],(string)$user_data['dept_id']) === false){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
                }
                return response()->json(['code'=>0,'message'=>'您暂无权限查看此文档，请联系系统管理员']);
            }elseif ($data['permission_range'] == 3){//不允许指定的职级
                if(strpos($data['permission_ids'],(string)$user_data['rank_id']) === false){
                    return response()->json(['code'=>1,'message'=>'获取成功','data'=>$data]);
                }
                return response()->json(['code'=>0,'message'=>'您暂无权限查看此文档，请联系系统管理员']);
            }
        }
    }

    /**
     * 共享知识库删除
     * @Author: renxianyong
     * @Date:   2019-04-18
     */
    public function delete(SharedKnowledge $shared_knowledge)
    {
        $inputs = \request()->all();
        if(!isset($inputs['id'])){
            return response()->json(['code'=>-1,'message'=>'共享知识库id不能为空，请输入']);
        }
        $query = $shared_knowledge->where('id',$inputs['id']);
        $data = $query->first();
        $result = $query->delete();
        if($result){
            systemLog('文档管理', '删除了共享知识[' . $data['name'] . ']');
            return response()->json(['code'=>1,'message'=>'操作成功']);
        }
        return response()->json(['code'=>0,'message'=>'操作失败，请重试']);
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function setPermission(array $inputs)
    {
        $rules = [
            'id' => 'required|array',
            'permission_type' => 'required|integer',
            'permission_range' => 'required|integer',
            'permission_ids' => 'required|array'
        ];

        $attributes = [
            'id' => '共享知识库id',
            'permission_type' => '权限类型',
            'permission_range' => '权限范围',
            'permission_ids' => '权限数据'
        ];

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $shared_knowledge = new SharedKnowledge;
        $result = $shared_knowledge->updatePermission($inputs);
        if ($result) {
            systemLog('文档管理', '设置了共享知识[' . $result . ']的权限');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function editKnowledge(array $inputs)
    {
        $rules = [
            'id' => 'required|integer',
            'name' => 'required|string|unique:shared_knowledges,name,' . $inputs['id'],
            'content' => 'required|string',
        ];

        $attributes = [
            'id' => '知识共享id',
            'name' => '文件名',
            'content' => '正文',
        ];

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $shared_knowledge = new SharedKnowledge;
        $result = $shared_knowledge->storeKnowledgeData($inputs);
        if ($result) {
            systemLog('文档管理', '编辑了共享知识[' . $inputs['name'] . ']');
            return response()->json(['code' => 1, 'message' => '操作成功']);
        }
        return response()->json(['code' => 0, 'message' => '操作失败，请重试']);
    }
}

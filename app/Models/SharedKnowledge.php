<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SharedKnowledge extends Model
{
    protected $table = 'shared_knowledges';

    //共享知识库列表
    public function queryKnowledgeDatas($inputs)
    {
        $start_date = $inputs['start_date'] ?? '';
        $end_date = $inputs['end_date'] ?? '';
        $keyword_name = $inputs['keyword_name'] ?? '';//文件名
        $keyword_uploader = $inputs['keyword_uploader'] ?? '';//发布人名字
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $query = $this->when($start_date && $end_date, function($query) use($start_date,$end_date){
            $query->whereBetween('created_at',[$start_date.' 00:00:00',$end_date.' 23:59:59']);
        })->when($keyword_name,function($query) use($keyword_name){
            $query->where('name','like','%'.$keyword_name.'%');
        })->when($keyword_uploader,function($query)use($keyword_uploader){
            $query->where('uploader','like','%'.$keyword_uploader.'%');
        });
        $datas['count'] = $query->count();
        $datas['datas'] = $query->orderBy('created_at','DESC')->skip($start)->take($length)->get();
        return $datas;
    }

    //新增或修改共享知识库
    public function storeKnowledgeData($inputs)
    {
        try{
            if(isset($inputs['id']) && !empty($inputs['id'])){
                //修改知识共享
                $shared_knowledge = $this->where('id',$inputs['id'])->first();
            }else{
                //新增知识共享
                $shared_knowledge = $this;
                $uploader_data = auth()->user();//上传人信息
                $uploader = $uploader_data['realname'];
                $uploader_id = $uploader_data['id'];
            }
            $shared_knowledge->name = $inputs['name'];
            $shared_knowledge->content = $inputs['content'];
            if(isset($uploader) && !empty($uploader)){
                $shared_knowledge->uploader_id = $uploader_id;
                $shared_knowledge->uploader = $uploader;
            }
            $shared_knowledge->save();
        }catch (\Exception $e){
            return false;
        }
        return true;
    }

    //设置查看权限
    public function updatePermission($inputs)
    {
        DB::beginTransaction();
        try{
            foreach($inputs['id'] as $id){
                $shared_knowledge = $this->where('id',$id)->first();
                $name = $shared_knowledge['name'];
                $inputs['permission_ids'][] = $shared_knowledge->uploader_id;
                $shared_knowledge->permission_type = $inputs['permission_type'];
                $shared_knowledge->permission_range = $inputs['permission_range'];
                $shared_knowledge->permission_ids = implode(',',$inputs['permission_ids']);
                $shared_knowledge->save();
            }
            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            return false;
        }
        return $name;
    }
}

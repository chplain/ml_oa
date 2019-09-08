<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\DocBlock\Tags\Author;

class Notice extends Model
{
    protected $table = 'notices';

    protected $fillable = ['title', 'notice_type_id', 'user_ids', 'user_id', 'accessory', 'content'];

    protected $dates = ['deleted_at'];

    //获取公告类型
    public function noticeType()
    {
        return $this->belongsTo('App\Models\NoticeType', 'notice_type_id', 'id');
    }

    //获取用户信息
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    public function getAccessoryAttribute($value)
    {
        if (empty($value)) {
            return '';
        }
        $value = unserialize($value);
        $datas = [];
        foreach ($value as $key => $data) {
            if(isset($data['realPath'])){
                $datas[$key]['realPath'] = asset($data['realPath']);
            }else{
                $datas[$key]['realPath'] = $data ? asset($data) : '';
            }
            if(isset($data['original_name'])){
                $datas[$key]['original_name'] = $data['original_name'];
            }else{
                $data = explode('/',$data);
                $datas[$key]['original_name'] = $data ? end($data) : '';
            }
            if(isset($data['ext'])) {
                $datas[$key]['ext'] = $data['ext'];
            }else{
                $datas[$key]['ext'] = $datas[$key]['original_name'] ? explode('.',$datas[$key]['original_name'])[1] : '';
            }
        }
        return $datas;

    }

    /**
     * 我发布的列表
     */
    public function queryMyList($inputs = [])
    {
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $keyword = $inputs['keyword'] ?? '';
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';
        $user_id = auth()->id();
        $querys = $this->with(['user' => function ($query) {
            $query->select(['id', 'realname']);
        }, 'noticeType' => function ($query) {
            $query->select(['id', 'name']);
        }])->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->whereBetween('created_at', [$start_time . ' 00:00:00', $end_time . ' 23:59:59']);
        })->when($keyword, function ($query) use ($keyword) {
            $query->where('title', 'like', '%' . $keyword . '%');
        })->where('user_id', $user_id);
        $records_filtered = $querys->count();
        $datalist = $querys
            ->orderBy('created_at', 'DESC')
            ->skip($start)
            ->take($length)
            ->get(['id','title','notice_type_id','user_id','status','created_at']);
        return ['records_filtered' => $records_filtered, 'datalist' => $datalist];
    }

    /**
     * 关于我的列表
     */
    public function queryAboutMyList($inputs = [])
    {
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $keyword = $inputs['keyword'] ?? '';
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';
        $user_id = auth()->id();
        $querys = $this->with(['user' => function ($query) {
            $query->select(['id', 'realname']);
        },'noticeType' => function ($query) {
            $query->select(['id', 'name']);
        }])->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->whereBetween('created_at', [$start_time . ' 00:00:00', $end_time . ' 23:59:59']);
        })->when($keyword, function ($query) use ($keyword) {
            $query->where('title', 'like', '%' . $keyword . '%');
        })->where(function ($query) use ($user_id) {
            $query->whereRaw("FIND_IN_SET($user_id,user_ids)");
        })->where('status',1);
        $records_filtered = $querys->count();
        $datalist = $querys->orderBy('created_at', 'DESC')
            ->skip($start)
            ->take($length)
            ->get(['id','title','notice_type_id','user_id','created_at']);
        return ['records_filtered' => $records_filtered, 'datalist' => $datalist];
    }

    /**
     * 查看详情
     */
    public function queryDetail($id)
    {
        $data = $this->with(['user' => function ($query) {
            $query->select(['id', 'realname']);
        },'noticeType' => function ($query) {
            $query->select(['id', 'name']);
        }])->where('id',$id)->first();
        $user_ids = explode(',',$data['user_ids']);
        $user = new \App\Models\User;
        $user_names = $user->whereIn('id',$user_ids)->pluck('realname');
        $data['notifier'] = $user_names;
        return $data;
    }
}

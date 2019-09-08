<?php

namespace App\Http\Controllers\Api;

use App\Models\Notice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NoticeController extends Controller
{
    /**
     * 发布通知公告
     * @Author: renxianyong
     * @Date:   2019-01-28
     */
    public function store()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        $inputs['user_id'] = auth()->id();
        $inputs['status'] = isset($inputs['status']) ? $inputs['status'] : 1;
        switch ($request_type) {
            case 'create':
                // 加载添加表单时候需要的表单数据
                $NoticeTypes = (new \App\Models\NoticeType)->where('is_using',1)->get(); // 通知公告类型
                $users = (new \App\Models\User)->get(['id','realname'])->toArray();
                $all = ['id'=>'all','realname'=>'所有人'];
                array_unshift($users,$all);//插入全选数据
                return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => ['NoticeTypes' => $NoticeTypes, 'users' => $users]]);
                break;
            case 'upload':
                // 上传附件
                return $this->uploadAccessory($inputs);
                break;
            default:
                // 发布通知公告操作
                return $this->addNotice($inputs);
        }
    }

    /**
     * 我发布的公告列表
     * @Author: renxianyong
     * @Date:   2019-01-28
     */
    public function myNoticeList()
    {
        $inputs = request()->all();
        $resources = (new Notice)->queryMyList($inputs);
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $resources]);
    }

    /**
     * 我发布的公告-撤回
     * @Author: renxianyong
     * @Date:   2019-01-28
     */
    public function recall()
    {
        $id = \request()->input('id', 0);
        if ($id <= 0) {
            return ['code' => 0, 'message' => '不存在这条数据记录'];
        }
        $notice = Notice::find($id);
        if($notice['status'] == 0){
            return ['code' => 0, 'message' => '该公告已撤回'];
        }elseif (strtotime($notice['created_at']) < (time()-172800)){
            return ['code' => 0, 'message' => '发布超过48小时候，不能撤回'];
        }
        $notice->status = 0;
        $result = $notice->save();
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('通知公告','撤回通知公告['.$notice["title"].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 我发布的公告-详情
     * @Author: renxianyong
     * @Date:   2019-01-28
     */
    public function myNoticeDetail()
    {
        $id = \request()->input('id', 0);
        $user_id = (new Notice)->where('id',$id)->value('user_id');
        if ($id <= 0) {
            return ['code' => -1, 'message' => '不存在这条数据记录'];
        }elseif ($user_id != (auth()->id())){
            return ['code' => -1, 'message' => '该公告不是您发布的'];
        }
        $notice = new Notice;
        $data = $notice->queryDetail($id);
        return $data ? ['code' => 1, 'message' => 'success', 'data' => $data] : ['code' => 0, 'message' => '查看失败，请重试'];
    }

    /**
     * 关于我的公告-详情
     * @Author: renxianyong
     * @Date:   2019-01-30
     */
    public function aboutMyDetail()
    {
        $id = \request()->input('id', 0);
        $user_ids = (new Notice)->where('id',$id)->value('user_ids');
        $user_ids = explode(',',$user_ids);
        if ($id <= 0) {
            return ['code' => -1, 'message' => '不存在这条数据记录'];
        }elseif (!in_array((auth()->id()),$user_ids)){
            return ['code' => -1, 'message' => '该公告不是关于您的'];
        }
        $notice = new Notice;
        $data = $notice->queryDetail($id);
        return $data ? ['code' => 1, 'message' => 'success', 'data' => $data] : ['code' => 0, 'message' => '查看失败，请重试'];
    }

    /**
     * 关于我的公告列表
     * @Author: renxianyong
     * @Date:   2019-01-28
     */
    public function aboutMyList()
    {
        $inputs = request()->all();
        $resources = (new Notice)->queryAboutMyList($inputs);
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $resources]);
    }

    /**
     * 上传通知公告附件
     * @param $inputs
     * @return array
     */
    private function uploadAccessory($inputs): array
    {
        $rules = ['accessory' => 'file|max:10240'];
        $attributes = ['accessory' => '文件'];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        if (request()->isMethod('post')) {
            $file = request()->file('accessory');
            if(!$file){
                return ['code' => -1, 'message' => '请上传附件'];
            }
            if ($file->isValid()) {
                $original_name = $file->getClientOriginalName(); // 文件原名
                $ext = $file->getClientOriginalExtension();  // 扩展名
            }
            $directory = storage_path('app/public/uploads/accessory');
            if (!\File::isDirectory($directory)) {
                \File::makeDirectory($directory, $mode = 0777, $recursive = true); // 递归创建目录
            }
            $filename = date('YmdHis') . uniqid() .'.' . $ext;
            $fileinfo = $file->move($directory, $filename);
            $datas['realPath'] = '/storage/uploads/accessory/'.$filename;
            $datas['original_name'] = $original_name;
            $datas['ext'] = $ext;
            return ['code' => 1, 'message' => '上传成功', 'data' => $datas];
        } else {
            return ['code' => 0, 'message' => '非法操作'];
        }
    }

    //发布通知公告验证
    private function addNotice(array $inputs){
        $rules = [
            'title' => 'required|min:1|max:50',
            'notice_type_id' => 'required|integer|min:1',
            'user_id' => 'required|integer|min:1',
            'user_ids' => 'required|array',
            'content' => 'required',
            'accessory' => 'nullable|array'
        ];
        $attributes = [
            'title' => '公告标题',
            'notice_type_id' => '公告类型',
            'user_id' => '发布人',
            'user_ids' => '需要通知的用户',
            'content' => '正文',
            'accessory' => '附件'
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }

        if (in_array('all',$inputs['user_ids'])) {
            $user = new \App\Models\User;
            $user_ids = $user->where('status',1)->pluck('id');
            $user_ids_new = [];
            foreach($user_ids as $val){
                $user_ids_new[] = $val;
            }
        }
        $notice = new Notice();
        $user_ids = isset($user_ids_new) ? $user_ids_new : $inputs['user_ids'];
        $inputs['user_ids'] = implode(',',$user_ids);
        $inputs['accessory'] = isset($inputs['accessory']) ? serialize($inputs['accessory']) : '';
        $result = $notice->create($inputs);
        //添加到消息提醒表
        $title = '通知公告';
        $message = '收到一条有关您的通知公告，请查看';
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            $operator_id = auth()->id();
            $url = 'notice-list-my-notice';
            $api_route_url = 'notice/store';
            $api_params = ['start' => 0, 'length' => 10];
            addNotice($user_ids,$title,$message,'',$operator_id,$url,$api_route_url,$api_params);
            systemLog('通知公告','发布通知公告['.$inputs["title"].']');
        }
            $response = isset($response) ? $response : ['code' => 0, 'message' => '操作失败，请重试'];
        return $response;
    }
}

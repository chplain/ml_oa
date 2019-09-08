<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{

	protected $table = 'system_logs';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['content'];

    /*
    * 获取列表
    * @Author: molin
    * @Date:   2018-08-22
    */
    public function getDataList($inputs = array()){
    	$records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        if(isset($inputs['keywords']) && !empty($inputs['keywords'])){
            $user = new \App\Models\User;
            $user_list = $user->where('username', 'like', '%'.$inputs['keywords'].'%')->orWhere('realname', 'like', '%'.$inputs['keywords'].'%')->select(['id'])->get();
            $inputs['user_ids'] = array();
            foreach ($user_list as $key => $value) {
                $inputs['user_ids'][] = $value->id;
            }
        }
    	$where_query = $this->when(!empty($inputs['user_id']), function($query) use ($inputs){
                        return $query->where('user_id', $inputs['user_id']);
                    })
                    ->when(!empty($inputs['type']), function($query) use ($inputs){
                        return $query->where('type', $inputs['type']);
                    })
                    ->when(isset($inputs['menu_id']) && is_numeric($inputs['menu_id']), function($query) use ($inputs){
                        return $query->where('menu_id', $inputs['menu_id']);
                    })
                    ->when(!empty($inputs['start_time']) && !empty($inputs['end_time']), function($query) use ($inputs){
                        return $query->whereBetween('created_at', array($inputs['start_time'],$inputs['end_time']));
                    })
                    ->when(!empty($inputs['keywords']), function($query) use ($inputs){
                        return $query->whereIn('user_id', $inputs['user_ids']);
                    });
        $count = $where_query->count();
        if(isset($inputs['export_check']) || isset($inputs['export_all'])){
            $list = array();
            if( isset($inputs['export_check']) && isset($inputs['log_ids']) && !empty($inputs['log_ids']) ){
                //选中导出
                $log_ids = $inputs['log_ids'];//需要导出的id
                if(is_array($log_ids) && !empty($log_ids)){
                    $list = $where_query->when(!empty($log_ids), function($query) use ($log_ids){
                                return $query->whereIn('id', $log_ids);
                            })->orderBy('id', 'desc')->get();
                }
            }
            if(isset($inputs['export_all'])){
                //导出全部
                $list = $where_query->orderBy('id', 'desc')->get();
            }
        }else{
        	$list = $where_query->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();
        }
        
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }
    

    /*
    * 添加操作日志
    */
    public function addLog($insert){
        $this->type = $insert['type']; //1其它  2登录
        $this->user_id = $insert['user_id'];
        $this->username = $insert['username'];
        $this->login_ip = $insert['login_ip'];
        $this->login_addr = $insert['login_addr'];
        $this->operate_path = $insert['operate_path'];
        $this->content = $insert['content'];
    	return $this->save();
    }
}

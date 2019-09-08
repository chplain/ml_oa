<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;

class GroupFile extends Model
{
    //文件管理
    protected $table = 'group_files';

    public $file_path = DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR;

    //部门文件管理
    public function getFileList($inputs){
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query =  $this->when(isset($inputs['name']) && !empty($inputs['name']), function ($query) use ($inputs){
                            $query->where('name', 'like', '%'.$inputs['name'].'%');
                        })
        				->when(isset($inputs['uploader']) && !empty($inputs['uploader']), function ($query) use ($inputs){
                            $query->where('uploader', 'like', '%'.$inputs['uploader'].'%');
                        })
                        ->when(isset($inputs['parent_id']) && is_numeric($inputs['parent_id']), function ($query) use ($inputs){
                            $query->where('parent_id', $inputs['parent_id']);
                        })
                        ->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs){
                            $query->whereBetween('created_at', [$inputs['start_time'].' 00:00:00', $inputs['end_time'].' 23:59:59']);
                        });
        $count = $where_query->count();
        $list = $where_query->orderBy('level', 'asc')->orderBy('is_file', 'asc')->get();
        return ['records_filtered' => $count, 'datalist' => $list];
    }

    //创建文件夹
    public function createFolder($inputs){
    	$file = new GroupFile;
    	if(isset($inputs['parent_id']) && is_numeric($inputs['parent_id'])){
    		$parent_id = $inputs['parent_id'];
    	}else{
    		$parent_id = 0;//默认根目录
    	}
    	if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$file_info = $file->where('id', $inputs['id'])->first();
    		$file_info->name = $inputs['name'];
    		return $file_info->save();
    	}
    	$root_path = storage_path() . $this->file_path;//根目录
    	if(!is_dir($root_path)){
    		$is_ok = mkdir($root_path, 0777, true);
            if(!$is_ok) return false;//创建失败
    	}
    	
    	if($parent_id > 0){
    		$file_info = $file->where('id', $parent_id)->select(['id', 'path', 'level'])->first();
    		$level = $file_info->level + 1;
    		$path = $root_path.$file_info->path.DIRECTORY_SEPARATOR;
    	}else{
    		$level = 0;
    		$path = $root_path;
    	}
    	$randDirName = $this->randDirName($path);
    	if(!is_dir($randDirName['storage_path'])){
    		$is_ok = mkdir($randDirName['storage_path'], 0777, true);
            if(!$is_ok) return false;//创建失败
    	}
    	
    	$file->parent_id = $parent_id;
    	$file->name = $inputs['name'];
    	$file->suffix_name = '';
    	$file->dept_id = auth()->user()->dept_id;
    	$file->size = 0;
    	$file->path = $parent_id == 0 ? $randDirName['dir'] : $file_info->path.DIRECTORY_SEPARATOR.$randDirName['dir'];//文件夹路径
    	$file->uploader_id = auth()->user()->id;//上传人
    	$file->uploader = auth()->user()->realname;//上传人
    	$file->password = '';//密码
    	$file->restrict_type = 0;//限制类型 
    	$file->group_type = 0;//限制组别
    	$file->group_ids = '';//群体id
    	$file->is_file = 0;//0文件夹  1文件
    	$file->level = $level;//目录层级
    	return $file->save();

    }

    public function randDirName($storage_path){
    	$str = 'abcdefghijklmnopqrstuvwxyz23456789';
    	$dir = substr(str_shuffle($str),0,6);
    	$storage_path = $storage_path.$dir;
    	while (is_dir($storage_path)) {
    		$dir = substr(str_shuffle($str),0,6);
    		$storage_path = $storage_path.$dir;
    	}
    	return ['storage_path'=>$storage_path, 'dir'=>$dir];
    }

    public function getPathUrl($all_folder = [], $parent_id = 0, $path_data = []){
    	if($parent_id == 0 || empty($all_folder)){
    		return [['parent_id'=>0, 'name'=> '根目录/']];
    	}
    	foreach ($all_folder as $value) {
    		if ($value->id == $parent_id) {
    			$tmp = [];
    			$tmp['parent_id'] = $value->id;
    			$tmp['name'] = $value->name.'/';
    			array_unshift($path_data,$tmp);
    			if($value->parent_id == 0){
    				$root = ['parent_id'=>0, 'name'=> '根目录/'];
    				array_unshift($path_data, $root);
    				break;
    			}
    			$path_data = $this->getPathUrl($all_folder, $value->parent_id, $path_data);
    		}
    	}
    	return $path_data;
    }


}

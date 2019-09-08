<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GroupFileController extends Controller
{
    /** 
    *  文件管理
    *  @author molin
    *	@date 2019-04-19
    */
	public function index(){
		//文件夹排前面
		$inputs = request()->all();
		if(isset($inputs['request_type']) && $inputs['request_type'] == 'check'){
			if(!isset($inputs['id']) || !is_numeric($inputs['id'])){
	    		return response()->json(['code' => -1, 'message' => '缺少参数id']);
	    	}
	    	$inputs['if_password'] = $inputs['if_password'] ?? 0;
	    	$user_info = auth()->user();
	    	$file = new \App\Models\GroupFile;
	    	$file_info = $file->where('id', $inputs['id'])->first();
	    	//验证权限
	    	if($file_info->restrict_type == 1){
	    		if(($file_info->group_type == 1 && in_array($user_info->id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 2 && in_array($user_info->dept_id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 3 && in_array($user_info->rank_id, explode(',', $file_info->group_ids)))){
	    			
	    		}else{
	    			return response()->json(['code' => 0, 'message' => '无权限']);
	    		}
	    	}else if($file_info->restrict_type == 2){
	    		if(($file_info->group_type == 1 && in_array($user_info->id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 2 && in_array($user_info->dept_id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 3 && in_array($user_info->rank_id, explode(',', $file_info->group_ids)))){
	    			return response()->json(['code' => 0, 'message' => '无权限']);
	    		}
	    	}
	    	if(empty($file_info->password)){
	    		return response()->json(['code' => 1, 'message' => '验证通过']);
	    	}
	    	//验证密码
	    	if(!empty($file_info->password) && $inputs['if_password'] == 0){
	    		return response()->json(['code' => 1, 'message' => '请输入密码', 'data' => ['if_password' => 1]]);
	    	}
	    	if($inputs['if_password'] == 1 && !isset($inputs['password'])){
	    		return response()->json(['code' => 0, 'message' => '缺少参数password']);
	    	}
	    	if(!empty($file_info->password) && $inputs['if_password'] == 1){
	    		if($file_info->password != md5($inputs['password'])){
	    			return response()->json(['code' => 0, 'message' => '密码不正确']);
	    		}
	    		return response()->json(['code' => 1, 'message' => '验证通过']);
	    	}
		}

		$file = new \App\Models\GroupFile;
		$inputs['parent_id'] = $inputs['parent_id'] ?? 0;
		$inputs['if_password'] = $inputs['if_password'] ?? 0;
		$user_info = auth()->user();
		if($inputs['parent_id'] > 0){
			//判断是否有权限和密码
			$folder_info = $file->where('id', $inputs['parent_id'])->first();
			if(empty($folder_info)){
				return response()->json(['code' => 0, 'message' => '文件夹不存在']);
			}
			if(!empty($folder_info->password) && $inputs['if_password'] == 0){
				return response()->json(['code' => 1, 'message' => '请输入密码', 'data' => ['if_password'=>1]]);
			}
			if($inputs['if_password'] == 1 && !isset($inputs['password'])){
	    		return response()->json(['code' => 0, 'message' => '缺少参数password']);
	    	}
	    	if(!empty($folder_info->password) && $inputs['if_password'] == 1){
	    		if($folder_info->password != md5($inputs['password'])){
	    			return response()->json(['code' => 0, 'message' => '密码不正确']);
	    		}
	    		if($folder_info->restrict_type == 1){
	    			//允许范围
	    			if(($folder_info->group_type == 1 && in_array($user_info->id, explode(',', $folder_info->group_ids))) || ($folder_info->group_type == 2 && in_array($user_info->dept_id, explode(',', $folder_info->group_ids))) || ($folder_info->group_type == 3 && in_array($user_info->rank_id, explode(',', $folder_info->group_ids)))){

	    			}else{
	    				return response()->json(['code' => 0, 'message' => '您没有权限覆查看该文件夹!!']);
	    			}
	    		}else if($folder_info->restrict_type == 2){
	    			//不允许范围
	    			if(($folder_info->group_type == 1 && in_array($user_info->id, explode(',', $folder_info->group_ids))) || ($folder_info->group_type == 2 && in_array($user_info->dept_id, explode(',', $folder_info->group_ids))) || ($folder_info->group_type == 3 && in_array($user_info->rank_id, explode(',', $folder_info->group_ids)))){
		                return response()->json(['code' => 0, 'message' => '您没有权限覆查看该文件夹!!']);
	    			}
	    		}
	    	}

		}
		$data = $file->getFileList($inputs);
		$files = [];
		foreach ($data['datalist'] as $key => $value) {
			$tmp = [];
			$tmp['id'] = $value->id;
			$tmp['name'] = $value->name;
			$tmp['uploader'] = $value->is_file ? $value->uploader : '--';
			$tmp['created_at'] = $value->is_file ? $value->created_at->format('Y-m-d H:i:s') : '--';
			$tmp['has_password'] = 0;//是否加密
			if(!empty($value->password)){
				$tmp['has_password'] = 1;
			}
			$tmp['has_restrict'] = 0;//是否有限制
			if($value->restrict_type != 0){
				$tmp['has_restrict'] = 1;
			}
			if($value->is_file){
				if(($value->size / 1024 / 1024 / 1024 / 1024) > 1){
					$tmp['size'] = sprintf('%.2f', ($value->size / 1024 / 1024 / 1024 / 1024)) .'TB';
				}else if(($value->size / 1024 / 1024 / 1024) > 1){
					$tmp['size'] = sprintf('%.2f', ($value->size / 1024 / 1024 / 1024)) .'GB';
				}else if(($value->size / 1024 / 1024) > 1){
					$tmp['size'] = sprintf('%.2f', ($value->size / 1024 / 1024)) .'MB';
				}else if(($value->size / 1024) > 1){
					$tmp['size'] = sprintf('%.2f', ($value->size / 1024)) .'KB';
				}else{
					$tmp['size'] = sprintf('%.2f', $value->size) .'B';
				}
			}else{
				$tmp['size'] = '--';
			}
			$tmp['if_restrict'] = 0;//当前用户是否有权限 0 没有 1 有
			if($value->restrict_type == 1){
				if(($value->group_type == 1 && in_array($user_info->id, explode(',', $value->group_ids))) || ($value->group_type == 2 && in_array($user_info->dept_id, explode(',', $value->group_ids))) || ($value->group_type == 3 && in_array($user_info->rank_id, explode(',', $value->group_ids)))){
					$tmp['if_restrict'] = 1;//有权限
    			}
			}else if($value->restrict_type == 2){
				if(($value->group_type == 1 && in_array($user_info->id, explode(',', $value->group_ids))) || ($value->group_type == 2 && in_array($user_info->dept_id, explode(',', $value->group_ids))) || ($value->group_type == 3 && in_array($user_info->rank_id, explode(',', $value->group_ids)))){
					$tmp['if_restrict'] = 0;
    			}else{
    				$tmp['if_restrict'] = 1;//有权限
    			}
			}else{
				$tmp['if_restrict'] = 1;//有权限
			}
			$tmp['is_file'] = $value->is_file;
			$files[] = $tmp;
		}
		$data['datalist'] = $files;
		$all_folder = $file->where('is_file',0)->select(['id','parent_id','name'])->get();
		$path_url = $file->getPathUrl($all_folder, $inputs['parent_id']);
		$data['path_url'] = $path_url;
		$data['parent_id'] = $inputs['parent_id'];
		return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
	}

	/** 
    *  创建文件夹
    *  @author molin
    *	@date 2019-04-19
    */
    public function store(){
    	$inputs = request()->all();
    	$inputs['parent_id'] = $inputs['parent_id'] ?? 0;
    	$rules = [
            'parent_id' => 'required|integer',
            'name' => 'required|max:30|unique:group_files,name,NULL,id,parent_id,'.$inputs['parent_id']
        ];
        $attributes = [
            'parent_id' => '父级id',
            'name' => '文件夹名称'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
    	$file = new \App\Models\GroupFile;
        if(isset($inputs['id']) && is_numeric($inputs['id'])){
    		$if_exist_name = $file->where('id', '<>', $inputs['id'])->where('name', $inputs['name'])->first();
    		if(!empty($if_exist_name)){
    			return response()->json(['code' => 0, 'message' => '名称已存在!!']);
    		}
    	}
    	$result = $file->createFolder($inputs);
    	if($result){
    		return response()->json(['code' => 1, 'message' => '操作成功']);
    	}
    	return response()->json(['code' => 0, 'message' => '操作失败']);
    }

    /** 
    *  上传文件
    *  @author molin
    *	@date 2019-04-22
    */
    public function upload(){
    	$inputs = request()->all();
    	if(!isset($inputs['parent_id']) || !is_numeric($inputs['parent_id'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数parent_id']);
    	}
    	if(!isset($inputs['replace_id']) || !in_array($inputs['replace_id'], [0, 1, 2])){
    		return response()->json(['code' => -1, 'message' => '缺少参数replace_id']);
    	}
    	if(!isset($inputs['if_password']) || !is_numeric($inputs['if_password'])){
    		return response()->json(['code' => -1, 'message' => '缺少参数if_password']);
    	}
    	$file = new \App\Models\GroupFile;
    	$file_info = $file->where('id', $inputs['parent_id'])->where('is_file', 0)->first();//查出文件夹
    	if(empty($file_info)){
    		return response()->json(['code' => 0, 'message' => '文件夹不存在']);
    	}
    	$cur_path = storage_path().$file->file_path.$file_info->path;//当前文件夹路径
    	if(!is_dir($cur_path)){
    		return response()->json(['code' => 0, 'message' => '文件夹不存在']);
    	}
    	$upload_file = \request()->file('file');
        if(!empty($upload_file)){
        	$if_exist_file = $file->where('parent_id', $inputs['parent_id'])->where('name', $upload_file->getClientOriginalName())->where('is_file', 1)->first();//查出当前文件夹下是否有相同文件名文件
        	if(!empty($if_exist_file) && $inputs['replace_id'] == 0){
	    		$replace = [['id' => 1, 'name' => '替换原有文件'],['id' => 2, 'name' => '增加后缀上传']];
	    		return response()->json(['code' => 1, 'message' => '路径下有相同名称的文件-'.$if_exist_file->name.'，请选择操作', 'data' => ['replace'=> $replace]]);
	    	}
        	$file->parent_id = $inputs['parent_id'];
        	$file->dept_id = auth()->user()->dept_id;
            $file->suffix_name = $upload_file->getClientOriginalExtension();//扩展名
        	if($inputs['replace_id'] == 0){
        		// 新上传
        		$file->name = $upload_file->getClientOriginalName(); // 文件原名
        	}else if($inputs['replace_id'] == 1){
        		//替换
        		$file->name = $upload_file->getClientOriginalName(); // 文件原名
        		if(!empty($if_exist_file->password) && $inputs['if_password'] == 0){
		    		return response()->json(['code' => 1, 'message' => '请输入原来文件的密码', 'data'=>['if_password'=>1]]);//需要输入密码
		    	}
		    	if($inputs['if_password'] == 1 && !isset($inputs['password'])){
		    		return response()->json(['code' => 0, 'message' => '缺少参数password']);
		    	}
		    	$user_info = auth()->user();
		    	if(!empty($if_exist_file->password) && $inputs['if_password'] == 1){
		    		if($if_exist_file->password != md5($inputs['password'])){
		    			return response()->json(['code' => 0, 'message' => '密码不正确,请重新输入']);
		    		}
		    		//输入密码下载  查看是否有权限
		    		if($if_exist_file->restrict_type == 1){
		    			//允许范围
		    			if(($if_exist_file->group_type == 1 && in_array($user_info->id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 2 && in_array($user_info->dept_id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 3 && in_array($user_info->rank_id, explode(',', $if_exist_file->group_ids)))){

		    			}else{
		    				return response()->json(['code' => 0, 'message' => '您没有权限覆盖该文件!!']);
		    			}
		    		}else if($if_exist_file->restrict_type == 2){
		    			//不允许范围
		    			if(($if_exist_file->group_type == 1 && in_array($user_info->id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 2 && in_array($user_info->dept_id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 3 && in_array($user_info->rank_id, explode(',', $if_exist_file->group_ids)))){
		    				//指定用户
			                return response()->json(['code' => 0, 'message' => '您没有权限覆盖该文件!!']);
		    			}
		    		}
		    	}else{
		    		//不需要密码
		    		if($if_exist_file->restrict_type == 1){
		    			//允许范围
		    			if(($if_exist_file->group_type == 1 && in_array($user_info->id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 2 && in_array($user_info->dept_id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 3 && in_array($user_info->rank_id, explode(',', $if_exist_file->group_ids)))){
		    				//指定用户
		    			}else{
		    				return response()->json(['code' => 0, 'message' => '您没有权限覆盖该文件!!']);
		    			}
		    		}else if($if_exist_file->restrict_type == 2){
		    			//不允许范围
		    			if(($if_exist_file->group_type == 1 && in_array($user_info->id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 2 && in_array($user_info->dept_id, explode(',', $if_exist_file->group_ids))) || ($if_exist_file->group_type == 3 && in_array($user_info->rank_id, explode(',', $if_exist_file->group_ids)))){
		    				//指定用户
			                return response()->json(['code' => 0, 'message' => '您没有权限覆盖该文件!!']);
		    			}
		    		}
		    		
		    	}

        	}else if($inputs['replace_id'] == 2){
        		//已存在相同文件名的时候 保留 (将新上传的重命名)
        		$name = $upload_file->getClientOriginalName(); // 文件原名
        		$file_name = str_replace('.'.$file->suffix_name, '-副本', $name);//重命名
        		$file_num = $file->where('parent_id', $inputs['parent_id'])->where('name', 'like', $file_name.'%')->where('is_file', 1)->count();
        		$file->name = $file_name.'('.($file_num+1).')'.'.'.$file->suffix_name;
        	}
            $file->size = $upload_file->getSize();//大小
            $fileName = date('YmdHis').uniqid().'.'.$file->suffix_name;//重命名
            $file->path = $file_info->path.DIRECTORY_SEPARATOR.$fileName;//文件路径
        	$file->uploader_id = auth()->user()->id;
        	$file->uploader = auth()->user()->realname;
        	if($inputs['replace_id'] == 1){
        		//替换时  保留密码  权限
        		$file->password = isset($inputs['password']) && $inputs['password'] ? md5($inputs['password']) : $if_exist_file->password;
        		$file->restrict_type = $if_exist_file->restrict_type;
	        	$file->group_type = $if_exist_file->group_type;
	        	$file->group_ids = $if_exist_file->group_ids;
	        	$file->level = $if_exist_file->level;//层级
        	}else{
        		$file->password = isset($inputs['password']) && $inputs['password'] ? md5($inputs['password']) : '';
	        	$file->restrict_type = 0;
	        	$file->group_type = 0;
	        	$file->group_ids = '';
	        	$file->level = $file_info->level + 1;//层级
        	}
        	
        	$file->is_file = 1;
            $is_ok = $upload_file->move($cur_path, $fileName);
            if($is_ok){
            	//文件上传成功  插入数据
            	$result = $file->save();
            	if($result){
            		if($inputs['replace_id'] == 1 && !empty($if_exist_file)){
		        		//删除记录
		        		$res = $file->where('id', $if_exist_file->id)->delete();
		        		if($res){
		        			//删除文件
		        			@unlink(storage_path().$file->file_path.$if_exist_file->path);
		        		}
		        	}
            		return response()->json(['code' => 1, 'message' => '上传成功']);
            	}
            }

        }else{
            return response()->json(['code' => -1, 'message' => '没有检测到上传文件']);
        }
    }

    /** 
    *  移动
    *  @author molin
    *	@date 2019-04-23
    */
    public function move(){
    	$inputs = request()->all();
    	$file = new \App\Models\GroupFile;
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
    		//移动
    		$rules = [
	            'ids' => 'required|array',
	            'parent_id' => 'required|integer'
	        ];
	        $attributes = [
	            'ids' => 'id集',
	            'parent_id' => '目标文件夹id'
	        ];
	    	$validator = validator($inputs, $rules, [], $attributes);
	        if ($validator->fails()) {
	            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
	        }
	        $folder_info = $file->where('id', $inputs['parent_id'])->where('is_file', 0)->first();
	        if(empty($folder_info)){	
	        	return response()->json(['code' => 0, 'message' => '文件夹不存在']);
	        }
	        $update = [];
	        $update['parent_id'] = $inputs['parent_id'];
	        $update['level'] = $folder_info->level + 1;
	        $result = $file->whereIn('id', $inputs['ids'])->update($update);
	        if($result){
	        	return response()->json(['code' => 1, 'message' => '操作成功']);
	        }
	        return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
    	$data = [];
    	$folder_list = getTree($file->where('is_file', 0)->select(['id', 'name', 'parent_id', 'level'])->get(),0, 'level');//所有文件夹
    	$data['folder_list'] =  $this->getFolder($folder_list);
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    public function getFolder($arr, $return=[]){
    	foreach ($arr as $key => $value) {
    		$tmp = [];
    		$fix = '';
    		for($i = 0; $i < $value->level; $i++){
    			$fix .= '|-';
    		}
			$tmp['id'] = $value->id;
			$tmp['name'] = $fix.$value->name;
			$return[] = $tmp;
    		if(isset($value['children']) && !empty($value['children'])){
    			$return = $this->getFolder($value['children'], $return);
    		}
    	}
    	return $return;
    }

    /** 
    *  设置权限
    *  @author molin
    *	@date 2019-04-22
    */
    public function permission(){
    	$inputs = request()->all();
    	if(isset($inputs['request_type']) && $inputs['request_type'] == 'save'){
    		$rules = [
	            'ids' => 'required|array',
	            'restrict_type' => 'required|integer',
	            'group_type' => 'required|integer',
	            'group_ids' => 'required|array'
	        ];
	        $attributes = [
	            'ids' => 'id集',
	            'restrict_type' => '限制类型',
	            'group_type' => '限制对象',
	            'group_ids' => '对象集'
	        ];
	    	$validator = validator($inputs, $rules, [], $attributes);
	        if ($validator->fails()) {
	            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
	        }
	    	$update = [];
	    	$update['restrict_type'] = $inputs['restrict_type'];
	    	$update['group_type'] = $inputs['group_type'];
	    	$update['group_ids'] = implode(',', $inputs['group_ids']);

	    	$file = new \App\Models\GroupFile;
	    	$result = $file->whereIn('id', $inputs['ids'])->update($update);
	    	if($result){
	    		return response()->json(['code' => 1, 'message' => '操作成功']);
	    	}
	    	return response()->json(['code' => 0, 'message' => '操作失败']);
    	}
    	$data = [];
    	$data['restrict_type'] = [['id'=> 1, 'name' => '允许'], ['id' => 2, 'name' => '不允许']];
    	$data['group_type'] = [['id'=> 1, 'name' => '用户'], ['id' => 2, 'name' => '部门'], ['id'=> 3, 'name' => '职级']];
    	$data['user_list'] = (new \App\Models\User)->where('status', 1)->select(['id', 'realname'])->get();
    	$data['dept_list'] = (new \App\Models\Dept)->where('status', 1)->select(['id', 'name'])->get();
    	$data['rank_list'] = (new \App\Models\Rank)->where('status', 1)->select(['id', 'name'])->get();
    	return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);

    }

    /** 
    *  加密
    *  @author molin
    *	@date 2019-04-22
    */
    public function encry(){
    	$inputs = request()->all();
		$rules = [
            'ids' => 'required|array',
            'password' => 'required'
        ];
        $attributes = [
            'ids' => 'id集',
            'password' => '密码'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
    	$update = [];
    	$update['password'] = md5($inputs['password']);

    	$file = new \App\Models\GroupFile;
    	$result = $file->whereIn('id', $inputs['ids'])->update($update);
    	if($result){
    		return response()->json(['code' => 1, 'message' => '操作成功']);
    	}
    	return response()->json(['code' => 0, 'message' => '操作失败']);

    }


    /** 
    *  下载
    *  @author molin
    *	@date 2019-04-22
    */
    public function download(){
    	$inputs = request()->all();
		$rules = [
            'content' => 'required|array'
        ];
        $attributes = [
            'content' => '下载集'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $ids = $id_pass = [];
        foreach ($inputs['content'] as $key => $value) {
        	$value['password'] = is_null($value['password']) ? '' : $value['password'];
        	if(!isset($value['id']) || !is_numeric($value['id'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数id']);
        	}
        	if(!isset($value['password'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数password']);
        	}
        	$ids[] = $value['id'];
        	$id_pass[$value['id']] = $value['password'];
        }
    	$file = new \App\Models\GroupFile;
    	$user_info = auth()->user();

    	if(count($ids) == 1){
    		//单个下载  不压缩 降低损耗
    		$file_info = $file->whereIn('id', $ids)->first();
	    	if(empty($file_info)){
	    		return response()->json(['code' => 0, 'message' => '数据不存在']);
	    	}
	    	if(!$file_info->is_file){
	    		return response()->json(['code' => 0, 'message' => '无效文件']);
	    	}
	    	if($file_info->restrict_type == 1){
	    		//允许的人
	    		if(($file_info->group_type == 1 && in_array($user_info->id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 2 && in_array($user_info->dept_id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 3 && in_array($user_info->rank_id, explode(',', $file_info->group_ids)))){

	    		}else{
	    			return response()->json(['code' => 0, 'message' => '您没有权限下载']);
	    		}
	    	}else if($file_info->restrict_type == 1){
	    		//不允许的人
	    		if(($file_info->group_type == 1 && in_array($user_info->id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 2 && in_array($user_info->dept_id, explode(',', $file_info->group_ids))) || ($file_info->group_type == 3 && in_array($user_info->rank_id, explode(',', $file_info->group_ids)))){
	    			return response()->json(['code' => 0, 'message' => '您没有权限下载']);
	    		}
	    	}
	    	if(!empty($file_info->password)){
	    		if($file_info->password != md5($id_pass[$file_info->id])){
	    			return response()->json(['code' => 0, 'message' => '密码不正确']);
	    		}
	    	}
	    	//下载
	    	$data = [];
	    	$data['filename'] = $file_info->name;//原名
            $data['filepath'] = 'storage/uploads/files/' . $file_info->path;//下载链接
	        return response()->json(['code' => 1, 'message' => '下载成功', 'data' => $data]);
    	}else if(count($ids) > 1){
    		//多个下载  先打包 再下载  最大100mb
    		$total_size = $file->whereIn('id', $ids)->sum('size');
    		$total_size = sprintf('%.2f', $total_size / 1024 / 1024);
    		if($total_size > 100){
    			return response()->json(['code' => 0, 'message' => '目前只允许100MB以内文件']);
    		}
    		$file_list = $file->whereIn('id', $ids)->where('is_file', 1)->get();
    		if(count($file_list) < 1){
    			return response()->json(['code' => 0, 'message' => '没有数据可下载']);
    		}
    		$file_data = $permission_data =[];
    		foreach ($file_list as $key => $value) {
    			if(!empty($value->password)){
    				if($value->password != md5($id_pass[$value->id])){
    					//密码不正确 跳过该文件
    					continue;
    				}
    				//密码正确  继续检验权限
    			}
    			if($value->restrict_type == 1){
		    		//允许的人
		    		if(($value->group_type == 1 && in_array($user_info->id, explode(',', $value->group_ids))) || ($value->group_type == 2 && in_array($user_info->dept_id, explode(',', $value->group_ids))) || ($value->group_type == 3 && in_array($user_info->rank_id, explode(',', $value->group_ids)))){
		    			$file_data[] = $value;
		    		}else{
		    			$permission_data[] = $value->name;//没有权限下载的文件
		    		}
		    	}else if($value->restrict_type == 1){
		    		//不允许的人
		    		if(($value->group_type == 1 && in_array($user_info->id, explode(',', $value->group_ids))) || ($value->group_type == 2 && in_array($user_info->dept_id, explode(',', $value->group_ids))) || ($value->group_type == 3 && in_array($user_info->rank_id, explode(',', $value->group_ids)))){
		    			$permission_data[] = $value->name;//没有权限下载的文件
		    		}else{
		    			$file_data[] = $value;
		    		}
		    	}else{
		    		$file_data[] = $value;
		    	}
    		}
    		if(empty($file_data)){
    			$message = '没有符合下载条件的文件';
    			if(!empty($permission_data)) $message = '以下文件无权限下载:'.implode(',', $permission_data);
    			return response()->json(['code' => 0, 'message' => $message]);
    		}
    		//打包
    		$zip = new \ZipArchive();
			$invoice_path = storage_path().DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR .'invoice';
			if(!is_dir($invoice_path)){
				$is_ok = @mkdir($invoice_path, 0777, true);
				if(!$is_ok) return response()->json(['code' => 0, 'message' => '创建文件夹失败']);
			}
			$all_file = scandir($invoice_path);//文件夹下所有文件
			foreach ($all_file as $key => $value) {
			    if(strpos($value, 'sdoa_') !== false){
			        $time = substr(str_replace('.zip', '', $value), 5);
			        if(time()-86400 > $time){
			            //删除超过一天时间的压缩包
			            @unlink($invoice_path.DIRECTORY_SEPARATOR.$value);
			        }
			    }
			}
			$zip_name = 'sdoa_'.time().'.zip';
			$zip_file =  $invoice_path.DIRECTORY_SEPARATOR.$zip_name;
			$root_path = storage_path().$file->file_path;
			if($zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)){
				$filename = $zip_name;
				foreach ($file_data as $key => $value) {
					$zip->addFile($root_path.$value->path, $value->name);
					$fix = '.'.$value->suffix_name;
					$filename = str_replace($fix, '', $value->name).'等.zip';
				}
				$zip->close();
				if(is_file($zip_file)){
					$data = [];
			    	$data['filename'] = $filename;//原名
		            $data['filepath'] = 'storage/invoice/' . $zip_name;//下载链接
			        return response()->json(['code' => 1, 'message' => '下载成功', 'data' => $data]);
				}
				return response()->json(['code' => 0, 'message' => '下载失败']);
			}else{
				return response()->json(['code' => 0, 'message' => '压缩文件创建失败,请联系开发人员']);
			}

    	}else{
    		return response()->json(['code' => -1, 'message' => '请选择要下载的文件']);
    	}


    }

    /** 
    *  删除
    *  @author molin
    *	@date 2019-04-22
    */
    public function delete(){
    	$inputs = request()->all();
		$rules = [
            'content' => 'required|array'
        ];
        $attributes = [
            'content' => '删除集'
        ];
    	$validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $ids = $id_pass = [];
        foreach ($inputs['content'] as $key => $value) {
        	$value['password'] = is_null($value['password']) ? '' : $value['password'];
        	if(!isset($value['id']) || !is_numeric($value['id'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数id']);
        	}
        	if(!isset($value['password'])){
        		return response()->json(['code' => -1, 'message' => '缺少参数password']);
        	}
        	$ids[] = $value['id'];
        	$id_pass[$value['id']] = $value['password'];
        }
    	$file = new \App\Models\GroupFile;
    	$user_info = auth()->user();

    	if(count($ids) > 0){
    		//删除
    		$if_has_child = $file->whereIn('parent_id', $ids)->first();
    		if(!empty($if_has_child)){
    			return response()->json(['code' => 0, 'message' => '所选文件夹包含子文件,请先删除子文件']);
    		}
    		$file_list = $file->whereIn('id', $ids)->get();
    		if(count($file_list) < 1){
    			return response()->json(['code' => 0, 'message' => '没有数据可删除']);
    		}

    		$file_data = $permission_data = [];
    		foreach ($file_list as $key => $value) {
    			if(!empty($value->password)){
    				if($value->password != md5($id_pass[$value->id])){
    					//密码不正确 跳过该文件
    					continue;
    				}
    				//密码正确  继续检验权限
    			}
    			if($value->restrict_type == 1){
		    		//允许的人
		    		if(($value->group_type == 1 && in_array($user_info->id, explode(',', $value->group_ids))) || ($value->group_type == 2 && in_array($user_info->dept_id, explode(',', $value->group_ids))) || ($value->group_type == 3 && in_array($user_info->rank_id, explode(',', $value->group_ids)))){
		    			$file_data[] = $value;
		    		}else{
		    			$permission_data[] = $value->name;//没有权限下载的文件
		    		}
		    	}else if($value->restrict_type == 1){
		    		//不允许的人
		    		if(($value->group_type == 1 && in_array($user_info->id, explode(',', $value->group_ids))) || ($value->group_type == 2 && in_array($user_info->dept_id, explode(',', $value->group_ids))) || ($value->group_type == 3 && in_array($user_info->rank_id, explode(',', $value->group_ids)))){
		    			$permission_data[] = $value->name;//没有权限下载的文件
		    		}else{
		    			$file_data[] = $value;
		    		}
		    	}else{
		    		$file_data[] = $value;
		    	}
    		}
    		if(empty($file_data)){
    			$message = '没有符合条件的文件';
    			if(!empty($permission_data)) $message = '以下文件无权限:'.implode(',', $permission_data);
    			return response()->json(['code' => 0, 'message' => $message]);
    		}
    		//删除符合条件的文件夹或文件
    		$del_ids = $del_path = [];
			foreach ($file_data as $key => $value) {
				$del_ids[] = $value->id;
				$del_path[] = $value->path;
			}
			$result = $file->whereIn('id', $del_ids)->delete();
			if($result){
				foreach ($del_path as $value) {
					$path = storage_path().$file->file_path.$value;
					if(is_dir($path)){
						\File::deleteDirectory($path);
					}else{
						@unlink($path);
					}
				}
				return response()->json(['code' => 1, 'message' => '删除成功']);
			}
			return response()->json(['code' => 0, 'message' => '删除失败']);
    	}else{
    		return response()->json(['code' => -1, 'message' => '请选择要删除的文件夹或文件']);
    	}


    }


}

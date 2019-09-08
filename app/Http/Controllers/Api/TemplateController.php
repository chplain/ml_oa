<?php

namespace App\Http\Controllers\Api;

use App\Models\BusinessProject;
use App\Models\TemplateLibrary;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TemplateController extends Controller
{
    /**
     * 模板库列表
     * @Author: qinjintian
     * @Date:   2019-04-23
     */
    public function index()
    {
        $request_type = request()->input('request_type', '');
        switch ($request_type) {
            case 'surface':
                // 根目录列表
                return $this->surface();
                break;
            case 'inside':
                // 非根目录列表
                return $this->inside();
                break;
        }
    }

    /**
     * @return array
     */
    private function surface(): array
    {
        $params = request()->all();
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $querys = TemplateLibrary::where([
            ['type', '=', 0],
            ['parent_id', '=', 0]
        ])->selectRaw('`id`, `name`, LEFT(created_at, 10) as `created_date`')
            ->orderBy('created_date', 'DESC')
            ->withCount('folderFiles as template_count');

        $count = $querys->count();
        $templates = $querys->skip($start)->take($length)->get();
        $breadcrumbs = [['id' => 0, 'name' => '根目录']];
        return ['code' => 1, 'message' => '获取数据成功', 'data' => ['count' => $count, 'templates' => $templates, 'breadcrumbs' => $breadcrumbs]];
    }

    /**
     * @return array
     */
    private function inside(): array
    {
        $params = request()->all();
        $start = $params['start'] ?? 0;
        $length = $params['length'] ?? 10;
        $start_date = $params['start_date'] ?? '';
        $end_date = $params['end_date'] ?? '';
        if (!isset($params['id']) || intval($params['id']) < 0) {
            return ['code' => -1, 'message' => '文件夹id不能小于0，请检查'];
        }

        $template = TemplateLibrary::where('id', $params['id'])->first();
        if (!$template || $template->type != 0) {
            return ['code' => 0, 'message' => '这不是文件夹，请检查'];
        }

        $querys = TemplateLibrary::where('parent_id', $params['id'])
            ->when(isset($params['keyword_name']) && !empty($params['keyword_name']), function ($query) use ($params) {
                $query->where('name', $params['keyword_name']);
            })->when(isset($params['keyword_uploader']) && !empty($params['keyword_uploader']), function ($query) use ($params) {
                $query->where('uploader', $params['keyword_uploader']);
            })->when(isset($params['keyword_designer']) && !empty($params['keyword_designer']), function ($query) use ($params) {
                $query->where('designer', $params['keyword_designer']);
            })->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            })->withCount('folderFiles as template_count');

        $count = $querys->count();
        $templates = $querys->skip($start)->take($length)->get();

        $breadcrumbs = TemplateLibrary::breadcrumb($template->id);
        return ['code' => 1, 'message' => '获取数据成功', 'data' => ['count' => $count, 'templates' => $templates, 'breadcrumbs' => $breadcrumbs]];
    }

    /**
     * 重命名
     * @Author: qinjintian
     * @Date:   2019-04-24
     */
    public function rename()
    {
        $params = request()->all();
        $rules = [
            'id' => 'required|integer|min:1',
            'name' => 'required|min:1|max:32',
        ];

        $attributes = [
            'id' => 'ID',
            'name' => '名字',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $template = TemplateLibrary::where('id', $params['id'])->first();
        if (!$template) {
            return ['code' => 0, 'message' => '数据库资源不存在，请检查'];
        }

        if (!file_exists(public_path($template->path))) {
            return ['code' => 0, 'message' => '服务器资源不存在，请检查'];
        }

        $template->name = $params['name'];
        $res = $template->save();
        if ($res) {
            systemLog('模板库', '修改了模板[' . $params["name"] . ']');
            return ['code' => 1, 'message' => '修改名字成功'];
        }
        return ['code' => 0, 'message' => '修改名字失败，请重试'];
    }

    /**
     * 删除
     * @Author: qinjintian
     * @Date:   2019-04-24
     */
    public function delete()
    {
        $id = request()->post('id');
        if (!$id) {
            return ['code' => -1, 'message' => 'ID不能为空，请检查'];
        }

        $template = TemplateLibrary::where('id', $id)->first();
        if (!$template) {
            return ['code' => 0, 'message' => '资源不存在，请检查'];
        }

        $template_ids = [(int)$id];
        if ($template->type == 0) {
            $my_folder_count = TemplateLibrary::where([
                ['parent_id', '=', $template->id],
                ['type', '=', 0]
            ])->count();

            if ($my_folder_count > 0) {
                $template_ids = array_unique(array_merge($template_ids, getTreeById(TemplateLibrary::all(['id', 'parent_id']), $id)));
            }
        }

        $real_path = public_path($template->path);
        if (file_exists($real_path)) {
            $rets = \File::deleteDirectory($real_path);
        }

        $res = $template->destroy($template_ids);
        if ($res) {
            systemLog('模板库', '删除了[' . $template["name"] . ']');
            return ['code' => 1, 'message' => '删除成功'];
        }
        return ['code' => 0, 'message' => '删除失败，请重试'];
    }

    /**
     * 编辑
     * @Author: qinjintian
     * @Date:   2019-04-24
     */
    public function update()
    {
        $request_type = request()->input('request_type', '');
        switch ($request_type) {
            case 'load_edit_form_data':
                // 加载编辑表单数据
                return $this->loadEditFormData();
                break;
            default:
                // 更新数据
                return $this->edit();
        }
    }

    /**
     * @param $datas
     * @return array
     */
    private function loadEditFormData(): array
    {
        $id = request()->post('id');
        if (!$id) {
            return ['code' => -1, 'message' => 'ID不能为空，请检查'];
        }

        $template = TemplateLibrary::where('id', $id)->first();

        if (!$template) {
            return ['code' => 0, 'message' => '资源不存在，请检查'];
        }

        if ($template->type == 0) {
            return ['code' => 0, 'message' => '只能编辑文件，请检查'];
        }

        $template_data = [
            'id' => $template->id,
            'name' => $template->name,
            'designer_id' => $template->designer_id,
            'designer' => $template->designer,
            'uploader_id' => $template->uploader_id,
            'uploader' => $template->uploader,
            'project_id' => $template->project_id,
            'path' => asset($template->path),
        ];
        $datas['projects'] = BusinessProject::get(['id', 'project_name']);
        $datas['users'] = User::where('status', 1)->get(['id', 'realname']);
        $datas['template'] = $template_data;
        return ['code' => 1, 'message' => 'success', 'data' => $datas];
    }

    /**
     * @return array
     */
    private function edit(): array
    {
        $params = request()->all();
        $rules = [
            'template_id' => 'required|integer|min:1',
            'project_id' => 'required|integer|min:1',
            'designer_id' => 'required|integer|min:1',
            'name' => 'required|min:1',
        ];

        $attributes = [
            'template_id' => '模板ID',
            'project_id' => '项目ID',
            'designer_id' => '设计者ID',
            'name' => '模板名称',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $template = TemplateLibrary::where('id', $params['template_id'])->first();
        if (!$template) {
            return ['code' => 0, 'message' => '资源不存在，请检查'];
        }

        $name_is_exisit = TemplateLibrary::where([
            ['parent_id', '=', $template->parent_id],
            ['name', '=', $params['name']],
            ['id', '!=', $params['template_id']],
        ])->count();

        if ($name_is_exisit > 0) {
            return ['code' => 0, 'message' => '该文件名已经存在，请更换'];
        }

        $template->project_id = $params['project_id'];
        $template->designer_id = $params['designer_id'];
        $template->name = $params['name'];
        $res = $template->save();
        if ($res) {
            systemLog('模板库', '更新了[' . $params["name"] . ']');
            return ['code' => 1, 'message' => '操作成功'];
        }
        return ['code' => 0, 'message' => '操作失败，请重试'];
    }

    /**
     * 查看
     * @Author: qinjintian
     * @Date:   2019-04-25
     */
    public function show()
    {
        $id = request()->post('id', '0');
        $template = TemplateLibrary::where('id', $id)->first();
        if (!$template) {
            return ['code' => 0, 'message' => '模板不存在，请检查'];
        }

        if ($template->type == 0) {
            return ['code' => 0, 'message' => '这不是模板，请检查'];
        }

        $project_name = BusinessProject::where('id', $template->project_id)->value('project_name');
        $template_data['id'] = $template->id;
        $template_data['name'] = $template->name;
        $template_data['proejct_name'] = $project_name;
        $template_data['designer'] = $template->designer;
        $template_data['uploader'] = $template->uploader;
        $template_data['created_at'] = $template->created_at->toDateTimeString();
        $template_data['path'] = asset($template->path);
        return ['code' => 1, 'message' => 'success', 'data' => $template_data];
    }

    /**
     * 新建文件夹
     * @Author: qinjintian
     * @Date:   2019-04-25
     */
    public function createFolder()
    {
        $params = request()->all();
        $rules = [
            'parent_id' => 'required|integer|min:1',
            'name' => 'required|min:1',
        ];

        $attributes = [
            'parent_id' => '当前所处位置的文件夹id',
            'name' => '文件夹名',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $folder = TemplateLibrary::where('id', $params['parent_id'])->first();
        if (!$folder || $folder->type != 0) {
            return ['code' => 0, 'message' => '找不到这个文件夹，请检查'];
        }

        if (!\File::isDirectory(public_path($folder->path))) {
            return ['code' => 0, 'message' => '服务器上没有找到这个文件夹，请检查'];
        }

        $is_exists = TemplateLibrary::where([
            ['parent_id', '=', $params['parent_id']],
            ['name', '=', $params['name']]
        ])->count();

        if ($is_exists > 0) {
            return ['code' => 0, 'message' => '名字已经存在，请更换'];
        }

        $create_lack_path = $folder->path . '/' . date('YmdHis', time()) . uniqid();
        \File::makeDirectory(public_path($create_lack_path), $mode = 0777, $recursive = true); // 递归创建目录
        $res = TemplateLibrary::create([
            'name' => $params['name'], 'path' => $create_lack_path,
            'type' => 0, 'parent_id' => $params['parent_id']
        ]);

        if ($res) {
            systemLog('模板库', '创建了[' . $params["name"] . ']');
            return ['code' => 1, 'message' => '创建成功'];
        }
        return ['code' => 1, 'message' => '创建失败，请重试'];
    }

    /**
     * 上传文件
     * @Author: qinjintian
     * @Date:   2019-04-25
     */
    public function upload()
    {
        $request_type = request()->input('request_type', '');
        switch ($request_type) {
            case 'load_upload_form_data':
                // 表单数据
                $datas['projects'] = BusinessProject::get(['id', 'project_name']);
                $datas['designers'] = User::where('status', 1)->get(['id', 'realname']);
                return ['code' => 1, 'message' => '获取上传表单数据成功', 'data' => $datas];
                break;
            case 'single':
                // 上传文件
                return $this->singleFileUpload();
                break;
            default:
                // 提交表单
                return $this->uploadSubmit();
        }
    }

    /**
     * @return array
     */
    private function singleFileUpload(): array
    {
        $params = \request()->all();
        $rules = [
            'file' => 'required|image',
        ];

        $attributes = [
            'file' => '图片',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        if (request()->isMethod('post')) {
            $file = request()->file('file');
            if (!$file->isValid()) {
                return ['code' => 0, 'message' => '这不是有效文件，请检查'];
            }

            $original_name = $file->getClientOriginalName(); // 文件原名
            $entension = $file->getClientOriginalExtension(); // 上传文件的后缀
            $file_name = date('YmdHis', time()) . uniqid() . '.' . $entension;
            $res = $file->move(storage_path('app/public/temps'), $file_name);
            $access_path = asset('storage/temps') . '/' . $file_name;
            return ['code' => 1, 'message' => '上传成功', 'data' => [
                'original_name' => $original_name,
                'filename' => str_replace(strrchr($original_name, '.'),'', $original_name),
                'access_path' => $access_path,
                'relative_path' => '/storage/temps/' . $file_name,
            ]];
        } else {
            return ['code' => 0, 'message' => '非法操作'];
        }
    }

    /**
     * @return array
     */
    private function uploadSubmit(): array
    {
        $params = request()->all();
        $rules = [
            'parent_id' => 'required|integer|min:1',
            'alert' => 'required|',
            'datas' => 'required|array|min:0',
            'datas.*.filename' => 'required|min:1|max:32',
            'datas.*.project_id' => 'required|integer|min:1',
            'datas.*.designer_id' => 'required|integer|min:1',
            'datas.*.relative_path' => 'required',
        ];

        $attributes = [
            'parent_id' => '当前所处文件夹ID',
            'datas' => '文件信息，请传二维数组',
            'alert' => '上传提示',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $designer_ids = [];
        foreach ($params['datas'] as $key => $val) {
            array_push($designer_ids, $val['designer_id']);
        }

        $upload_path = TemplateLibrary::where('id', $params['parent_id'])->value('path');
        $designers = User::whereIn('id', $designer_ids)->get(['id', 'realname'])->keyBy('id')->toArray();
        $user = auth()->user();

        try {
            if ($params['alert'] == 0) {
                // 覆盖上传
                foreach ($params['datas'] as $key => $val) {
                    $extension = \File::extension($val['relative_path']);
                    $template = TemplateLibrary::templateIsExist(['parent_id' => $params['parent_id'], 'filename' =>  $val['filename'], 'extension' => $extension]);

                    $random_name = '/' . date('YmdHis', time()) . uniqid() . '.';
                    $file_relative_path = $upload_path . $random_name . $extension;
                    if ($template) {
                        $template->delete();
                        if (realpath(public_path($template->path))) {
                            \File::delete(realpath(public_path($template->path)));
                        }
                    }

                    $file_storage_path = realpath(public_path($upload_path)) . $random_name . $extension;
                    \File::move(realpath(public_path($val['relative_path'])), $file_storage_path);

                    TemplateLibrary::create([
                        'name' => $val['filename'], 'type' => 1,
                        'extension' => $extension, 'size' => formatSizeUnits(\File::size($file_storage_path)),
                        'path' => $file_relative_path,
                        'parent_id' => $params['parent_id'], 'uploader_id' => $user['id'], 'uploader' => $user['realname'],
                        'designer_id' => $val['designer_id'], 'designer' => $designers[$val['designer_id']]['realname'],
                        'project_id' => $val['project_id'],
                    ]);
                }
            } else {
                // 增加后缀上传
                foreach ($params['datas'] as $key => $val) {
                    $extension = \File::extension($val['relative_path']);
                    $template_names = TemplateLibrary::where([
                        ['parent_id', '=', $params['parent_id']],
                        ['name', 'like', $val['filename'] . '%'],
                        ['extension', '=', $extension],
                    ])->orderBy('id', 'DESC')->pluck('name')->toArray();

                    $filename = $val['filename'];
                    if (in_array($filename, $template_names)) {
                        if (count($template_names) > 1) {
                            $index = substr(mb_substr($template_names[0], (intval(mb_strripos($template_names[0],'(')) + 1)), 0, -1);
                            $filename .= '-副本(' . ++$index . ')';
                        } else {
                            $filename .= '-副本(1)';
                        }
                    }

                    $random_name = '/' . date('YmdHis', time()) . uniqid() . '.';
                    $file_relative_path = $upload_path . $random_name . $extension;
                    $file_storage_path = realpath(public_path($upload_path)) . $random_name . $extension;
                    \File::move(realpath(public_path($val['relative_path'])), $file_storage_path);

                    TemplateLibrary::create([
                        'name' => $filename, 'type' => 1,
                        'extension' => $extension, 'size' => formatSizeUnits(\File::size($file_storage_path)),
                        'path' => $file_relative_path,
                        'parent_id' => $params['parent_id'], 'uploader_id' => $user['id'], 'uploader' => $user['realname'],
                        'designer_id' => $val['designer_id'], 'designer' => $designers[$val['designer_id']]['realname'],
                        'project_id' => $val['project_id']
                    ]);
                }
            }
            systemLog('模板库', '上传了' . count($params['datas']) . '个文件');
            return ['code' => 1, 'message' => '上传成功'];
        } catch (\Exception $exception) {
            return ['code' => 0, 'message' => '上传失败，可能文件已经被转移'];
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\UiLibrary;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UiController extends Controller
{
    /**
     * UI库列表
     * @Author: qinjintian
     * @Date:   2019-04-26
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
        $querys = UiLibrary::where([
            ['type', '=', 0],
            ['parent_id', '=', 0]
        ])->selectRaw('`id`, `name`, LEFT(created_at, 10) as `created_date`, `type`')
            ->orderBy('created_date', 'DESC');

        $count = $querys->count();
        $uis = $querys->skip($start)->take($length)->get();
        $breadcrumbs = [['id' => 0, 'name' => '根目录']];
        return ['code' => 1, 'message' => '获取数据成功', 'data' => ['count' => $count, 'templates' => $uis, 'breadcrumbs' => $breadcrumbs]];
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

        $ui = UiLibrary::where('id', $params['id'])->first();
        if (!$ui || $ui->type != 0) {
            return ['code' => 0, 'message' => '这不是文件夹，请检查'];
        }

        $querys = UiLibrary::where('parent_id', $params['id'])
            ->when(isset($params['keyword_name']) && !empty($params['keyword_name']), function ($query) use ($params) {
                $query->where('name', $params['keyword_name']);
            })->when(isset($params['keyword_uploader']) && !empty($params['keyword_uploader']), function ($query) use ($params) {
                $query->where('uploader', $params['keyword_uploader']);
            })->when(isset($params['keyword_designer']) && !empty($params['keyword_designer']), function ($query) use ($params) {
                $query->where('designer', $params['keyword_designer']);
            })->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            });

        $count = $querys->count();
        $uis = $querys->skip($start)->take($length)->get();

        $breadcrumbs = UiLibrary::breadcrumb($ui->id);
        return ['code' => 1, 'message' => '获取数据成功', 'data' => ['count' => $count, 'uis' => $uis, 'breadcrumbs' => $breadcrumbs]];
    }

    /**
     * 新建文件夹
     * @Author: qinjintian
     * @Date:   2019-04-26
     */
    public function createFolder()
    {
        $params = \request()->all();
        $rules = [
            'parent_id' => 'required|integer|min:0',
            'name' => 'required|min:1|max:32',
        ];

        $attributes = [
            'parent_id' => '上级目录ID',
            'name' => '文件夹名',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        if ($params['parent_id'] == 0) {
            $folder_is_exists = UiLibrary::where([['parent_id', '=', '0'], ['name', '=', $params['name']]])->count();
            if ($folder_is_exists > 0) {
                return ['code' => 1, 'message' => '该文件夹名已经存在，请更换'];
            }

            $create_lack_path = '/storage/uploads/uis/' . date('YmdHis', time()) . uniqid();
            if (!\File::isDirectory(public_path($create_lack_path))) {
                $create_res = \File::makeDirectory(public_path($create_lack_path), $mode = 0777, $recursive = true); // 递归创建目录
                UiLibrary::create([
                    'name' => $params['name'], 'path' => $create_lack_path,
                    'type' => 0, 'parent_id' => 0
                ]);
                systemLog('UI库', '创建文件夹[' . $params["name"] . ']');
                return ['code' => 1, 'message' => '创建成功'];
            }
        } else {
            $folder = UiLibrary::where([
                ['id', '=', $params['parent_id']],
                ['type', '=', 0]
            ])->first();

            if (!$folder) {
                return ['code' => 0, 'message' => '文件夹不存在，请检查'];
            }

            $folder_name_is_exists = UiLibrary::where([
                ['parent_id', '=', $folder['id']],
                ['type', '=', 0],
                ['name', '=', $params['name']]
            ])->count();

            if ($folder_name_is_exists) {
                return ['code' => 0, 'message' => '该目录下已经存在此文件夹名，请更换'];
            }

            $create_lack_path = $folder->path . '/' . date('YmdHis', time()) . uniqid();
            if (!\File::isDirectory(public_path($create_lack_path))) {
                $create_res = \File::makeDirectory(public_path($create_lack_path), $mode = 0777, $recursive = true); // 递归创建目录
                UiLibrary::create([
                    'name' => $params['name'], 'path' => $create_lack_path,
                    'type' => 0, 'parent_id' => $folder->id
                ]);
                systemLog('UI库', '创建文件夹[' . $params["name"] . ']');
                return ['code' => 1, 'message' => '创建成功'];
            }
        }
    }

    /**
     * 重命名
     * @Author: qinjintian
     * @Date:   2019-04-26
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

        $ui = UiLibrary::where('id', $params['id'])->first();
        if (!$ui) {
            return ['code' => 0, 'message' => '数据库资源不存在，请检查'];
        }

        if (!file_exists(public_path($ui->path))) {
            return ['code' => 0, 'message' => '服务器资源不存在，请检查'];
        }

        $ui->name = $params['name'];
        $res = $ui->save();
        if ($res) {
            systemLog('UI库', '修改了UI图[' . $params["name"] . ']');
            return ['code' => 1, 'message' => '修改名字成功'];
        }
        return ['code' => 0, 'message' => '修改名字失败，请重试'];
    }

    /**
     * 删除
     * @Author: qinjintian
     * @Date:   2019-04-26
     */
    public function delete()
    {
        $id = request()->post('id');
        if (!$id) {
            return ['code' => -1, 'message' => 'ID不能为空，请检查'];
        }

        $ui = UiLibrary::where('id', $id)->first();
        if (!$ui) {
            return ['code' => 0, 'message' => '资源不存在，请检查'];
        }

        $ui_ids = [(int)$id];
        if ($ui->type == 0) {
            $my_folder_count = UiLibrary::where([
                ['parent_id', '=', $ui->id],
                ['type', '=', 0]
            ])->count();

            if ($my_folder_count > 0) {
                $ui_ids = array_unique(array_merge($ui_ids, getTreeById(UiLibrary::all(['id', 'parent_id']), $id)));
            }
        }

        $real_path = public_path($ui->path);
        if (file_exists($real_path)) {
            $rets = \File::deleteDirectory($real_path);
        }

        $res = $ui->destroy($ui_ids);
        if ($res) {
            systemLog('UI库', '删除了[' . $ui["name"] . ']');
            return ['code' => 1, 'message' => '删除成功'];
        }
        return ['code' => 0, 'message' => '删除失败，请重试'];
    }

    /**
     * 查看
     * @Author: qinjintian
     * @Date:   2019-04-26
     */
    public function show()
    {
        $id = request()->post('id', '0');
        $ui = UiLibrary::where('id', $id)->first();
        if (!$ui) {
            return ['code' => 0, 'message' => 'UI图不存在，请检查'];
        }

        if ($ui->type == 0) {
            return ['code' => 0, 'message' => '这不是UI图，请检查'];
        }

        $ui_data['id'] = $ui->id;
        $ui_data['name'] = $ui->name;
        $ui_data['designer'] = $ui->designer;
        $ui_data['created_at'] = $ui->created_at->toDateTimeString();
        $ui_data['path'] = asset($ui->path);
        return ['code' => 1, 'message' => 'success', 'data' => $ui_data];
    }

    /**
     * 上传文件
     * @Author: qinjintian
     * @Date:   2019-04-26
     */
    public function upload()
    {
        $request_type = request()->input('request_type', '');
        switch ($request_type) {
            case 'load_upload_form_data':
                // 表单数据
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
            'designer_id' => 'required|integer|min:1',
            'datas' => 'required|array|min:0',
            'datas.*.filename' => 'required|min:1|max:32',
            'datas.*.relative_path' => 'required',
        ];

        $attributes = [
            'parent_id' => '当前所处文件夹ID',
            'datas' => '文件信息，请传二维数组',
            'alert' => '上传提示',
            'designer_id' => '设计者ID',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $upload_path = UiLibrary::where('id', $params['parent_id'])->value('path');
        $designer = User::where('id', $params['designer_id'])->value('realname');
        $user = auth()->user();

        try {
            if ($params['alert'] == 0) {
                // 覆盖上传
                foreach ($params['datas'] as $key => $val) {
                    $extension = \File::extension($val['relative_path']);
                    $ui = UiLibrary::uiIsExist(['parent_id' => $params['parent_id'], 'filename' =>  $val['filename'], 'extension' => $extension]);

                    $random_name = '/' . date('YmdHis', time()) . uniqid() . '.';
                    $file_relative_path = $upload_path . $random_name . $extension;
                    if ($ui) {
                        $ui->delete();
                        if (realpath(public_path($ui->path))) {
                            \File::delete(realpath(public_path($ui->path)));
                        }
                    }

                    $file_storage_path = realpath(public_path($upload_path)) . $random_name . $extension;
                    \File::move(realpath(public_path($val['relative_path'])), $file_storage_path);

                    UiLibrary::create([
                        'name' => $val['filename'], 'type' => 1,
                        'extension' => $extension, 'size' => formatSizeUnits(\File::size($file_storage_path)),
                        'path' => $file_relative_path,
                        'parent_id' => $params['parent_id'], 'uploader_id' => $user['id'], 'uploader' => $user['realname'],
                        'designer_id' => $params['designer_id'], 'designer' => $designer,
                    ]);
                }
            } else {
                // 增加后缀上传
                foreach ($params['datas'] as $key => $val) {
                    $extension = \File::extension($val['relative_path']);
                    $ui_names = UiLibrary::where([
                        ['parent_id', '=', $params['parent_id']],
                        ['name', 'like', $val['filename'] . '%'],
                        ['extension', '=', $extension],
                    ])->orderBy('id', 'DESC')->pluck('name')->toArray();

                    $filename = $val['filename'];
                    if (in_array($filename, $ui_names)) {
                        if (count($ui_names) > 1) {
                            $index = substr(mb_substr($ui_names[0], (intval(mb_strripos($ui_names[0],'(')) + 1)), 0, -1);
                            $filename .= '-副本(' . ++$index . ')';
                        } else {
                            $filename .= '-副本(1)';
                        }
                    }

                    $random_name = '/' . date('YmdHis', time()) . uniqid() . '.';
                    $file_relative_path = $upload_path . $random_name . $extension;
                    $file_storage_path = realpath(public_path($upload_path)) . $random_name . $extension;
                    \File::copy(realpath(public_path($val['relative_path'])), $file_storage_path);

                    UiLibrary::create([
                        'name' => $filename, 'type' => 1,
                        'extension' => $extension, 'size' => formatSizeUnits(\File::size($file_storage_path)),
                        'path' => $file_relative_path,
                        'parent_id' => $params['parent_id'], 'uploader_id' => $user['id'], 'uploader' => $user['realname'],
                        'designer_id' => $params['designer_id'], 'designer' => $designer,
                    ]);
                }
            }
            systemLog('UI库', '上传了' . count($params["datas"]) . '个文件');
            return ['code' => 1, 'message' => '上传成功'];
        } catch (\Exception $exception) {
            return ['code' => 0, 'message' => '上传失败，可能文件已经被转移,错误信息:' . $exception->getMessage()];
        }
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

        $ui = UiLibrary::where('id', $id)->first();

        if (!$ui) {
            return ['code' => 0, 'message' => '资源不存在，请检查'];
        }

        if ($ui->type == 0) {
            return ['code' => 0, 'message' => '只能编辑文件，请检查'];
        }

        $ui_data = [
            'id' => $ui->id,
            'name' => $ui->name,
            'designer_id' => $ui->designer_id,
            'designer' => $ui->designer,
            'path' => asset($ui->path),
        ];
        $datas['users'] = User::where('status', 1)->get(['id', 'realname']);
        $datas['ui'] = $ui_data;
        return ['code' => 1, 'message' => 'success', 'data' => $datas];
    }

    /**
     * @return array
     */
    private function edit(): array
    {
        $params = request()->all();
        $rules = [
            'ui_id' => 'required|integer|min:1',
            'designer_id' => 'required|integer|min:1',
            'name' => 'required|min:1',
        ];

        $attributes = [
            'ui_id' => 'UI图ID',
            'designer_id' => '设计者ID',
            'name' => 'UI图名称',
        ];

        $validator = validator($params, $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $ui = UiLibrary::where('id', $params['ui_id'])->first();
        if (!$ui) {
            return ['code' => 0, 'message' => '资源不存在，请检查'];
        }

        $name_is_exisit = UiLibrary::where([
            ['parent_id', '=', $ui->parent_id],
            ['name', '=', $params['name']],
            ['id', '!=', $params['ui_id']],
            ])->count();

        if ($name_is_exisit > 0) {
            return ['code' => 0, 'message' => '该文件名已经存在，请更换'];
        }

        $ui->designer_id = $params['designer_id'];
        $ui->name = $params['name'];
        $res = $ui->save();
        if ($res) {
            systemLog('UI库', '更新了[' . $params["name"] . ']');
            return ['code' => 1, 'message' => '操作成功'];
        }
        return ['code' => 0, 'message' => '操作失败，请重试'];
    }
}

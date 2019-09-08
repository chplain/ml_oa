<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateLibrary extends Model
{
    //
    protected $table = 'template_librarys';

    protected $guarded = [];

    // 当前文件夹下的文件和文件夹
    public function folderFiles()
    {
        return $this->hasMany(TemplateLibrary::class, 'parent_id', 'id');
    }

    // 面包削导航
    public static function breadcrumb($template_id = 0)
    {
        $my_template = TemplateLibrary::where('id', $template_id)->first(['id', 'name', 'parent_id']);

        $pid = $my_template->parent_id ?? 0;

        $breadcrumbs = [];
        $root_node = ['id' => 0, 'name' => '根目录']; // 根节点

        if ($pid == 0) {
            array_push($breadcrumbs, $root_node);
            array_push($breadcrumbs, ['id' => $my_template->id, 'name' => $my_template->name]);
            return $breadcrumbs;
        }

        if ($my_template) {
            array_unshift($breadcrumbs, ['id' => $my_template->id, 'name' => $my_template->name]);
        }

        $stop = false;
        while (!$stop) {
            $template = TemplateLibrary::where([
                ['id', '=', $pid],
                ['type', '=', 0],
            ])->first(['id', 'name', 'parent_id']);

            if ($template->parent_id == 0) {
                $stop = true;
            }

            $pid = $template->parent_id;
            $nav = ['id' => $template->id, 'name' => $template->name];
            array_unshift($breadcrumbs, $nav);
        }

        array_unshift($breadcrumbs, $root_node);
        return $breadcrumbs;
    }

    // 创建当前月模板库文件夹
    public static function createTemplateFolder()
    {
        $current_month = date('Y-m', time());
        $current_month_template_count = TemplateLibrary::where([
            ['type', '=', 0],
            ['parent_id', '=', 0]
        ])->whereRaw("LEFT(`created_at`, 7) = '$current_month'")->count();

        if ($current_month_template_count < 1) {
            $create_lack_path = '/storage/uploads/templates/' . date('YmdHis', time()) . uniqid();
            if (!\File::isDirectory(public_path($create_lack_path))) {
                \File::makeDirectory(public_path($create_lack_path), $mode = 0777, $recursive = true); // 递归创建目录
            }
            TemplateLibrary::create([
                'name' => $current_month, 'path' => $create_lack_path,
                'type' => 0, 'parent_id' => 0
            ]);
        }
    }

    // 检查模板文件是否已经存在
    public static function templateIsExist($where = [])
    {
        return TemplateLibrary::where([
            ['parent_id', '=', $where['parent_id']],
            ['name', '=', $where['filename']],
            ['extension', '=', $where['extension']],
        ])->first();
    }

}

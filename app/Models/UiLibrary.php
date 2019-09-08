<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UiLibrary extends Model
{
    //
    protected $table = 'ui_librarys';

    protected $guarded = [];

    // 面包削导航
    public static function breadcrumb($ui_id = 0)
    {
        $my_ui = UiLibrary::where('id', $ui_id)->first(['id', 'name', 'parent_id']);

        $pid = $my_ui->parent_id ?? 0;

        $breadcrumbs = [];
        $root_node = ['id' => 0, 'name' => '根目录']; // 根节点

        if ($pid == 0) {
            array_push($breadcrumbs, $root_node);
            array_push($breadcrumbs, ['id' => $my_ui->id, 'name' => $my_ui->name]);
            return $breadcrumbs;
        }

        if ($my_ui) {
            array_unshift($breadcrumbs, ['id' => $my_ui->id, 'name' => $my_ui->name]);
        }

        $stop = false;
        while (!$stop) {
            $ui = UiLibrary::where([
                ['id', '=', $pid],
                ['type', '=', 0],
            ])->first(['id', 'name', 'parent_id']);

            if ($ui->parent_id == 0) {
                $stop = true;
            }

            $pid = $ui->parent_id;
            $nav = ['id' => $ui->id, 'name' => $ui->name];
            array_unshift($breadcrumbs, $nav);
        }

        array_unshift($breadcrumbs, $root_node);
        return $breadcrumbs;
    }

    // 检查UI文件是否已经存在
    public static function uiIsExist($where = [])
    {
        return UiLibrary::where([
            ['parent_id', '=', $where['parent_id']],
            ['name', '=', $where['filename']],
            ['extension', '=', $where['extension']],
        ])->first();
    }
}

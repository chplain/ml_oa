<?php
/*
|--------------------------------------------------------------------------
| 自定义全局函数
|--------------------------------------------------------------------------
 */

/**
 * 更加友好的 Debug 函数 dd => dda
 */
if (!function_exists('ddd')) {
    function ddd($var)
    {
        foreach (func_get_args() as $v) {
            if (method_exists($v, 'toArray')) {
                dump($v->toArray());
            } else {
                dump($v);
            }
        }
        exit;
    }
}

/**
 * @param $bytes
 * @return string
 * 文件单位大小转换
 */
if (!function_exists('formatSizeUnits')) {
    function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } else if ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } else if ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } else if ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } else if ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
    }
}

/**
 * @param $start_date
 * @param $end_date
 * @param $date_type true:Y-m-d   false:Ymd
 * 获取两个日期之间的所有日期
 */
if (!function_exists('prDates')) {
    function prDates($start_date, $end_date, $date_type = true)
    {
        $dt_start = strtotime($start_date);
        $dt_end = strtotime($end_date);
        $days = [];
        while ($dt_start <= $dt_end) {
            $days[] = date($date_type ? 'Y-m-d' : 'Ymd', $dt_start);
            $dt_start = strtotime('+1 day', $dt_start);
        }
        return $days;
    }
}

/**
 * 导出Excel文件
 */
if (!function_exists('pExprot')) {
    function pExprot($theads = [], $tbodys = [], $filename = null, $sheet_name = 'Sheet1', $sheet2 = 'Sheet2', $sheet2_theads = [], $sheet2_tbodys = [])
    {
        $filename = empty($filename) ? md5(uniqid()) : $filename;
        $cell_data = $tbodys;
        array_unshift($cell_data, $theads);
        return \Maatwebsite\Excel\Facades\Excel::create($filename, function ($excel) use ($cell_data, $sheet_name, $sheet2, $sheet2_theads, $sheet2_tbodys) {
            $excel->sheet($sheet_name, function ($sheet) use ($cell_data) {
                $sheet->rows($cell_data);
                $sheet->cell('A1:Z' . count($cell_data), function ($cells) {
                    $cells->setAlignment('center'); // 设置单元格水平对齐
                    $cells->setValignment('center'); // 设置单元格垂直对齐
                });

                //设置列宽
                $sheet->setWidth([               // 设置多个列
                    'A' => 25, 'B' => 25, 'C' => 25, 'D' => 25, 'E' => 25, 'F' => 25, 'G' => 25, 'H' => 25, 'I' => 25, 'J' => 25, 'K' => 25, 'L' => 25, 'M' => 25, 'N' => 25, 'O' => 25, 'P' => 25, 'Q' => 25, 'R' => 25, 'S' => 25, 'T' => 25, 'U' => 25, 'V' => 25, 'W' => 25, 'X' => 25, 'Y' => 25, 'Z' => 25,]);
            });

            if (!empty($sheet2_tbodys)) {
                //第二页
                $sheet2_data = $sheet2_tbodys;
                array_unshift($sheet2_data, $sheet2_theads);
                $excel->sheet($sheet2, function ($sheet) use ($sheet2_data) {
                    $sheet->rows($sheet2_data);
                    $sheet->cell('A1:Z' . count($sheet2_data), function ($cells) {
                        $cells->setAlignment('center'); // 设置单元格水平对齐
                        $cells->setValignment('center'); // 设置单元格垂直对齐
                    });

                    //设置列宽
                    $sheet->setWidth([               // 设置多个列
                        'A' => 25, 'B' => 25, 'C' => 25, 'D' => 25, 'E' => 25, 'F' => 25, 'G' => 25, 'H' => 25, 'I' => 25, 'J' => 25, 'K' => 25, 'L' => 25, 'M' => 25, 'N' => 25, 'O' => 25, 'P' => 25, 'Q' => 25, 'R' => 25, 'S' => 25, 'T' => 25, 'U' => 25, 'V' => 25, 'W' => 25, 'X' => 25, 'Y' => 25, 'Z' => 25,]);
                });
            }
        })->store('xls', storage_path('app/public/exports'), true);
    }
}

/**
 * @param $categorys
 * @param $parent_id
 * @param $sort_field
 * @return array
 * 递归无限级分类实现
 */
if (!function_exists('getTree')) {
    function getTree($categorys = [], $parent_id = 0, $sort_field = 'sort')
    {
        $tree = []; // 每次都声明一个新数组用来放子元素
        foreach ($categorys as $category) {
            if ($category['parent_id'] == $parent_id) { // 匹配子记录
                $category['children'] = getTree($categorys, $category['id']); // 递归获取子记录
                if (empty($category['children'])) {
                    unset($category['children']); // 如果子元素为空则unset()进行删除，说明已经到该分支的最后一个元素了（可选）
                }
                //  通过sort字段进行排序
                if (!empty($category['children']) && is_array($category['children'])) {
                    $category['children'] = collect($category['children'])->sortBy($sort_field)->values()->all();
                }
                $tree[] = $category;// 将记录存入新数组
            }
        }
        return collect($tree)->sortBy($sort_field)->values()->all();  // 返回新数组
    }
}

/**
 * @param $categorys
 * @param $parent_id
 * @return array
 * 递归无限级子集id实现，仅返回id
 */
if (!function_exists('getTreeById')) {
    function getTreeById($data, $parent_id = 0, $is_first_time = true)
    {
        static $array = [];
        $array = $is_first_time ? [] : $array;
        foreach ($data as $key => $val) {
            if ($val['parent_id'] == $parent_id) {
                $array[] = $val['id'];
                getTreeById($data, $val['id'], false);
            }
        }
        return $array;
    }
}

/**
 * 写入系统日志
 * @param $path 路径标题
 * @param $content 内容
 */
if (!function_exists('systemLog')) {
    function systemLog($path = '-', $content = '-', $type = 1, $menu_id = 0)
    {
        $userInfo = auth()->user();
        // 拿取缓存登录ip、登录地址
        $location = cache()->remember('user_id_' . $userInfo->id, 30, function () {
            // 获取ip、地址
            $login_ip = request()->getClientIp();
            $ipip = new \ipip\db\City(storage_path('app/private/ipdb/ipipfree.ipdb'));
            $location = $ipip->find($login_ip, 'CN');
            $login_addr = empty($location[2]) ? '未知' : $location[2];
            // 存入缓存
            $cache_arr = [];
            $cache_arr['login_ip'] = $login_ip;
            $cache_arr['login_addr'] = $login_addr;
            return $cache_arr;
        });
        $log_array = [];
        $log_array['type'] = $type; //1普通  2登录 退出
        $log_array['menu_id'] = $menu_id; //根菜单id
        $log_array['user_id'] = $userInfo->id;
        $log_array['username'] = $userInfo->username;
        $log_array['login_ip'] = $location['login_ip'];
        $log_array['login_addr'] = $location['login_addr'];
        $log_array['operate_path'] = $path;
        $log_array['content'] = '[' . $userInfo->realname . ']' . $content;
        (new \App\Models\SystemLog)->addLog($log_array);
    }
}

/**
 * @Author: qinjintian
 * @Date:   2018-11-29
 * 导出Excel文件，一次创建一张或多张工作簿
 * @param array $theads 表头
 * @param array $tbodys 内容
 * @param array $sheet_names 工作簿名称
 * @param null $file_name 文件名
 * @param null $path 文件保存路径
 * @param null $suffix 文件后缀 xls、xlsx
 * @return array
 */
if (!function_exists('exportMultipleExcel')) {
    function exportMultipleExcel($theads = [], $tbodys = [], $sheet_names = [], $file_name = null, $path = 'app/public/temps', $suffix = 'xls')
    {
        if (count($theads) != count($tbodys)) {
            return ['code' => 0, 'message' => '头部字段和内容字段不一致，请检查'];
        }
        $file_name = $file_name ? $file_name : date('YmdHis') . uniqid();
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return \Excel::create($file_name, function ($excel) use ($theads, $tbodys, $sheet_names) {
            $sheet_count = count($theads);
            for ($i = 0; $i < $sheet_count; $i++) {
                $cell_data = array_merge([$theads[$i]], $tbodys[$i]) ?? [];
                $sheet_name = $sheet_names[$i] ?? 'Sheet' . ($i + 1);
                $excel->sheet($sheet_name, function ($sheet) use ($cell_data) {
                    $sheet->fromArray($cell_data, null, 'A1', true, false);
                    $max_string_column = $sheet->getHighestColumn();
                    $max_index_column = \PHPExcel_Cell::columnIndexFromString($max_string_column) - 1; // 英文字母索引转数字索引
                    $cell_size = [];
                    for ($i = 0; $i <= $max_index_column; $i++) {
                        $cell_size[\PHPExcel_Cell::stringFromColumnIndex($i)] = 15;
                    }
                    $sheet->setWidth($cell_size); // 设置单元格宽度
                    $style = ['alignment' => ['horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER, // 水平居中
                        'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER, // 垂直居中
                    ]];
                    $sheet->getDefaultStyle()->applyFromArray($style);
                });
            }
        })->store($suffix, storage_path($path), true);
    }
}

/**
 * @Author: qinjintian
 * @Date:   2018-11-29
 * 导出Excel文件，只导到第一张工作簿
 * @param array $theads 表头
 * @param array $tbodys 内容
 * @param array $sheet_name 工作簿名称
 * @param null $file_name 文件名
 * @param null $path 文件保存路径
 * @param null $suffix 文件后缀 xls, xlsx, htm, html, csv, txt
 * @return array
 */
if (!function_exists('exportSingleExcel')) {
    function exportSingleExcel($theads = [], $tbodys = [], $sheet_name = null, $file_name = null, $path = 'app/public/temps', $suffix = 'xls')
    {
        $file_name = $file_name ? $file_name : date('YmdHis') . uniqid();
        $sheet_name = $sheet_name ? $sheet_name : 'Sheet1';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return \Excel::create($file_name, function ($excel) use ($theads, $tbodys, $sheet_name) {
            $cell_data = array_merge([$theads], $tbodys);
            $excel->sheet($sheet_name, function ($sheet) use ($cell_data) {
                $sheet->fromArray($cell_data, null, 'A1', true, false);
                $max_string_column = $sheet->getHighestColumn();
                $max_index_column = \PHPExcel_Cell::columnIndexFromString($max_string_column) - 1; // 英文字母索引转数字索引
                $cell_size = [];
                for ($i = 0; $i <= $max_index_column; $i++) {
                    $cell_size[\PHPExcel_Cell::stringFromColumnIndex($i)] = 15;
                }
                $sheet->setWidth($cell_size); // 设置单元格宽度
                $style = [
                    'alignment' => [
                        'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER, // 垂直居中
                        'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER, // 水平居中
                    ]
                ];
                $sheet->getDefaultStyle()->applyFromArray($style);
            });
        })->store($suffix, storage_path($path), true);
    }
}

/**
 * @Author: qinjintian
 * @Date:   2018-11-30
 * 导入Excel文件，可读取多张工作簿内容
 * @param string $file_name 文件名（绝对路径）
 * @param array $sheet_index 工作簿索引，默认为 [] 读取全部工作簿，可指定读取一个或多个工作簿，例如读取第1张和第二张为：[0, 1]
 * @param int $skip_rows 跳过行，默认为0读取工作簿所有行内容
 * @return array
 */
if (!function_exists('importExcel')) {
    function importExcel($file_name, $sheet_index = [], $skip_rows = 0)
    {
        if (!$file_name) {
            return ['code' => 0, 'message' => '文件名不能为空'];
        }
        if (!file_exists($file_name)) {
            return ['code' => 0, 'message' => '文件名不存在，请检查'];
        }
        $excel = \Excel::selectSheetsByIndex($sheet_index)->load($file_name);
        $sheet_count = $excel->getSheetCount(); // 工作簿总数
        $sheet_names = $excel->getSheetNames(); // 所有工作簿名
        $sheets = $excel->noHeading()->skip($skip_rows)->get()->toArray(); // 所有工作簿
        return ['code' => 1, 'message' => 'success', 'data' => ['sheet_count' => $sheet_count, 'sheet_names' => $sheet_names, 'sheets' => $sheets]];
    }
}

/**
 * 消息提醒
 * @param $user_id 被提醒人  array/int
 * @param $title 标题
 * @param $message 内容
 * @param $date 日报日期
 */
if (!function_exists('addNotice')) {
    function addNotice($user_id = [], $title = '', $message = '', $date = '', $operator_id = 0, $url = '', $api_route_url = '', $api_params = [])
    {
        if (empty($user_id) || empty($title) || empty($message)) return false;
        $notice_array = [];
        if (is_array($user_id)) {
            //多个
            foreach ($user_id as $uid) {
                $tmp = [];
                $tmp['user_id'] = $uid;
                $tmp['title'] = $title;
                $tmp['message'] = $message;
                if (!empty($date)) {
                    $tmp['date'] = $date;
                }
                $tmp['operator_id'] = $operator_id;
                $tmp['url'] = $url;
                $tmp['api_route_url'] = $api_route_url;
                $tmp['api_params'] = serialize($api_params);
                $tmp['created_at'] = date('Y-m-d H:i:s');
                $tmp['updated_at'] = date('Y-m-d H:i:s');
                $notice_array[] = $tmp;
            }
        } elseif (is_numeric($user_id)) {
            //单个
            $notice_array['user_id'] = $user_id;
            $notice_array['title'] = $title;
            $notice_array['message'] = $message;
            if (!empty($date)) {
                $notice_array['date'] = $date;
            }
            $notice_array['operator_id'] = $operator_id;
            $notice_array['url'] = $url;
            $notice_array['api_route_url'] = $api_route_url;
            $notice_array['api_params'] = serialize($api_params);
            $notice_array['created_at'] = date('Y-m-d H:i:s');
            $notice_array['updated_at'] = date('Y-m-d H:i:s');
        } else {
            return false;
        }
        $notice = new \App\Models\Notification;
        return $notice->insert($notice_array);
    }
}

/**
 * @Author: qinjintian
 * @Date:   2019-03-12
 * 检查当前用户访问是否在微信内容浏览器中
 */
if (!function_exists('isWeChat')) {
    function isWeChat()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return true;
        }
        return false;
    }
}


/**
 * @Author: qinjintian
 * @Date:   2019-03-13
 * 图片转base64
 * @param $image_file String 图片路径
 * @return 转为base64的图片
 */
if (!function_exists('base64EncodeImage')) {
    function base64EncodeImage($image_file)
    {
        if (file_exists($image_file) || is_file($image_file)) {
            $base64_image = '';
            $image_info = getimagesize($image_file);
            $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
            $base64_image = 'data:' . $image_info['mime'] . ';base64,' . base64_encode($image_data);
            return $base64_image;
        } else {
            return false;
        }
    }
}

/**
 * @Author: qinjintian
 * @Date:   2019-03-14
 * 获取指定日期所在周
 * @param null $date 日期，如:2019-03-04
 */
if (!function_exists('getWeeks')) {
    function getWeeks($date = null)
    {
        $date = $date ?? date('Y-m-d');
        $week = date('w', strtotime($date));
        $week = $week == 0 ? 7 : $week;
        $monday_date = date('Y-m-d', strtotime('-' . ($week - 1) . ' day', strtotime($date)));
        $sunday_date = date('Y-m-d', strtotime('+' . (7 - $week) . ' day', strtotime($date)));
        $friday_date = date('Y-m-d', strtotime('+4 day', strtotime($monday_date)));
        return [
            'today' => $date,
            'monday_date' => $monday_date,
            'friday_date' => $friday_date,
            'sunday_date' => $sunday_date
        ];
    }
}

/**
 * @Author: qinjintian
 * @Date:   2019-03-25
 * 判断某年某月有多少天
 */
if (!function_exists('yearMonthDays')) {
    function yearMonthDays($year, $month)
    {
        if (in_array($month, ['1', '3', '5', '7', '8', '01', '03', '05', '07', '08', '10', '12'])) {
            return '31';
        } elseif ($month == 2) {
            if ($year % 400 == 0 || ($year % 4 == 0 && $year % 100 !== 0)) { // 判断是否是闰年
                return '29';
            } else {
                return '28';
            }
        } else {
            return '30';
        }
    }
}
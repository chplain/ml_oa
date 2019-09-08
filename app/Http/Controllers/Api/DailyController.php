<?php

namespace App\Http\Controllers\Api;

use App\Models\Daily;
use App\Models\DailySetting;
use App\Models\DailyType;
use App\Models\Dept;
use App\Models\Holiday;
use App\Models\Notification;
use App\Models\Position;
use App\Models\ReportBasicSetting;
use App\Models\User;
use App\Models\WeekSetting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use phpDocumentor\Reflection\DocBlock\Description;

class DailyController extends Controller
{
    /**
     *  保存日报配置
     * @Author: qinjintian
     * @Date:   2018-12-13
     **/
    public function setting()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 进入日报设置页面需要的数据
                return $this->getDailySetting();
                break;
            default:
                // 保存日报设置
                return $this->storeDailySetting($inputs);
        }
    }

    /**
     * 日报设置页面表单数据
     * @return array
     */
    private function getDailySetting(): array
    {
        $last_daily_setting = DailySetting::where('user_id', auth()->id())->orderBy('id', 'DESC')->first();
        $daily_types = $last_daily_setting ? $last_daily_setting->dailyTypes()->get() : [];
        $projects = []; // OA文档
        return ['code' => 1, 'message' => 'success', 'data' => ['daily_types' => $daily_types, 'projects' => $projects]];
    }

    /**
     * 保存日报设置
     * @param $inputs
     * @return array
     */
    private function storeDailySetting($inputs): array
    {
        $rules = [
            'daily_types' => 'required|array',
        ];
        $attributes = [
            'daily_types' => '日报类型',
        ];
        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        // 验证日报类型
        foreach ($inputs['daily_types'] as $key => $val) {
            if (empty($val['daily_type_name'])) {
                return ['code' => -1, 'message' => '第 ' . ($key + 1) . ' 行的日报类型名称不能为空'];
            }
            if (!empty($val['if_relation'])) {
                if (empty($val['project_ids']) || !is_array($val['project_ids']) || count($val['project_ids']) < 1) {
                    return ['code' => -1, 'message' => '第 ' . ($key + 1) . ' 行请选择关联的OA文档'];
                }
            }
        }
        $daily_setting_model = new \App\Models\DailySetting;
        return $daily_setting_model->storeSettings($inputs);
    }

    /**
     * 写日报
     * @Author: qinjintian
     * @Date:   2018-12-13
     */
    public function store()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 日报表单数据
                return $this->getDailyFromData();
                break;
            default:
                // 保存日报
                return $this->storeDaily($inputs);
        }
    }

    /**
     * 获取加载日报表单内容
     * @return array
     */
    private function getDailyFromData(): array
    {
        $daily_setting = DailySetting::where('user_id', auth()->id())->orderBy('id', 'DESC')->first();
        if (!$daily_setting) {
            return ['code' => 0, 'message' => '请先到【我的日报设置】进行日报类型设置再写日报'];
        }
        $daily_types = $daily_setting->dailyTypes()->where('status', 1)->get();
        $data['daily_setting_id'] = $daily_setting->id;
        $data['daily_types'] = $daily_types;
        return ['code' => 1, 'message' => 'success', 'data' => ['daily_types' => $data]];
    }

    /**
     * 写日报
     * @param $inputs
     * @return array
     */
    private function storeDaily($inputs): array
    {
        $rules = [
            'daily_setting_id' => 'required|integer|min:1',
            'daily_content' => 'required|array|min:1'
        ];

        $attributes = [
            'daily_setting_id' => '日报配置ID',
            'daily_content' => '日报内容',
        ];

        $messages = [];

        $validator = validator($inputs, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        // 写日报的人只能在工作日当天写
        if (auth()->user()->report_id == 1) {
            $is_working_day = Holiday::where([['type', '=', 0], ['date', '=', date('Y-m-d', time())]])->count();
            if ($is_working_day < 1) {
                return ['code' => 0, 'message' => '今天不是工作日，不需要写日报'];
            }

            $if_write_daily = Daily::where('user_id', auth()->id())->where('date', date('Y-m-d'))->first();
            if ($if_write_daily) {
                return ['code' => 0, 'message' => '您今日已经写过日报，不能重新填写'];
            }
        }

        // 写周报的人周一至周日都可以写
        if (auth()->user()->report_id == 2) {
            $weeks = getWeeks(); // 指定日期所在周的周一和周日日期

            $working_day_count = Holiday::where('type', 0)->whereBetween('date', [$weeks['monday_date'], $weeks['sunday_date']])->count();
            if ($working_day_count < 1) {
                return ['code' => 0, 'message' => '这周没有工作日，不需要写周报'];
            }

            $my_daily = Daily::where('user_id', auth()->id())->whereBetween('date', [$weeks['monday_date'], $weeks['sunday_date']])->first();
            if ($my_daily) {
                return ['code' => 0, 'message' => '本周已经 ' . $my_daily['date'] . ' 已经写过周报了，可到【我的日报】列表进行修改'];
            }
        }

        $entry_date = auth()->user()->contracts()->value('entry_date'); // 入职时间
        if (strtotime(date('Y-m-d')) < strtotime($entry_date)) {
            return ['code' => 0, 'message' => '日报日期不能在入职日期之前'];
        }

        $is_my_daily_setting = DailySetting::where([['id', '=', $inputs['daily_setting_id']], ['user_id', '=', auth()->id()]])->first();
        if (!$is_my_daily_setting) {
            return ['code' => 0, 'message' => '报表配置ID错误，您没有这条报表配置，请检查'];
        }

        // 验证内容字段
        $check_result = $this->checkDailyFields($inputs['daily_setting_id'], $inputs['daily_content']);
        if ($check_result['code'] != 1) {
            // 验证不通过
            return $check_result;
        }

        $now_time = time();
        $data = [];
        $data['daily_setting_id'] = $inputs['daily_setting_id'];
        $data['daily_content'] = serialize($inputs['daily_content']);
        $data['date'] = date('Y-m-d', $now_time);
        $data['year'] = date('Y', $now_time);
        $data['month'] = date('m', $now_time);
        $data['user_id'] = auth()->id();
        $data['dept_id'] = auth()->user()->dept_id;
        $data['status'] = 0; // 正常
        $daily = (new Daily)->storeDaily($data);
        if ($daily) {
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('日报管理', '写了[' . $data['date'] . ']的日报');
        } else {
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    private function checkDailyFields($daily_setting_id, $daily_content)
    {
        $daily_types = DailyType::where('daily_setting_id', $daily_setting_id)->select(['id', 'daily_type_name', 'if_relation'])->get()->keyBy('id');
        $daily_type_ids = $daily_types->pluck('id')->toArray();
        $validator_fields = [];
        foreach ($daily_content as $key => $val) {
            if (!in_array($key, $daily_type_ids)) {
                return ['code' => 0, 'message' => '报表类型ID错误，您最新的报表类型配置中没有这个ID（' . $key . '），请检查'];
            }

            if (empty($val)) {
                unset($daily_content[$key]);
                continue;
            }

            foreach ($val as $keys => $vals) {
                if (isset($daily_types[$key]['if_relation']) && $daily_types[$key]['if_relation'] == 1) {
                    // 开启了关联文档需要判断任务id和完成度
                    if (!isset($vals['project_id']) || intval($vals['project_id']) < 1) {
                        array_push($validator_fields, 0);
                        continue;
                    } elseif (!isset($vals['schedule']) || intval($vals['schedule']) < 1) {
                        array_push($validator_fields, 0);
                        continue;
                    }
                }
                if (empty($vals['remark'])) {
                    array_push($validator_fields, 0);
                    continue;
                }
                if (empty($vals['time_cost']) || !is_numeric($vals['time_cost']) || intval($vals['time_cost']) < 1) {
                    array_push($validator_fields, 0);
                    continue;
                }
                if (empty($vals['period']) || !in_array(trim($vals['period']), ['上午', '下午', '上/下午'])) {
                    array_push($validator_fields, 0);
                    continue;
                }
                array_push($validator_fields, 1);
            }
        }
        if (!in_array(1, array_unique($validator_fields))) {
            return ['code' => 0, 'message' => '报表内容不能全部为空，请检查'];
        }

        foreach ($daily_content as $key => $val) {
            if (!in_array($key, $daily_type_ids)) {
                return ['code' => 0, 'message' => '报表类型ID错误，您最新的报表类型配置中没有这个ID（' . $key . '），请检查'];
            }
            foreach ($val as $keys => $vals) {
                if (isset($daily_types[$key]['if_relation']) && $daily_types[$key]['if_relation'] == 1) {
                    // 开启了关联文档需要判断任务id和完成度
                    if (!isset($vals['project_id']) || intval($vals['project_id']) < 1) {
                        return ['code' => -1, 'message' => ($daily_types[$key]['daily_type_name'] ?? 'n') . ' 部分第 ' . ($keys + 1) . ' 行的项目名字段不能为空，请检查'];
                    }
                    if (!isset($vals['schedule']) || intval($vals['schedule']) < 1) {
                        return ['code' => -1, 'message' => ($daily_types[$key]['daily_type_name'] ?? 'n') . ' 部分第 ' . ($keys + 1) . ' 行的完成度字段不能为空，请检查'];
                    }
                }

                if (empty($vals['remark'])) {
                    return ['code' => -1, 'message' => ($daily_types[$key]['daily_type_name'] ?? 'n') . ' 部分第 ' . ($keys + 1) . ' 行的备注或描述不能为空，请检查'];
                }

                // if (empty($vals['time_cost']) || !is_numeric($vals['time_cost']) || intval($vals['time_cost']) < 1) {
                //     return ['code' => -1, 'message' => ($daily_types[$key]['daily_type_name'] ?? 'n') . ' 部分第 ' . ($keys + 1) . ' 行的耗时(分钟)填写有误，请检查'];
                // }
                //
                // if (empty($vals['period']) || !in_array(trim($vals['period']), ['', '上午', '下午', '上/下午'])) {
                //     return ['code' => -1, 'message' => ($daily_types[$key]['daily_type_name'] ?? 'n') . ' 部分第 ' . ($keys + 1) . ' 行的 上/下午 填写有误，请检查'];
                // }
            }
        }

        return ['code' => 1, 'message' => 'qualified'];
    }

    /**
     * 我的日报
     * @Author: qinjintian
     * @Date:   2018-12-14
     */
    public function myDaily()
    {
        $inputs = request()->all();
        $daily_model = new Daily;
        $sources = $daily_model->queryMyDailys($inputs);
        $response = ['code' => 0, 'message' => '获取数据失败，请重试'];
        if ($sources) {
            $response = ['code' => 1, 'message' => '获取数据成功', 'data' => $sources];
        }
        return $response;
    }

    /**
     * 我的日报详情
     * @Author: qinjintian
     * @Date:   2018-12-14
     */
    public function myDailyShow()
    {
        $id = request()->input('id', 0);
        $daily = Daily::with(['user' => function ($query) {
            $query->select(['id', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->where(['id' => $id, 'user_id' => auth()->id()])
            ->select(['id', 'date', 'user_id', 'dept_id', 'daily_content', 'daily_setting_id'])
            ->first();
        if (!$daily) {
            return ['code' => 0, 'message' => '不存在您的这条数据'];
        }
        $data = $this->formatDailyDetail($daily);
        return ['code' => 1, 'message' => 'success', 'data' => $data];
    }

    /**
     * 格式化日报详情
     * @param $daily
     * @return array
     */
    private function formatDailyDetail($daily)
    {
        $daily_types = DailyType::where('daily_setting_id', $daily->daily_setting_id)->select(['id', 'daily_type_name'])->get()->keyBy('id');
        $daily_content = [];
        foreach ($daily->daily_content as $key => $val) {
            $daily_content[$key]['daily_type_id'] = $key;
            $daily_content[$key]['daily_type_name'] = $daily_types[$key]['daily_type_name'] ?? '';
            $daily_content[$key]['daily_content'] = $val;
            foreach ($val as $ckey => $cval) {
                $daily_content[$key]['daily_content'][$ckey]['project_name'] = '没有项目名';
            }
        }
        $data = [];
        $data['date'] = $daily->date;
        $data['dept_name'] = $daily->dept->name;
        $data['realname'] = $daily->user->realname;
        $data['daily_setting_id'] = $daily->daily_setting_id;
        $data['daily_content'] = $daily_content;
        return $data;
    }

    /**
     * 我的部门日报
     * @Author: qinjintian
     * @Date:   2018-12-14
     */
    public function myDeptDaily()
    {
        $inputs = request()->all();
        $daily_model = new Daily;
        $sources = $daily_model->queryMyDeptDailys($inputs);
        $response = ['code' => 0, 'message' => '获取数据失败，请重试'];
        if ($sources) {
            $response = ['code' => 1, 'message' => '获取数据成功', 'data' => $sources];
        }
        return $response;
    }

    /**
     * 查看我的部门日报详情
     * @Author: qinjintian
     * @Date:   2018-12-14
     */
    public function myDeptDailyShow()
    {
        $id = request()->input('id', 0);
        $daily = Daily::with(['user' => function ($query) {
            $query->select(['id', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->where(['id' => $id, 'dept_id' => auth()->user()->dept_id])
            ->select(['id', 'date', 'user_id', 'dept_id', 'daily_content', 'daily_setting_id'])
            ->first();
        if (!$daily) {
            return ['code' => 0, 'message' => '这条数据不存在，或您无权查看非本部门的日报'];
        }
        $data = $this->formatDailyDetail($daily);
        return ['code' => 1, 'message' => 'success', 'data' => $data];
    }

    /**
     * 日报汇总列表
     * @Author: qinjintian
     * @Date:   2018-12-14
     */
    public function summary()
    {
        $inputs = request()->all();
        $daily_model = new Daily;
        $sources = $daily_model->queryDailySummary($inputs);
        $response = ['code' => 0, 'message' => '获取数据失败，请重试'];
        if ($sources) {
            $response = ['code' => 1, 'message' => '获取数据成功', 'data' => $sources];
        }
        return $response;
    }

    /**
     * 日报汇总-查看详情
     * @Author: qinjintian
     * @Date:   2018-12-21
     */
    public function summaryShow()
    {
        $id = request()->input('id', 0);
        $daily = Daily::with(['user' => function ($query) {
            $query->select(['id', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->where('id', $id)
            ->select(['id', 'date', 'user_id', 'dept_id', 'daily_content', 'daily_setting_id'])
            ->first();
        if (!$daily) {
            return ['code' => 0, 'message' => '这条数据不存在，或您无权查看非本部门的日报'];
        }
        $data = $this->formatDailyDetail($daily);
        return ['code' => 1, 'message' => 'success', 'data' => $data];
    }

    /**
     * 补写日报
     * @Author: qinjintian
     * @Date:   2018-12-17
     */
    public function replenish()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 日报表单数据
                return $this->getDailyFromData();
                break;
            default:
                // 补写日报
                return $this->replenishDaily($inputs);
        }
    }

    /**
     * 补写日报
     * @param $inputs
     * @return array
     */
    private function replenishDaily($inputs): array
    {
        $inputs = request()->all();
        $rules = [
            'daily_setting_id' => 'required|integer|min:1',
            'daily_content' => 'required|array|min:1',
            'date' => 'required|date|date_format:"Y-m-d"',
        ];

        $attributes = [
            'daily_setting_id' => '日报配置ID',
            'daily_content' => '日报内容',
            'date' => '日报日期',
        ];

        $messages = [];

        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        if (strtotime($inputs['date']) >= strtotime(date('Y-m-d'))) {
            return ['code' => 0, 'message' => '只能补写今天之前的日报'];
        }

        // 写日报的人
        if (auth()->user()->report_id == 1) {
            $my_diary_today_count = Daily::where(['user_id' => auth()->id(), 'date' => $inputs['date']])->count();
            if ($my_diary_today_count > 0) {
                return ['code' => 0, 'message' => $inputs['date'] . ' 的日报已经存在，不能重复'];
            }

            $is_working_day = Holiday::where([['type', '=', 0], ['date', '=', $inputs['date']]])->count();
            if ($is_working_day < 1) {
                return ['code' => 0, 'message' => $inputs['date'] . ' 不是工作日，无需补写'];
            }
        }

        // 写周报的人周一至周日都可以写
        if (auth()->user()->report_id == 2) {
            $weeks = getWeeks($inputs['date']); // 指定日期所在周的周一和周日日期

            $working_day_count = Holiday::where('type', 0)->whereBetween('date', [$weeks['monday_date'], $weeks['sunday_date']])->count();
            if ($working_day_count < 1) {
                return ['code' => 0, 'message' => '这周没有工作日，不需要写周报'];
            }

            $my_daily = Daily::where('user_id', auth()->id())->whereBetween('date', [$weeks['monday_date'], $weeks['sunday_date']])->first();
            if ($my_daily) {
                return ['code' => 0, 'message' => '你要补写 ' . $my_daily['date'] . ' 的周报已经存在了，可到【我的日报】列表进行修改'];
            }
        }

        $is_my_daily_setting = DailySetting::where([['id', '=', $inputs['daily_setting_id']], ['user_id', '=', auth()->id()]])->first();
        if (!$is_my_daily_setting) {
            return ['code' => 0, 'message' => '报表类型ID错误，您没有这条报表配置，请检查'];
        }

        $entry_date = auth()->user()->contracts()->value('entry_date'); // 入职时间
        if (strtotime(date('Y-m-d')) < strtotime($entry_date)) {
            return ['code' => 0, 'message' => '日报日期不能在入职日期之前'];
        }

        $check_result = $this->checkDailyFields($inputs['daily_setting_id'], $inputs['daily_content']);
        if ($check_result['code'] != 1) {
            return $check_result;
        }

        $data = [];
        $data['daily_setting_id'] = $inputs['daily_setting_id'];
        $data['daily_content'] = serialize($inputs['daily_content']);
        $data['date'] = $inputs['date'];
        $data['year'] = substr($inputs['date'], 0, 4);
        $data['month'] = substr($inputs['date'], 5, 2);
        $data['user_id'] = auth()->id();
        $data['dept_id'] = auth()->user()->dept_id;
        $data['status'] = 1; // 迟写
        $daily = (new Daily)->storeDaily($data);
        $notification = Notification::where([['user_id', '=', auth()->id()], ['date', '=', $inputs['date']], ['status', '=', 0]])->first();
        if (!empty($notification)) {
            $notification->read_time = date('Y-m-d H:i:s', time());
            $notification->status = 1;
            $notification->save();
        }
        if (!$daily) {
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        } else {
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('日报管理', '补写[' . $data['date'] . ']日报');
        }
        return $response;
    }

    /**
     * 基础设置
     * @Author: qinjintian
     * @Date:   2018-12-18
     */
    public function basisSetting()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 报表基础设置
                $report_basic_settings = ReportBasicSetting::all();
                return ['code' => 1, 'message' => 'success', 'data' => $report_basic_settings];
                break;
            case 2:
                // 查看报表人员
                if (empty($inputs['report_id'])) {
                    return ['code' => -1, 'message' => '报表ID不能为空'];
                }
                $user_list = (new ReportBasicSetting)->queryUserList($inputs);
                return ['code' => 1, 'message' => 'success', 'data' => $user_list];
                break;
            default:
                // 保存报表基础设置
                return $this->storeReportBasicSetting($inputs);
        }
    }

    /**
     * 保存报表基础设置
     * @param $inputs
     * @return array
     */
    private function storeReportBasicSetting($inputs): array
    {
        $rules = [
            'id' => 'required|integer|min:1',
            'report_type_name' => 'required',
            'if_assess' => 'required|numeric|min:0|max:1',
            'status' => 'required|numeric|min:0|max:1',
        ];
        $attributes = [
            'id' => '报表ID',
            'report_type_name' => '报表类型名称',
            'if_assess' => '是否考核',
            'status' => '启用/禁用',
        ];
        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $report_basic_setting_model = new ReportBasicSetting;
        $result = $report_basic_setting_model->storeBasisSetting($inputs);
        if ($result) {
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('日报管理', '保存了[' . $inputs["report_type_name"] . ']基础设置');
        } else {
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 周报提交日期设置
     * @Author: renxianyong
     * @Date:   2019-03-13
     */
    public function weekSetting()
    {
        $inputs = request()->all();
        $data = [];
        if (isset($inputs['request_type']) && $inputs['request_type'] == 'view') {
            return $this->calendarDate($inputs, $data);//获取指定月份的日历数据
        }
        return $this->weekDateSet($inputs);//批量修改指定月份的周报提交日期数据
    }

    /**
     * @param array $inputs
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function calendarDate(array $inputs, array $data): \Illuminate\Http\JsonResponse
    {
        if (!isset($inputs['month']) || empty($inputs['month'])) {
            $inputs['month'] = date('m');
        }
        if (!isset($inputs['year']) || empty($inputs['year'])) {
            $inputs['year'] = date('Y');
        }
        $start_date = $inputs['year'] . '-' . $inputs['month'];
        $holiday = new \App\Models\Holiday;
        $data['dates'] = $holiday->whereRaw('left(`date`,7)= "' . $start_date . '"')->get(['id', 'date', 'type'])->toArray();//日历
        $week = new \App\Models\WeekSetting;
        $data['report_date'] = $week->where('year', $inputs['year'])->where('month', $inputs['month'])->get(['holiday_id', 'date'])->toArray();//需要写周报的日期
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * @param array $inputs
     * @return \Illuminate\Http\JsonResponse
     */
    public function weekDateSet(array $inputs): \Illuminate\Http\JsonResponse
    {
        $rules = [
            'year' => 'required|string|size:4',
            'month' => 'required|string|size:2',
            'datas' => 'required|array',
        ];

        $attributes = [
            'year' => '年份',
            'month' => '月份',
            'datas' => '当月需写日报数据',
        ];
        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $update = $tmp = [];
        foreach ($inputs['datas'] as $key => $val) {
            $tmp['holiday_id'] = $val['holiday_id'];
            $tmp['date'] = $val['date'];
            $tmp['created_at'] = date('Y-m-d H:i:s');
            $tmp['year'] = $inputs['year'];
            $tmp['month'] = $inputs['month'];
            $update[] = $tmp;
        }
        $week = new \App\Models\WeekSetting;
        $result = $week->updateReport($update);
        if ($result) {
            systemLog('日报设置', '编辑了周报日期设置');
            return response()->json(['code' => 1, 'message' => '保存成功']);
        }
        return response()->json(['code' => 0, 'message' => '保存失败']);
    }

    /**
     * 设置报表类型用户
     * @Author: qinjintian
     * @Date:   2018-12-18
     */
    public function reportUser()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 页面搜索条件
                $depts = (new Dept)->get(['id', 'name']);
                $positions = (new Position)->get(['id', 'name']);
                $data['depts'] = $depts;
                $data['positions'] = $positions;
                return ['code' => 1, 'message' => 'success', 'data' => $data];
                break;
            case 2:
                // 人员列表
                $users = (new ReportBasicSetting)->queryUserList($inputs);
                return ['code' => 1, 'message' => '获取人员列表成功', 'data' => $users];
                break;
            default:
                // 设置人员到对应的报表
                return $this->setReportUser($inputs);
        }
    }

    /**
     * 设置报表人员
     * @param $inputs
     * @return array
     */
    private function setReportUser($inputs): array
    {
        $rules = [
            'user_id' => 'required|integer|min:1',
            'report_id' => 'required|integer|min:0',
        ];
        $attributes = [
            'id' => '报表ID',
            'report_id' => '报表类型ID',
        ];
        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $user = User::find($inputs['user_id']);
        if (!$user) {
            return ['code' => 0, 'message' => '用户不存在，请检查'];
        }
        $user->report_id = $inputs['report_id'];
        $result = $user->save();
        if ($result) {
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('日报管理', '设置报表人员[' . $user["realname"] . ']');
        } else {
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 我的部门日报-消息提醒
     * @Author: qinjintian
     * @Date:   2018-12-19
     */
    public function notification()
    {
        return $this->notifcationApi();
    }

    /**
     * @return array
     */
    private function notifcationApi(): array
    {
        $inputs = request()->all();
        $rules = [
            'date' => 'required|date|date_format:"Y-m-d"',
            'user_id' => 'required|integer|min:1',
        ];
        $attributes = [
            'date' => '报表日期',
            'user_id' => '用户ID',
        ];
        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $user = User::where('id', $inputs['user_id'])->first();
        $notification_model = new Notification;
        $now_daily_unread_count = $notification_model->where([
            ['user_id', '=', $inputs['user_id']],
            ['date', '=', $inputs['date']],
            ['status', '=', 0]
        ])->count();
        if ($now_daily_unread_count > 0) {
            return ['code' => 0, 'message' => '' . $user->realname . $inputs['date'] . '的日/周报已经有人提醒过，且未读，不能重复提醒'];
        }
        $notification_model->user_id = $inputs['user_id'];
        $notification_model->title = '日/周报';
        $notification_model->message = $user->realname . '，您' . $inputs['date'] . '有一份日/周报没写，请尽快补写';
        $notification_model->operator_id = auth()->id();
        $notification_model->date = $inputs['date'];
        $notification_model->url = 'report-list-index';
        $result = $notification_model->save();
        return $result ? ['code' => 1, 'message' => '操作成功'] : ['code' => 0, 'message' => '操作失败，请重试'];
    }

    /**
     * 日报统计
     * @Author: qinjintian
     * @Date:   2018-12-19
     */
    public function statistical()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 部门信息
                $depts = Dept::all();
                $report_types = [['id' => 0, 'value' => '日报'], ['id' => 1, 'value' => '周报']];
                return ['code' => 1, 'message' => 'success', 'data' => ['depts' => $depts, 'report_types' => $report_types]];
                break;
            case 'detaill':
                // 统计详情
                return $this->statisticalDetaillApi($inputs);
            default:
                // 日报统计
                return $this->dailyStatistical($inputs);
        }
    }

    /**
     * 日报统计详细数据
     * @param $inputs
     * @return array
     */
    private function statisticalDetaillApi($inputs): array
    {
        $rules = [
            'year_month' => 'required|date_format:"Y-m"',
            'report_type' => 'required|integer|min:0|max:1'
        ];

        $attributes = [
            'year_month' => '年月份',
            'report_type' => '报表类型',
        ];

        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $statisticals = [];
        if ($inputs['report_type'] == 0) {
            // 日报统计
            $statisticals = (new Daily)->dailyStatisticalDetaill($inputs);
        } elseif ($inputs['report_type'] == 1) {
            // 周报统计
            $statisticals = (new Daily)->weeklyStatisticalDetaill($inputs);
        }
        return ['code' => 1, 'message' => '统计成功', 'data' => $statisticals];
    }

    // 验证日报内容

    /**
     * 日报统计
     * @param $inputs
     * @return array
     */
    private function dailyStatistical($inputs): array
    {
        // 导出日报统计的时候把日报详情页一起导出
        if (!empty($inputs['export'])) {
            // 日报统计
            $dailys = (new Daily)->dailyStatistical($inputs); // 日报
            $weeklys = (new Daily)->weeklyStatistical($inputs); // 周报
            $statisticals = array_merge($dailys, $weeklys);
            $theads_statisticals = ['部门', '姓名', '应写日报数', '按时提交数', '缺/迟提交数'];
            $tbodys_statisticals = [];
            foreach ($statisticals as $key => $val) {
                $tbodys_statisticals[$key]['dept_name'] = $val['dept_name'];
                $tbodys_statisticals[$key]['realname'] = $val['realname'];
                $tbodys_statisticals[$key]['total'] = $val['total'];
                $tbodys_statisticals[$key]['normal'] = $val['normal'];
                $tbodys_statisticals[$key]['lack'] = $val['lack'];
            }

            // 日报统计详情
            $daily_statistical_details = (new Daily)->exportDailyDetail($inputs);
            $theads_detail = ['部门,姓名'];
            foreach ($daily_statistical_details['everydays'] as $key => $val) {
                array_push($theads_detail, $val['date'] . '[' . $val['week'] . ']');
            }
            $tbodys_detail = [];
            foreach ($daily_statistical_details['statisticals'] as $key => $val) {
                $tbodys_detail[$key]['dept_name'] = $val['dept_name'];
                $tbodys_detail[$key]['realname'] = $val['realname'];
                foreach ($val['everyday'] as $date => $work) {
                    $tbodys_detail[$key][$date] = $work;
                }
            }

            //日工作汇报统计
            $statistical_everyday = (new Daily)->exportDailyEvery($inputs);
            $theads_everyday = ['部门,姓名'];
            foreach ($statistical_everyday['everydays'] as $key => $val) {
                array_push($theads_everyday, date('m月d日', strtotime($val['date'])) . '[' . $val['week'] . ']');
            }
            $theads_everyday = array_merge($theads_everyday,['总时间','天数','平均工作时间/小时']);
            $tbodys_everyday = [];
            foreach ($statistical_everyday['statisticals'] as $key => $val) {
                $tbodys_everyday[$key]['dept_name'] = $val['dept_name'];
                $tbodys_everyday[$key]['realname'] = $val['realname'];
                foreach ($val['everyday'] as $date => $time) {
                    $tbodys_everyday[$key][$date] = $time;
                }
                $tbodys_everyday[$key]['total_time'] = $val['total_time'];//总时间
                $tbodys_everyday[$key]['working_days_count'] = $val['working_days_count'];//天数
                $tbodys_everyday[$key]['average_work_time'] = $val['average_work_time'];//平均工作时间/小时
            }
            $fileinfo = exportMultipleExcel([$theads_statisticals, $theads_detail, $theads_everyday], [$tbodys_statisticals, $tbodys_detail, $tbodys_everyday], ['日报统计情况', '日报汇总详情', '日工作汇报统计'], 'daily-statistical');
            return ['code' => 1, 'message' => '导出成功', 'data' => ['url' => asset('storage/temps/' . $fileinfo['file']), 'filepath' => 'storage/temps/' . $fileinfo['file']]];
        } else {
            // 列表查询
            $report_type = isset($inputs['report_type']) && !empty($inputs['report_type']) && is_numeric($inputs['report_type']) ? $inputs['report_type'] : 0;
            $statisticals = [];
            if ($report_type == 0) {
                // 日报统计
                $statisticals = (new Daily)->dailyStatistical($inputs);
            } elseif ($report_type == 1) {
                // 周报统计
                $statisticals = (new Daily)->weeklyStatistical($inputs);
            }
            return ['code' => 1, 'message' => '统计成功', 'data' => $statisticals];
        }
    }

    /**
     * 修改日报
     * @Author: qinjintian
     * @Date:   2019-02-28
     **/
    public function update()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 1:
                // 获取日报表单数据
                return $this->getDailyFormData($inputs);
                break;
            default:
                // 修改日报
                return $this->editDaily($inputs);
        }
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function getDailyFormData(array $inputs): array
    {
        if (!isset($inputs['daily_id'])) {
            return ['code' => 0, 'message' => '缺少日报ID参数，请检查'];
        }

        $daily = Daily::where('id', $inputs['daily_id'])->first();
        if (!$daily) {
            return ['code' => 0, 'message' => '日报不存在，请检查'];
        }

        $last_daily_setting = DailySetting::where('id', $daily['daily_setting_id'])->first();
        $daily_types = $last_daily_setting ? $last_daily_setting->dailyTypes()->get() : [];
        $daily_data = [];
        $daily_data['id'] = $daily['id'];
        $daily_data['date'] = $daily['date'];
        $daily_data['daily_setting_id'] = $daily['daily_setting_id'];
        $daily_data['daily_content'] = $daily['daily_content'];
        return ['code' => 1, 'message' => 'success', 'data' => ['daily_types' => $daily_types, 'daily' => $daily_data]];
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function editDaily(array $inputs): array
    {
        $rules = [
            'daily_id' => 'required|integer|min:1',
            'daily_content' => 'required|array|min:1'
        ];

        $attributes = [
            'daily_id' => '日报ID',
            'daily_content' => '日报内容',
        ];

        $messages = [];

        $validator = validator($inputs, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        $daily = Daily::where('id', $inputs['daily_id'])->first();

        if (!$daily) {
            return ['code' => 0, 'message' => '日报不存在，请检查'];
        }

        if ($daily['user_id'] != auth()->id()) {
            return ['code' => 0, 'message' => '这不是你的日报，不能够修改，请检查'];
        }

        // 验证内容字段
        $check_result = $this->checkDailyFields($daily['daily_setting_id'], $inputs['daily_content']);
        if ($check_result['code'] != 1) {
            // 验证不通过
            return $check_result;
        }

        $daily->daily_content = serialize($inputs['daily_content']);
        $save_result = $daily->save();
        if ($save_result) {
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('日报管理', '修改了[' . $daily['date'] . ']的日报');
        } else {
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 复制日报
     * @Author: qinjintian
     * @Date:   2019-02-28
     **/
    public function copy()
    {
        $daily = Daily::where('user_id', auth()->id())->orderBy('id', 'DESC')->first();
        if (!$daily) {
            return ['code' => 0, 'message' => '该用户没有写过日报'];
        }

        $last_daily_setting = DailySetting::where('id', $daily['daily_setting_id'])->first();
        $daily_types = $last_daily_setting ? $last_daily_setting->dailyTypes()->get() : [];
        $daily_data = [];
        $daily_data['date'] = $daily['date'];
        $daily_data['daily_setting_id'] = $daily['daily_setting_id'];
        $daily_data['daily_content'] = $daily['daily_content'];
        return ['code' => 1, 'message' => 'success', 'data' => ['daily_types' => $daily_types, 'daily' => $daily_data]];
    }
}

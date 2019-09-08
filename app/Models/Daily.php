<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Daily extends Model
{

    //
    protected $table = 'dailys';

    protected $fillable = ['year', 'month', 'date', 'user_id', 'dept_id', 'daily_setting_id', 'daily_content', 'status'];

    // 获取日报内容
    public function getDailyContentAttribute($value)
    {
        return unserialize($value);
    }

    // 获得此日报所属的用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 获得此日报所属的部门
    public function dept()
    {
        return $this->belongsTo(Dept::class, 'dept_id', 'id');
    }

    // 保存日报信息
    public function storeDaily($data = [])
    {
        if (auth()->user()->report_id == 1) { // 写日报
            $daily = $this->where('user_id', $data['user_id'])->where('date', $data['date'])->first();
        } elseif (auth()->user()->report_id == 2) { // 写周报
            $weeks = getWeeks(); // 指定日期所在周的周一和周日日期
            $daily = Daily::where('user_id', auth()->id())->whereBetween('date', [$weeks['monday_date'], $weeks['sunday_date']])->first();
        }

        if (empty($daily)) {
            $daily = $this->create($data);
        } else {
            $daily = $daily->update($data);
        };
        return $daily;
    }

    // 我的日报
    public function queryMyDailys($inputs = [])
    {
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            $start_time = date('Y-m-d', strtotime('-1 month'));
            $end_time = date('Y-m-d', time());
        }

        $user_info = auth()->user();
        // 如果是写周报的用户
        if ($user_info->report_id == 2) {
            $start_time = getWeeks($start_time)['monday_date']; // 从搜索日期所在周的周一开始检索数据
            $end_time = getWeeks($end_time)['sunday_date']; // 从搜索日期所在周的周日结束检索数据
        }

        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $querys = $this->where('user_id', auth()->id())
            ->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
                $query->whereBetween('date', [$start_time, $end_time]);
            })->select(['id', 'date', 'created_at', 'updated_at']);
        $my_dailys = $querys->orderBy('date', 'DESC')
            ->get()
            ->keyBy('date')
            ->toArray();

        $data = []; // 主体数据
        $records_filtered = 0; // 符合条件的报表数
        // 如果是写周报
        if ($user_info->report_id == 2) {
            $limit_dates = prDates($start_time, $end_time);
            $full_week_dates = [];
            foreach ($limit_dates as $key => $val) {
                $full_week_dates[] = getWeeks($val);
            }

            $records_filtered = count($full_week_dates) > 0 ? count($full_week_dates) / 7 : $records_filtered;

            foreach ($full_week_dates as $key => $val) {
                $is_today = 0;
                $today = date('Y-m-d');
                if ($today >= $val['monday_date'] && $today <= $val['sunday_date']) {
                    $is_today = 1;
                }

                $weeklys = [
                    'id' => 0, 'date' => $val['monday_date'] . '至' . $val['sunday_date'],
                    'report_id' => 2, 'report_name' => '周报', 'inside_date' => $val['friday_date'],
                    'created_at' => '--', 'updated_at' => '--', 'is_today' => $is_today
                ];

                $user_weekly = isset($my_dailys[$val['today']]) ? $my_dailys[$val['today']] : null;
                if ($user_weekly) {
                    $user_weekly['date'] = $val['monday_date'] . '至' . $val['sunday_date'];
                    $user_weekly['report_id'] = 2;
                    $user_weekly['report_name'] = '周报';
                    $user_weekly['inside_date'] = $val['friday_date'];
                    $user_weekly['is_today'] = $is_today;
                    $weeklys = $user_weekly;
                }

                $inside_date_weekly = $data[$val['monday_date']] ?? null;
                if (!$inside_date_weekly || $inside_date_weekly['id'] < 1) {
                    $data[$val['monday_date']] = $weeklys;
                }
            }
            $data = collect($data)->sortByDesc('inside_date')->values()->all();
        } elseif (in_array($user_info->report_id, [0, 1])) {
            // 写日报的就按照工作日组合数据
            $records_filtered = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->count();
            $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->orderBy('date', 'DESC')
                ->skip($start)
                ->take($length)
                ->pluck('date');

            $today = date('Y-m-d');
            foreach ($working_days as $key => $val) {
                $is_today = $today == $val ? 1 : 0;
                $temp_data = [
                    'id' => 0, 'date' => $val, 'report_id' => $user_info->report_id, 'report_name' => '日报',
                    'inside_date' => $val, 'created_at' => '--', 'updated_at' => '--', 'is_today' => $is_today
                ];
                if (isset($my_dailys[$val])) {
                    $my_dailys[$val]['is_today'] = $is_today;
                    $data[$key] = $my_dailys[$val];
                } else {
                    $data[$key] = $temp_data;
                }
            }
        }

        return ['records_filtered' => $records_filtered, 'datalist' => $data];
    }

    // 我的部门日报
    public function queryMyDeptDailys($inputs = [])
    {
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            $start_time = date('Y-m-d', strtotime('-1 month'));
            $end_time = date('Y-m-d', time());
        }

        $real_start_time = $start_time;
        $real_end_time = $end_time;

        $start_time = getWeeks($start_time)['monday_date']; // 从搜索日期所在周的周一开始检索数据
        $end_time = getWeeks($end_time)['sunday_date']; // 从搜索日期所在周的周日结束检索数据

        $keyword = $inputs['keyword'] ?? '';
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $user = auth()->user();
        $querys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->where('dept_id', $user->dept_id)
            ->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
                $query->whereBetween('date', [$start_time, $end_time]);
            })->whereHas('user', function ($query) use ($keyword) {
                $query->where('status', 1)
                    ->when($keyword, function ($query) use ($keyword) {
                        $query->where('realname', 'like', '%' . $keyword . '%');
                    });
            });
        $dailys = $querys->orderBy('date', 'DESC')
            ->select(['id', 'date', 'created_at', 'updated_at', 'user_id', 'dept_id'])
            ->get();

        $daily_report_users = User::with(['contracts' => function ($query) {
            $query->select(['id', 'user_id', 'entry_date']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->where('report_id', '>', 0)
            ->where('status', 1)
            ->where('dept_id', $user->dept_id)
            ->when($keyword, function ($query) use ($keyword) {
                $query->where('realname', 'like', '%' . $keyword . '%');
            })->get(['id', 'realname', 'dept_id', 'report_id']);

        $limit_dates = prDates($start_time, $end_time);
        $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->orderBy('date', 'DESC')->pluck('date')->toArray();
        $written_every_day_dailys = $full_week_dates = [];
        foreach ($limit_dates as $key => $val) {
            $full_week_dates[] = getWeeks($val);
            foreach ($dailys as $keys => $vals) {
                if ($vals['date'] == $val) {
                    $written_every_day_dailys[$val][$vals['user_id']] = ['id' => $vals['id'], 'date' => $vals['date'], 'user_id' => $vals['user_id'], 'realname' => $vals['user']['realname'], 'dept_name' => $vals['dept']['name'], 'created_at' => ($vals['created_at'])->toDateTimeString(), 'updated_at' => ($vals['updated_at'])->toDateTimeString()];
                }
            }
        }

        $every_day_dailys = $every_weeklys = [];
        foreach ($full_week_dates as $key => $val) {
            foreach ($daily_report_users as $keys => $vals) {
                $user_daily = isset($written_every_day_dailys[$val['today']][$vals['id']]) ? $written_every_day_dailys[$val['today']][$vals['id']] : null;

                // 周报是周一至周日都可以写
                if ($vals['report_id'] == 2) {
                    $weeklys = [
                        'id' => 0, 'date' => $val['monday_date'] . '至' . $val['sunday_date'],
                        'user_id' => $vals['id'], 'realname' => $vals['realname'], 'dept_name' => $vals['dept']['name'],
                        'report_id' => 2, 'report_name' => '周报', 'inside_date' => $val['friday_date'], 'created_at' => '--', 'updated_at' => '--'
                    ];
                    if ($user_daily) {
                        $user_daily['date'] = $val['monday_date'] . '至' . $val['sunday_date'];
                        $user_daily['report_id'] = 2;
                        $user_daily['report_name'] = '周报';
                        $user_daily['inside_date'] = $val['friday_date'];
                        $weeklys = $user_daily;
                    }

                    $inside_date_weekly = $every_weeklys[$val['friday_date']][$vals['id']] ?? null;
                    if (!$inside_date_weekly || $inside_date_weekly['id'] < 1) {
                        $every_weeklys[$val['friday_date']][$vals['id']] = $weeklys;
                    }
                }

                // 只有工作日才需要写日报
                if ($vals['report_id'] == 1 && in_array($val['today'], $working_days)) {
                    if ($val['today'] < $real_start_time || $val['today'] > $real_end_time) {
                        continue;
                    }

                    $daily_report = [
                        'id' => 0, 'date' => $val['today'], 'user_id' => $vals['id'], 'realname' => $vals['realname'],
                        'dept_name' => $vals['dept']['name'], 'report_id' => 1, 'report_name' => '日报',
                        'inside_date' => $val['today'], 'created_at' => '--', 'updated_at' => '--'
                    ];
                    if ($user_daily) {
                        $user_daily['report_id'] = 1;
                        $user_daily['report_name'] = '日报';
                        $user_daily['inside_date'] = $val['today'];
                        $daily_report = $user_daily;
                    }
                    $every_day_dailys[] = $daily_report;
                }
            }
        }

        $every_day_dailys = collect($every_day_dailys)->sortByDesc('inside_date')->values()->all();

        // 加上周报数据
        $every_day_dailys_key = 0;
        $check_every_weekly_repeats = [];
        if (count($every_weeklys) > 0) {
            if (count($every_day_dailys) > 0) {
                foreach ($full_week_dates as $key => $val) {
                    if (isset($every_weeklys[$val['friday_date']]) && !in_array($val['friday_date'], $check_every_weekly_repeats)) {
                        array_splice($every_day_dailys, ($key + $every_day_dailys_key), 0, $every_weeklys[$val['friday_date']]);
                        array_push($check_every_weekly_repeats, $val['friday_date']);
                        $every_day_dailys_key += count($every_weeklys[$val['friday_date']]);
                    }
                }
            } else {
                foreach ($every_weeklys as $key2 => $val2) {
                    foreach ($val2 as $key3 => $val3) {
                        array_push($every_day_dailys, $val3);
                    }
                }
            }
        }
        return ['records_filtered' => count($every_day_dailys), 'datalist' => array_slice($every_day_dailys, $start, $length)];
    }

    // 日报汇总列表
    public function queryDailySummary($inputs = [])
    {
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            $start_time = date('Y-m-d', strtotime('-1 month'));
            $end_time = date('Y-m-d', time());
        }

        $real_start_time = $start_time;
        $real_end_time = $end_time;

        $start_time = getWeeks($start_time)['monday_date']; // 从搜索日期所在周的周一开始检索数据
        $end_time = getWeeks($end_time)['sunday_date']; // 从搜索日期所在周的周日结束检索数据

        $keyword = $inputs['keyword'] ?? '';
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $user = auth()->user();
        $querys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->whereBetween('date', [$start_time, $end_time]);
        })->whereHas('user', function ($query) use ($keyword) {
            $query->where('status', 1)
                ->when($keyword, function ($query) use ($keyword) {
                    $query->where('realname', 'like', '%' . $keyword . '%');
                });
        });
        $dailys = $querys->orderBy('date', 'DESC')
            ->select(['id', 'date', 'created_at', 'updated_at', 'user_id', 'dept_id'])
            ->get();

        $daily_report_users = User::with(['contracts' => function ($query) {
            $query->select(['id', 'user_id', 'entry_date']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->where('report_id', '>', 0)
            ->where('status', 1)
            ->when($keyword, function ($query) use ($keyword) {
                $query->where('realname', 'like', '%' . $keyword . '%');
            })->get(['id', 'realname', 'dept_id', 'report_id']);

        $limit_dates = prDates($start_time, $end_time);
        $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->orderBy('date', 'DESC')->pluck('date')->toArray();
        $written_every_day_dailys = $full_week_dates = [];
        foreach ($limit_dates as $key => $val) {
            $full_week_dates[] = getWeeks($val);
            foreach ($dailys as $keys => $vals) {
                if ($vals['date'] == $val) {
                    $written_every_day_dailys[$val][$vals['user_id']] = ['id' => $vals['id'], 'date' => $vals['date'], 'user_id' => $vals['user_id'], 'realname' => $vals['user']['realname'], 'dept_name' => $vals['dept']['name'], 'created_at' => ($vals['created_at'])->toDateTimeString(), 'updated_at' => ($vals['updated_at'])->toDateTimeString()];
                }
            }
        }

        $every_day_dailys = $every_weeklys = [];
        foreach ($full_week_dates as $key => $val) {
            foreach ($daily_report_users as $keys => $vals) {
                $user_daily = isset($written_every_day_dailys[$val['today']][$vals['id']]) ? $written_every_day_dailys[$val['today']][$vals['id']] : null;

                // 周报是周一至周日都可以写
                if ($vals['report_id'] == 2) {
                    $weeklys = [
                        'id' => 0, 'date' => $val['monday_date'] . '至' . $val['sunday_date'],
                        'user_id' => $vals['id'], 'realname' => $vals['realname'], 'dept_name' => $vals['dept']['name'],
                        'report_id' => 2, 'report_name' => '周报', 'inside_date' => $val['friday_date'], 'created_at' => '--', 'updated_at' => '--'
                    ];

                    if ($user_daily) {
                        $user_daily['date'] = $val['monday_date'] . '至' . $val['sunday_date'];
                        $user_daily['report_id'] = 2;
                        $user_daily['report_name'] = '周报';
                        $user_daily['inside_date'] = $val['friday_date'];
                        $weeklys = $user_daily;
                    }

                    $inside_date_weekly = $every_weeklys[$val['friday_date']][$vals['id']] ?? null;
                    if (!$inside_date_weekly || $inside_date_weekly['id'] < 1) {
                        $every_weeklys[$val['friday_date']][$vals['id']] = $weeklys;
                    }
                }

                // 只有工作日才需要写日报
                if ($vals['report_id'] == 1 && in_array($val['today'], $working_days)) {
                    if ($val['today'] < $real_start_time || $val['today'] > $real_end_time) {
                        continue;
                    }

                    $daily_report = [
                        'id' => 0, 'date' => $val['today'], 'user_id' => $vals['id'], 'realname' => $vals['realname'],
                        'dept_name' => $vals['dept']['name'], 'report_id' => 1, 'report_name' => '日报',
                        'inside_date' => $val['today'], 'created_at' => '--', 'updated_at' => '--'
                    ];
                    if ($user_daily) {
                        $user_daily['report_id'] = 1;
                        $user_daily['report_name'] = '日报';
                        $user_daily['inside_date'] = $val['today'];
                        $daily_report = $user_daily;
                    }
                    $every_day_dailys[] = $daily_report;
                }
            }
        }

        $every_day_dailys = collect($every_day_dailys)->sortByDesc('inside_date')->values()->all();

        // 加上周报数据
        $every_day_dailys_key = 0;
        $check_every_weekly_repeats = [];
        if (count($every_weeklys) > 0) {
            if (count($every_day_dailys) > 0) {
                foreach ($full_week_dates as $key => $val) {
                    if (isset($every_weeklys[$val['friday_date']]) && !in_array($val['friday_date'], $check_every_weekly_repeats)) {
                        array_splice($every_day_dailys, ($key + $every_day_dailys_key), 0, $every_weeklys[$val['friday_date']]);
                        array_push($check_every_weekly_repeats, $val['friday_date']);
                        $every_day_dailys_key += count($every_weeklys[$val['friday_date']]);
                    }
                }
            } else {
                foreach ($every_weeklys as $key2 => $val2) {
                    foreach ($val2 as $key3 => $val3) {
                        array_push($every_day_dailys, $val3);
                    }
                }
            }
        }
        return ['records_filtered' => count($every_day_dailys), 'datalist' => array_slice($every_day_dailys, $start, $length)];
    }

    // 日报统计
    public function dailyStatistical($inputs = [])
    {
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            $start_time = date('Y-m-d', strtotime('-1 month'));
            $end_time = date('Y-m-d', time());
        }

        $dept_id = $inputs['dept_id'] ?? '';
        // 取出写日报和周报的用户
        $users = User::with(['dept' => function ($query) {
            $query->select(['id', 'name']);
        }, 'contracts' => function ($query) {
            $query->select('user_id', 'entry_date');
        }])->where('report_id', 1)
            ->where('status', 1)
            ->when($dept_id, function ($query) use ($dept_id) {
                $query->where('dept_id', $dept_id);
            })->get(['id', 'realname', 'dept_id', 'report_id'])->keyBy('id');

        $all_user_ids = $users ? $users->pluck('id') : [];
        $leaves = ApplyAttendanceDetail::where([
            ['type', '=', 1],
            ['time_str', '=', 1]
        ])->whereBetween('date', [$start_time, $end_time])
            ->when(count($all_user_ids) > 0, function ($query) use ($all_user_ids) {
                $query->whereIn('user_id', $all_user_ids);
            })->get(['user_id', 'date', 'time_str']);

        $querys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->whereBetween('date', [$start_time, $end_time]);
        });
        $dailys = $querys->orderBy('date', 'DESC')
            ->select(['id', 'date', 'created_at', 'updated_at', 'user_id', 'dept_id', 'status'])
            ->get();

        $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->orderBy('date', 'DESC')->pluck('date');
        $working_days_count = count($working_days);
        $statisticals = [];
        foreach ($users as $key => $val) {
            $temp_working_days_count = $working_days_count;
            if (!empty($val->contracts->entry_date) && strtotime($val->contracts->entry_date) >= strtotime($start_time) && strtotime($val->contracts->entry_date) <= strtotime($end_time)) {
                $interval_dates = prDates($val->contracts->entry_date, $end_time);
                $temp_working_days = $working_days ? $working_days->toArray() : [];
                foreach ($temp_working_days as $wkey => $date) {
                    if (!in_array($date, $interval_dates)) {
                        unset($temp_working_days[$wkey]);
                    }
                }
                $temp_working_days_count = count($temp_working_days);
            }
            $leave_days_count = 0;
            foreach ($leaves as $keys => $vals) {
                if ($vals['user_id'] == $val['id']) {
                    $leave_days_count += 1;
                }
            }

            $normal = 0;
            foreach ($dailys as $keyss => $valss) {
                if ($valss['user_id'] == $val['id'] && $valss['status'] == 0) {
                    $normal += 1;
                }
            }
            $total = $temp_working_days_count - $leave_days_count; // 应写日报数 = 本月工作日天数 - 请假天数
            $temp_data = ['dept_name' => $val['dept']['name'], 'realname' => $val['realname'], 'total' => $total, 'normal' => $normal, 'lack' => $total - $normal];
            $statisticals[$key] = $temp_data;
        }
        return $statisticals;
    }

    // 周报统计
    public function weeklyStatistical($inputs = [])
    {
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            $start_time = date('Y-m-d', strtotime('-1 month'));
            $end_time = date('Y-m-d', time());
        }

        $start_time = getWeeks($start_time)['monday_date']; // 从搜索日期所在周的周一开始检索数据
        $end_time = getWeeks($end_time)['sunday_date']; // 从搜索日期所在周的周日结束检索数据

        $dept_id = $inputs['dept_id'] ?? '';
        // 取出写日报和周报的用户
        $users = User::with(['dept' => function ($query) {
            $query->select(['id', 'name']);
        }, 'contracts' => function ($query) {
            $query->select('user_id', 'entry_date');
        }])->where('report_id', 2)
            ->where('status', 1)
            ->when($dept_id, function ($query) use ($dept_id) {
                $query->where('dept_id', $dept_id);
            })->get(['id', 'realname', 'dept_id', 'report_id'])->keyBy('id');

        $all_user_ids = $users ? $users->pluck('id') : [];

        $leaves = ApplyAttendanceDetail::where([
            ['type', '=', 1],
            ['time_str', '=', 1]
        ])->whereBetween('date', [$start_time, $end_time])
            ->when(count($all_user_ids) > 0, function ($query) use ($all_user_ids) {
                $query->whereIn('user_id', $all_user_ids);
            })->get(['user_id', 'date', 'time_str']);

        $querys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->whereBetween('date', [$start_time, $end_time]);
        })->whereIn('user_id', $all_user_ids);
        $weeklys = $querys->orderBy('date', 'DESC')
            ->select(['id', 'date', 'created_at', 'updated_at', 'user_id', 'dept_id', 'status'])
            ->get();

        $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->pluck('date');

        $limit_dates = prDates($start_time, $end_time);
        $full_week_dates = [];
        foreach ($limit_dates as $key => $val) {
            $full_week_dates[] = getWeeks($val);
        }

        $limit_weeklys = collect($full_week_dates)->keyBy('monday_date')->values()->all();
        $weekly_count = count($limit_weeklys); // 此期间有多少周

        $weekly_data = [];
        foreach ($users as $key => $val) {
            $total = $weekly_count; // 应写周报数
            $normal = 0; // 正常提交次数
            foreach ($limit_weeklys as $key1 => $val1) {
                // 检查当前周该用户有没有入职
                if (!empty($val->contracts->entry_date) && strtotime($val->contracts->entry_date) > strtotime($val1['monday_date'])) {
                    $total--; // 周二之前没有入职的就不用写周报
                }

                // 检查本周是否提交了周报
                foreach ($weeklys as $key2 => $val2) {
                    if ($val2['user']['id'] == $val['id'] && $val2['date'] >= $val1['monday_date'] && $val2['date'] <= $val1['sunday_date'] && $val2['status'] == 0) {
                        $normal++;
                    }
                }

                // 查找出本周工作日的日期
                $this_week_working_days = [];
                foreach ($working_days as $key4 => $val4) {
                    if ($val4 >= $val1['monday_date'] && $val4 <= $val1['sunday_date']) {
                        array_push($this_week_working_days, $val4);
                    }
                }

                // 如果这周没有工作日那就不用写周报
                if (count($this_week_working_days) < 1) {
                    $total--;
                    continue ;
                }

                // 检查当前用户本周所有工作日内有没有全部请假
                $this_week_leave_count = 0;
                foreach ($leaves as $key3 => $val3) {
                    if ($val3['user_id'] == $val['id'] && in_array($val3['date'], $this_week_working_days)) {
                        $this_week_leave_count = $this_week_leave_count + $val3['time_str'];
                    }
                }

                // 如果该用户这周所有工作日都请假了，那就不需要写日报
                if ($this_week_leave_count >= count($this_week_working_days)) {
                    $total--;
                }
            }

            $temp_data = ['dept_name' => $val['dept']['name'], 'realname' => $val['realname'], 'total' => $total, 'normal' => $normal, 'lack' => $total - $normal];
            $weekly_data[$key] = $temp_data;
        }
        return $weekly_data;
    }

    // 日报统计详情数据(导出)
    public function exportDailyDetail($inputs)
    {
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            $start_time = date('Y-m-d', strtotime('-1 month'));
            $end_time = date('Y-m-d', time());
        }

        $dept_id = $inputs['dept_id'] ?? '';
        $users = User::with(['dept' => function ($query) {
            $query->select(['id', 'name']);
        }, 'contracts' => function ($query) {
            $query->select('user_id', 'entry_date');
        }])->where('report_id', 1)
            ->where('status', 1)
            ->when($dept_id, function ($query) use ($dept_id) {
                $query->where('dept_id', $dept_id);
            })->get(['id', 'realname', 'dept_id'])->keyBy('id');

        $user_ids = $users ? $users->pluck('id') : [];
        $leaves = ApplyAttendanceDetail::where([
            ['type', '=', 1],
            ['time_str', '=', 1]
        ])->whereBetween('date', [$start_time, $end_time])
            ->when(count($user_ids) > 0, function ($query) use ($user_ids) {
                $query->whereIn('user_id', $user_ids);
            })->get(['user_id', 'date', 'time_str']);

        $dailys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->whereBetween('date', [$start_time, $end_time])
            ->orderBy('date', 'DESC')
            ->select(['id', 'date', 'created_at', 'user_id', 'dept_id', 'status'])
            ->get();
        $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->orderBy('date', 'DESC')->pluck('date');
        $working_days_count = count($working_days);
        $dailys_group_by_date = [];
        $temp_dailys = $dailys ? $dailys->toArray() : [];
        foreach ($working_days as $gkey => $gval) {
            foreach ($temp_dailys as $lkey => $lval) {
                if ($lval['date'] == $gval) {
                    $dailys_group_by_date[$gval][$lval['user_id']] = $lval;
                    unset($temp_dailys[$lkey]);
                }
            }
        }
        unset($temp_dailys);

        $days = self::getDate($start_time, $end_time);

        $temp_working_days = $working_days ? $working_days->toArray() : [];
        $statisticals = [];
        $today_time = strtotime(date('Y-m-d'));
        foreach ($users as $ukey => $user) {
            $temp_data = ['user_id' => $user->id, 'realname' => $user->realname, 'dept_name' => $user->dept->name];
            foreach ($days as $dkey => $day) {
                // 0 => 正常   1 => 迟  2 => 缺   3 => 假日   4 => 未入职  5 => ---(未到该日期)  6 => 请假
                $situation = '正常';
                if (in_array($day['date'], $temp_working_days) && !isset($dailys_group_by_date[$day['date']][$user->id])) {
                    if (strtotime($day['date']) > $today_time) {
                        $situation = '---';
                    } else {
                        $situation = '缺';
                    }
                }

                if (isset($dailys_group_by_date[$day['date']][$user->id]) && $dailys_group_by_date[$day['date']][$user->id]['status'] == 1) {
                    $situation = '迟';
                }

                if (strtotime($day['date']) < strtotime($user->contracts->entry_date)) {
                    $situation = '未入职';
                }

                foreach ($leaves as $lkey => $lval) {
                    if ($lval['date'] == $day['date'] && $lval['user_id'] == $user['id']) {
                        $situation = '请假';
                    }
                }

                if (!in_array($day['date'], $temp_working_days)) {
                    $situation = '假日';
                }

                $temp_data['everyday'][$day['date']] = $situation;
            }
            $statisticals[] = $temp_data;
        }
        return ['everydays' => $days, 'statisticals' => $statisticals];
    }

    //日工作汇报统计
    public function exportDailyEvery($inputs)
    {
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';

        if (empty($start_time) || empty($end_time)) {
            $start_time = date('Y-m-d', strtotime('-1 month'));
            $end_time = date('Y-m-d', time());
        }

        $dept_id = $inputs['dept_id'] ?? '';
        //用户信息
        $users = User::with(['dept' => function ($query) {
            $query->select(['id', 'name']);
        }, 'contracts' => function ($query) {
            $query->select('user_id', 'entry_date');
        }])->where('status', 1)
            ->whereIn('report_id', [1, 2, 3])
            ->when(!empty($dept_id), function ($query) use ($dept_id) {
                $query->where('dept_id', $dept_id);
            })->get(['id', 'realname', 'dept_id'])->keyBy('id');
        $user_ids = $users ? $users->pluck('id') : [];
        //出勤申请详情
        $leaves = ApplyAttendanceDetail::where([
            ['type', '=', 1],
            ['time_str', '=', 1]
        ])->whereBetween('date', [$start_time, $end_time])
            ->when(count($user_ids) > 0, function ($query) use ($user_ids) {
                $query->whereIn('user_id', $user_ids);
            })->get(['user_id', 'date']);
        //日报内容
        $dailys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->whereBetween('date', [$start_time, $end_time])
            ->orderBy('date', 'DESC')
            ->select(['id', 'date', 'daily_content', 'user_id', 'dept_id', 'status'])
            ->get();
        $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->orderBy('date', 'DESC')->pluck('date');
        $working_days_count = count($working_days);
        $dailys_group_by_date = [];
        $temp_dailys = $dailys ? $dailys->toArray() : [];
        foreach ($working_days as $gkey => $gval) {
            foreach ($temp_dailys as $lkey => $lval) {
                if ($lval['date'] == $gval) {
                    $dailys_group_by_date[$gval][$lval['user_id']] = $lval;
                    unset($temp_dailys[$lkey]);
                }
            }
        }
        unset($temp_dailys);

        $days = self::getDate($start_time, $end_time);
        $temp_working_days = $working_days ? $working_days->toArray() : [];//工作日
        $statisticals = [];
        foreach ($users as $ukey => $user) {
            $temp_data = ['user_id' => $user->id, 'realname' => $user->realname, 'dept_name' => $user->dept->name, 'working_days_count' => $working_days_count];
            foreach ($days as $dkey => $day) {
                $situation = 0;
                if (in_array($day['date'], $temp_working_days) && isset($dailys_group_by_date[$day['date']][$user->id])) {
                    foreach ($dailys_group_by_date[$day['date']][$user->id]['daily_content'] as $content) {
                        foreach ($content as $value) {
                            $situation += $value['time_cost'];
                        }
                    }
                }
                //未入职
                if (strtotime($day['date']) < strtotime($user->contracts->entry_date)) {
                    $situation = '未入职';
                }
                //请假
                foreach ($leaves as $lkey => $lval) {
                    if ($lval['date'] == $day['date'] && $lval['user_id'] == $user['id']) {
                        $situation = '休假';
                    }
                }
                //假期
                if (!in_array($day['date'], $temp_working_days)) {
                    $situation = '休假';
                }
                $temp_data['everyday'][$day['date']] = $situation;
            }
            $total_time = 0;
            foreach ($temp_data['everyday'] as $time) {
                if (is_numeric($time)) {
                    $total_time += $time;
                }
            }
            $temp_data['total_time'] = $total_time;
            $temp_data['average_work_time'] = sprintf('%.2f', $temp_data['total_time'] / $temp_data['working_days_count'] / 60);//平均每天工作时间/小时
            $statisticals[] = $temp_data;

        }
        return ['everydays' => $days, 'statisticals' => $statisticals];
    }

    // 日报统计详细数据
    public function dailyStatisticalDetaill($inputs = [])
    {
        $keyword = $inputs['keyword'] ?? '';
        $dept_id = $inputs['dept_id'] ?? '';
        $year_month = $inputs['year_month'];
        $users = User::with(['dept' => function ($query) {
            $query->select(['id', 'name']);
        }, 'contracts' => function ($query) {
            $query->select('user_id', 'entry_date');
        }])->where('report_id', 1)
            ->where('status', 1)
            ->when($dept_id, function ($query) use ($dept_id) {
                $query->where('dept_id', $dept_id);
            })->when($keyword, function ($query) use ($keyword) {
                $query->where('realname', 'like', '%' . $keyword . '%');
            })->get(['id', 'realname', 'dept_id'])->keyBy('id');

        $user_ids = $users ? $users->pluck('id') : [];
        $leaves = ApplyAttendanceDetail::where([
            ['type', '=', 1],
            ['time_str', '=', 1]
        ])->where('date', 'like', $year_month . '%')
            ->when(count($user_ids) > 0, function ($query) use ($user_ids) {
                $query->whereIn('user_id', $user_ids);
            })->get(['user_id', 'date', 'time_str']);

        $dailys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->when($year_month, function ($query) use ($year_month) {
            $query->where('date', 'like', $year_month . '%');
        })->whereHas('user', function ($query) use ($keyword) {
            $query->when($keyword, function ($query) use ($keyword) {
                $query->where('realname', 'like', '%' . $keyword . '%');
            });
        })->orderBy('date', 'DESC')
            ->select(['id', 'date', 'created_at', 'user_id', 'dept_id', 'status'])
            ->get();

        $working_days = Holiday::where('type', 0)->where('date', 'like', $year_month . '%')->orderBy('date', 'DESC')->pluck('date');
        $working_days_count = count($working_days);

        $dailys_group_by_date = [];
        $temp_dailys = $dailys ? $dailys->toArray() : [];
        foreach ($working_days as $gkey => $gval) {
            foreach ($temp_dailys as $lkey => $lval) {
                if ($lval['date'] == $gval) {
                    $dailys_group_by_date[$gval][$lval['user_id']] = $lval;
                    unset($temp_dailys[$lkey]);
                }
            }
        }
        unset($temp_dailys);

        $days = self::getDateForMonth($inputs['year_month']);

        $temp_working_days = $working_days ? $working_days->toArray() : [];
        $statisticals = [];
        $today_time = strtotime(date('Y-m-d'));
        foreach ($users as $ukey => $user) {
            $temp_data = ['user_id' => $user->id, 'realname' => $user->realname, 'dept_name' => $user->dept->name];
            foreach ($days as $dkey => $day) {
                // 0 => 正常   1 => 迟  2 => 缺   3 => 假日   4 => 未入职  5 => ---(未到该日期)  6 => 请假
                $situation = '正常';
                if (in_array($day['date'], $temp_working_days) && !isset($dailys_group_by_date[$day['date']][$user->id])) {
                    if (strtotime($day['date']) > $today_time) {
                        $situation = '---';
                    } else {
                        $situation = '缺';
                    }
                }

                if (isset($dailys_group_by_date[$day['date']][$user->id]) && $dailys_group_by_date[$day['date']][$user->id]['status'] == 1) {
                    $situation = '迟';
                }

                if (strtotime($day['date']) < strtotime($user->contracts->entry_date)) {
                    $situation = '未入职';
                }

                foreach ($leaves as $lkey => $lval) {
                    if ($lval['date'] == $day['date'] && $lval['user_id'] == $user['id']) {
                        $situation = '请假';
                    }
                }

                if (!in_array($day['date'], $temp_working_days)) {
                    $situation = '假日';
                }

                $temp_data['everyday'][$day['date']] = $situation;
            }
            $statisticals[] = $temp_data;
        }
        return ['everydays' => $days, 'statisticals' => $statisticals];
    }

    /**
     * 周报统计详情
     */
    public function weeklyStatisticalDetaill($inputs = [])
    {
        $keyword = $inputs['keyword'] ?? '';
        $dept_id = $inputs['dept_id'] ?? '';
        $year_month = $inputs['year_month'];
        $month_day_count = yearMonthDays(substr($year_month, 0, 4), substr($year_month, 5, 2)); // 指定月份的天数
        $early_month = $year_month . '-' . '01'; // 月初第一天
        $end_of_month = $year_month . '-' . $month_day_count; // 月末最后一天
        $start_time = getWeeks($early_month)['monday_date']; // 从搜索日期所在周的周一开始检索数据
        $end_time = getWeeks($end_of_month)['sunday_date']; // 从搜索日期所在周的周日结束检索数据

        $users = User::with(['dept' => function ($query) {
            $query->select(['id', 'name']);
        }, 'contracts' => function ($query) {
            $query->select('user_id', 'entry_date');
        }])->where('report_id', 2)
            ->where('status', 1)
            ->when($dept_id, function ($query) use ($dept_id) {
                $query->where('dept_id', $dept_id);
            })->when($keyword, function ($query) use ($keyword) {
                $query->where('realname', 'like', '%' . $keyword . '%');
            })->get(['id', 'realname', 'dept_id'])->keyBy('id');

        $all_user_ids = $users ? $users->pluck('id') : [];
        $leaves = ApplyAttendanceDetail::where([
            ['type', '=', 1],
            ['time_str', '=', 1]
        ])->whereBetween('date', [$start_time, $end_time])
            ->when(count($all_user_ids) > 0, function ($query) use ($all_user_ids) {
                $query->whereIn('user_id', $all_user_ids);
            })->get(['user_id', 'date', 'time_str']);

        $querys = $this->with(['user' => function ($query) {
            $query->select(['id', 'username', 'realname']);
        }, 'dept' => function ($query) {
            $query->select(['id', 'name']);
        }])->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->whereBetween('date', [$start_time, $end_time]);
        })->whereIn('user_id', $all_user_ids);
        $weeklys = $querys->orderBy('date', 'DESC')
            ->select(['id', 'date', 'created_at', 'updated_at', 'user_id', 'dept_id', 'status'])
            ->get();

        $working_days = Holiday::where('type', 0)->whereBetween('date', [$start_time, $end_time])->pluck('date');

        $limit_dates = prDates($start_time, $end_time);
        $full_week_dates = [];
        foreach ($limit_dates as $key => $val) {
            $full_week_dates[] = getWeeks($val);
        }

        $limit_weeklys = collect($full_week_dates)->keyBy('monday_date')->values()->all();
        $weekly_count = count($limit_weeklys); // 此期间有多少周

        $today = date('Y-m-d', time());
        $weekly_datas = [];
        foreach ($users as $key => $val) {
            $temp_weekly_data[$val['id']] = ['user_id' => $val->id, 'realname' => $val->realname, 'dept_name' => $val->dept->name];
            foreach ($limit_weeklys as $key1 => $val1) {
                $situation = '正常';

                // 查找出本周工作日的日期
                $this_week_working_days = [];
                foreach ($working_days as $key2 => $val2) {
                    if ($val2 >= $val1['monday_date'] && $val2 <= $val1['sunday_date']) {
                        array_push($this_week_working_days, $val2);
                    }
                }

                // 当前周未用写日报
                if ($today < $val1['monday_date']) {
                    $situation = '---';
                } else {
                    // 检查本周是否提交了周报
                    $is_lack = false; // 本周周报是否缺写
                    $is_late = false; // 本周是否迟写
                    foreach ($weeklys as $key4 => $val4) {
                        if ($val4['user']['id'] == $val['id'] && $val4['date'] >= $val1['monday_date'] && $val4['date'] <= $val1['sunday_date'] && $val4['status'] == 0) {
                            $is_lack = true; // 为true表示没有缺写
                        } elseif ($val4['user']['id'] == $val['id'] && $val4['date'] >= $val1['monday_date'] && $val4['date'] <= $val1['sunday_date'] && $val4['status'] == 1) {
                            $is_late = true;
                        }
                    }

                    if (!$is_lack && !$is_late) { // 找不到该用户本周的周报，并且也没有补写那就是缺写了
                        $situation = '缺';
                    }

                    if ($is_late) {
                        $situation = '迟';
                    }
                }

                // 检查当前周该用户有没有入职
                if (!empty($val->contracts->entry_date) && strtotime($val->contracts->entry_date) > strtotime($val1['friday_date'])) {
                    $situation = '未入职';
                }

                // 检查当前用户本周所有工作日内有没有全部请假
                $this_week_leave_count = 0;
                foreach ($leaves as $key3 => $val3) {
                    if ($val3['user_id'] == $val['id'] && in_array($val3['date'], $this_week_working_days)) {
                        $this_week_leave_count = $this_week_leave_count + $val3['time_str'];
                    }
                }

                if ($this_week_leave_count >= count($this_week_working_days)) {
                    $situation = '请假';
                }

                // 检查本周是否全部是假期
                if (count($this_week_working_days) < 1) {
                    $situation = '假日';
                }

                $temp_weekly_data[$val['id']]['every_weeklys'][$key1] = [
                    'weekly' => $val1['monday_date'] . '-' . $val1['sunday_date'],
                    'situation' => $situation
                ];
            }
            $weekly_datas = $temp_weekly_data;
        }
        $weekly_datas = collect($weekly_datas)->values()->all();

        // 头部 周
        $every_weeklys = [];
        foreach ($limit_weeklys as $key => $val) {
            array_push($every_weeklys, $val['monday_date'] . '至' . $val['sunday_date']);
        }
        return ['theads' => $every_weeklys, 'tbodys' => $weekly_datas];
    }

    // 获取一个月的每一天
    public static function getDateForMonth($year_month)
    {
        if (empty($year_month)) return false;
        $dates = Holiday::where('date', 'like', $year_month . '%')->get(['date']);
        $days = [];
        $week_array = ['日', '一', '二', '三', '四', '五', '六'];
        foreach ($dates as $key => $val) {
            $days[$key] = ['date' => $val['date'], 'week' => '周' . $week_array[date('w', strtotime($val['date']))]];
        }
        return $days;
    }

    //获取指定期间的每一天
    public function getDate($start_time, $end_time)
    {
        $dates = Holiday::whereBetween('date', [$start_time, $end_time])->get(['date']);
        $days = [];
        $week_array = ['日', '一', '二', '三', '四', '五', '六'];
        foreach ($dates as $key => $val) {
            $days[$key] = ['date' => $val['date'], 'week' => '周' . $week_array[date('w', strtotime($val['date']))]];
        }
        return $days;
    }
}

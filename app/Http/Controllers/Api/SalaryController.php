<?php

namespace App\Http\Controllers\Api;

use App\Models\ApplyAttendanceDetail;
use App\Models\AttendanceStatistic;
use App\Models\Holiday;
use App\Models\HolidayType;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use phpDocumentor\Reflection\DocBlock\Tags\Author;

class SalaryController extends Controller
{
    /**
     * 薪酬管理 -> 创建工资表
     * @Author: qinjintian
     * @Date:   2018-11-19
     */
    public function create()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case '1':
                // 上传社保公积金信息文件
                return $this->uploadShebaoGongjijin($inputs);
                break;
            case '2':
                // 生成并导出工资表
                return $this->calculationSalary($inputs);
                break;
            default:

        }
    }

    /**
     * 上传社保公积金信息文件
     * @param $inputs
     * @return array
     */
    private function uploadShebaoGongjijin($inputs): array
    {
        $rules = ['file' => 'required|file|max:10240'];
        $attributes = ['file' => 'Excel文件'];
        $messages = [
            'file.required' => '请选择Excel文件',
            'file.file' => '上传的必须是有效的Excel文件',
        ];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        if (request()->isMethod('post')) {
            $file = request()->file('file');
            if ($file->isValid()) {
                $original_name = $file->getClientOriginalName(); // 文件原名
                $ext = $file->getClientOriginalExtension();  // 扩展名
                $real_path = $file->getRealPath(); // 临时文件的绝对路径
                $type = $file->getClientMimeType(); // image/jpeg
                if (!in_array($ext, ['xls', 'xlsx'])) {
                    return ['code' => -1, 'message' => '只能上传合法的Excel文件'];
                }
            }
            // 读取上传的Excel文件字段格式是否符合要求
            $file_name = $file->getPathname();
            if (!file_exists($file_name)) {
                return ['code' => 0, 'message' => '文件不存在，请检查'];
            }
            $sheets_data = importExcel($file_name, [0], 1);
            $first_sheet = $sheets_data['data']['sheets'];
            if (count($first_sheet) < 1) {
                return ['code' => 0, 'message' => '社保公积金文档没有任何行信息，请检查'];
            }
            // 验证字段有效性
            foreach ($first_sheet as $key => $val) {
                if(empty($val[0])) continue;
                $vdata = [
                    'employee_id' => trim($val[0]),
                    'year_month' => trim($val[1]),
                    'shebao' => floatval(trim($val[3])),
                    'gongjijin' => floatval(trim($val[4]))
                ];
                $rules = [
                    'employee_id' => 'required|min:1',
                    'year_month' => 'required|date_format:Y-m',
                    'shebao' => 'required|numeric|min:0',
                    'gongjijin' => 'required|numeric|min:0'
                ];
                $attributes = [
                    'employee_id' => '员工编号',
                    'year_month' => '社保年月份',
                    'shebao' => '社保',
                    'gongjijin' => '公积金'
                ];
                $messages = [];
                $validator = validator($vdata, $rules, $messages, $attributes);
                if ($validator->fails()) {
                    return ['code' => -1, 'message' => $vdata['employee_id'] . '.' . trim($val[2]) . ' ' . $validator->errors()->first() . '，请检查'];
                }
                $user_id = User::where('serialnum', trim($val[0]))->value('id');
                if (empty($user_id) || $user_id < 0) {
                    return ['code' => 0, 'message' => '系统中不存在用户【 ' . $vdata['employee_id'] . '.' . trim($val[2]) . '】请检查'];
                }
            }
            $filename = 'shebaogongjijin.' . $ext;
            $fileinfo = $file->move(storage_path('app/public/temps/'), $filename);
            $data = [];
            $data['original_name'] = $original_name;
            $data['file_name'] = $fileinfo->getFilename();
            $data['real_path'] = asset('/storage/temps/' . $fileinfo->getFilename());
            return ['code' => 1, 'message' => '上传成功', 'data' => $data];
        } else {
            return ['code' => 0, 'message' => '非法操作'];
        }
    }

    /**
     * 计算薪资
     * @param $inputs
     * @return array
     */
    private function calculationSalary($inputs): array
    {
        $rules = [
            'shebaogjj_file_name' => 'required',
            'wage_month' => 'required|date|date_format:"Y-m"',
            'performance_month' => 'required|date|date_format:"Y-m"',
            'attendance_month' => 'required|date|date_format:"Y-m"',
            'weekend_work_overtime' => 'required|numeric|min:1',
            'working_day_work_overtime' => 'required|numeric|min:1',
            'holiday_day_work_overtime' => 'required|numeric|min:1',
            'quanqin_shaoyu' => 'required|numeric|min:0',
            'quanqin_shaoyu_money' => 'required|numeric|min:0',
            'quanqin_dayu' => 'required|numeric|min:0',
            'quanqin_dayu_money' => 'required|numeric|min:0',
        ];
        $attributes = [
            'shebaogjj_file_name' => '社保公积金文件名',
            'wage_month' => '工资月份',
            'performance_month' => '绩效月份',
            'attendance_month' => '考勤月份',
            'weekend_work_overtime' => '周末加班费',
            'working_day_work_overtime' => '工作日加班费',
            'holiday_day_work_overtime' => '节假日加班费',
            'quanqin_shaoyu' => '连续全勤少于N月',
            'quanqin_shaoyu_money' => '连续全勤少于N月的奖金',
            'quanqin_dayu' => '连续全勤大于N月',
            'quanqin_dayu_money' => '连续全勤大于N月的奖金',
        ];
        $messages = [
            'performance_month.date_format' => '绩效月份格式必须由 4位数年份-2位数月份 组成',
            'attendance_month.date_format' => '考勤月份格式必须由 4位数年份-2位数月份 组成',
            'wage_month.date_format' => '工资月份格式必须由 4位数年份-2位数月份 组成',
        ];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $inputs['meal_subsidy'] = 0;//去掉餐补 2019-07-04改
        // 读取社保公积金excel文档
        $file_path = storage_path('app/public/temps/' . $inputs['shebaogjj_file_name']);
        if (!file_exists($file_path)) {
            return ['code' => 0, 'message' => '社保公积金信息文档不存在，请检查'];
        }
        try {
            $sheet_data = importExcel($file_path, [0], 1);
            $first_sheet = $sheet_data['data']['sheets'];
        } catch (\Exception $e) {
            return ['code' => 0, 'message' => 'Excel文件读取失败，请检查文件格式是否正确'];
        }
        if (count($first_sheet) < 1) {
            return ['code' => 0, 'message' => '社保公积金文档没有任何行信息，请检查'];
        }
        $user_ids = [];
        $shebao_gongjijin_users = [];
        foreach ($first_sheet as $key => $val) {
            if(empty($val[0])) continue;
            $employee_id = trim($val[0]);
            $user_id = User::where('serialnum', $employee_id)->value('id');
            if (!$user_id) {
                return ['code' => 0, 'message' => '系统不存在员工编号为【' . $employee_id . '】的用户，请检查'];
            }
            if (trim($val[1]) != trim($inputs['wage_month'])) {
                return ['code' => 0, 'message' => '上传的社保公积金文档中用户【' . $employee_id . '.' . trim($val[2]) . '】' . '和所发月份工资的月份格式不匹配，请检查'];
            }
            $user_ids[] = $user_id;
            $shebao_gongjijin_users[$user_id]['id'] = $user_id;
            $shebao_gongjijin_users[$user_id]['realname'] = trim($val[2]);
            $shebao_gongjijin_users[$user_id]['shebao'] = sprintf('%.2f', floatval($val[3]));
            $shebao_gongjijin_users[$user_id]['gongjijin'] = sprintf('%.2f', floatval($val[4]));
        }
        if (count($user_ids) < 1) {
            return ['code' => 0, 'message' => '社保公积金excel文档中没有读取到任何用户信息，请检查'];
        }

        $users = User::whereIn('id', $user_ids)->orderBy('id', 'ASC')->get(['id', 'realname', 'dept_id', 'serialnum']);
        if (count($users) < 0) {
            return ['code' => 0, 'message' => '系统用户中没有查询到社保公积金excel文档中的用户，请检查'];
        }
        $sys_user_ids = $users->pluck('id')->toArray();
        // 核对社保公积金excel文档用户是否在系统中都能查找到
        $not_found_users = [];
        foreach ($shebao_gongjijin_users as $key => $val) {
            if (!in_array($val['id'], $sys_user_ids)) {
                array_push($not_found_users, $val['id'] . '.' . $val['realname']);
            }
        }
        if (count($not_found_users) > 0) {
            return ['code' => 0, 'message' => '系统中没有找到以下用户，请确认员工ID是否有误并重新上传社保公积金人员文档：' . implode('，', $not_found_users)];
        }
        $salary_year = substr($inputs['wage_month'], 0, 4); // 薪酬年份
        $salary_month = substr($inputs['wage_month'], 5, 2); // 薪酬月份

        //生成用户出勤统计
        $stat = new \App\Http\Controllers\Api\AttendanceStatController;
        $if_ok = $stat->create($inputs['wage_month'], $user_ids);
        if($if_ok['code'] != 1){
            return ['code' => 0, 'message' => '生成'.$inputs['wage_month'].'月份出勤统计失败,请重新上传社保公积金信息'];
        }
        $data = [];

        // 发工资月份的工作日
        $workday_count = Holiday::where('type', 0)->whereRaw('left(`date`, 7) = "' . $inputs['wage_month'] . '"')->count();

        // 发工资月份请假天数，单位是 天
        $leaves = AttendanceStatistic::where(['year' => $salary_year, 'month' => $salary_month])
            ->whereIn('user_id', $user_ids)
            ->get(['year', 'month', 'user_id', 'qingjia_total', 'gongzuori_jiaban_total', 'zhoumo_jiaban_total', 'jiejiari_jiaban_total', 'if_full_att', 'qingjia_total', 'year_qingjia_total'])
            ->keyBy('user_id');

        // 假期类型表
        $holiday_types = HolidayType::all(['id', 'name', 'way', 'status', 'if_cancel_full_att', 'if_cancel_salary', 'salary_percent', 'suit', 'suit_sex', 'condition'])->keyBy('id');

        // 最近三个月全勤情况
        $shangyue = date('Y-m', strtotime('-1 month', strtotime($inputs['attendance_month'])));
        $qianyue = date('Y-m', strtotime('-2 month', strtotime($inputs['attendance_month'])));
        $full_attendance_totals = AttendanceStatistic::whereIn('user_id', $user_ids)
            ->where([
                'year' => substr($inputs['wage_month'], 0, 4),
                'month' => substr($inputs['wage_month'], 5, 2)
            ])->orWhere(function ($query) use ($shangyue, $qianyue) {
                $query->orWhere(['year' => substr($shangyue, 0, 4), 'month' => substr($shangyue, 5, 2)])
                    ->orWhere(['year' => substr($qianyue, 0, 4), 'month' => substr($qianyue, 5, 2)]);
            })->selectRaw('`user_id`, COUNT(*) `total`')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        //$theads_for_business = ['时间', '员工编号', '姓名', '部门', '基本工资', '绩效工资', '绩效分数', '最终绩效工资', '请假扣款', '社保扣款', '公积金扣款', '个税扣款', '其他扣款', '对公工资'];
        //$theads_for_personnel = ['时间', '员工编号', '姓名', '部门', '绩效', '加班费', '餐补', '全勤', '高温补贴', '其他工资', '其他扣款', '对私工资'];
        //$theads_for_complete = ['时间', '员工编号', '姓名', '部门', '基本工资', '请假扣款', '代扣社+公', '税前合计', '个税扣款', '实发(1)', '绩效工资', '餐补', '全勤', '加班费', '高温补贴', '其他工资', '实发(2)', '其他扣款', '合计'];
        $theads_for_complete = ['时间', '员工编号', '姓名', '部门', '基本工资', '请假扣款', '绩效工资', '全勤', '加班费','高温补贴', '其他工资', '代扣社+公', '税前合计', '个税扣款', '餐补', '其他扣款', '合计'];
        //$tbodys_for_business = [];
        //$tbodys_for_personnel = [];
        $tbodys_for_complete = [];

        foreach ($users as $key => $user) {
            $data[$key]['user_id'] = $user->id;
            $data[$key]['employee_id'] = $user->serialnum;
            $data[$key]['wage_month'] = $inputs['wage_month'];
            $data[$key]['realname'] = $user->realname;
            $dept = $user->dept()->first(['id', 'name']);
            if (!$dept) {
                return ['code' => 0, 'message' => 'ID为 ' . $user->id . ' 的用户未设置部门，请先设置'];
            }
            $data[$key]['dept_name'] = $dept->name;
            $contract = $user->contracts()->first();
            if (!$contract) {
                return ['code' => 0, 'message' => 'ID为 ' . $user->id . ' 的用户合同信息不全，请先补全'];
            }
            $is_cancel_full_attendance = false;
            $data[$key]['gaowenbutie'] = sprintf('%0.2f', $contract->gaowenbutie);
            $is_turn_positive = 0; // 试用期
            $wage_month_start = $inputs['wage_month'] . '-01';
            if (($contract && empty($contract->positive_date)) || ($contract && $contract->positive_date && strtotime($contract->positive_date) > strtotime($wage_month_start))) {
                $data[$key]['basic_wage'] = sprintf('%.2f', $contract->probational_period_salary); // 试用期
            } else if ($contract && $contract->positive_date && strtotime($contract->positive_date) <= strtotime($wage_month_start)) {
                $is_turn_positive = 1; // 转正
                $data[$key]['basic_wage'] = $contract->regular_employee_salary; // 已转正
            }
            $leave_bout_count = ApplyAttendanceDetail::where(['user_id' => $user->id, 'year' => $salary_year, 'month' => $salary_month])->count();
            // 请假扣款
            $leave_deduction = 0;
            $qingjia_type_totals = isset($leaves[$user->id]['qingjia_total']) ? collect(unserialize($leaves[$user->id]['qingjia_total']))->keyBy('type_id')->toArray() : [];
            if ($qingjia_type_totals) {
                foreach ($qingjia_type_totals as $ks => $vs) {
                    $holiday_type = $holiday_types[$vs['type_id']] ?? [];
                    if (empty($holiday_type) || intval($vs['time_str']) <= 0) {
                        continue;
                    }

                    // 是否取消全勤
                    if (!$is_cancel_full_attendance && $holiday_type['if_cancel_full_att'] == 1 && intval($vs['time_str']) > 0) {
                        $is_cancel_full_attendance = true;
                    }

                    $wage = $is_turn_positive ? $contract->regular_employee_salary + $contract->performance : $contract->probational_period_salary;
                    if ($holiday_type->if_cancel_salary == 1) {
                        // 取消请假时间段内工资
                        $leave_deduction += ($wage / 22) * $vs['time_str'];
                    } else if ($holiday_type->if_cancel_salary == 2) {
                        // 按照正常工资的百分比扣除
                        $salary_percent = isset($vs['salary_percent']) && is_numeric($vs['salary_percent']) ? ($vs['salary_percent'] / 100) : 1;
                        $leave_deduction += ($wage / 22) * $vs['time_str'] * $salary_percent;
                    } else if ($holiday_type->if_cancel_salary == 3) {
                        // 根据条件计算扣除
                        $conditions = isset($holiday_type->condition) ? unserialize($holiday_type->condition) : [];
                        $month_qingjia_detail = unserialize($leaves[$user->id]['qingjia_total']) ?? [];
                        $year_qingjia_detail = unserialize($leaves[$user->id]['year_qingjia_total']) ?? [];
                        $year_qingjia_total = 0;
                        foreach ($year_qingjia_detail as $ykey => $yval) {
                            if ($yval['type_id'] == $vs['type_id']) {
                                $year_qingjia_total += floatval($yval['time_str']);
                            }
                        }
                        $month_qingjia_total = 0;
                        foreach ($month_qingjia_detail as $mkey => $mval) {
                            if ($mval['type_id'] == $vs['type_id']) {
                                $month_qingjia_total += floatval($mval['time_str']);
                            }
                        }
                        $now_month_before = $year_qingjia_total - $month_qingjia_total; // 当前月份之前，今天共请假的天数
                        for ($now_month_leave_increase = $now_month_before + 0.5; $now_month_leave_increase <= $month_qingjia_total; $now_month_leave_increase += 0.5) {
                            foreach ($conditions as $ckey => $cval) {
                                if ($now_month_leave_increase >= $cval['start_day'] && $now_month_leave_increase < $cval['end_day']) {
                                    if ($cval['type'] == 1) {
                                        $leave_deduction += ($wage / 22) * ($cval['percent'] / 100) * 0.5;
                                    } else {
                                        $leave_deduction += ($wage / 22) * 0.5;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $data[$key]['leave_deduction'] = sprintf('%.2f', $leave_deduction);
            $full_attendance_reward = 0; // 全勤奖
            if (!$is_cancel_full_attendance) {
                if (isset($leaves[$user_id]['if_full_att']) && $leaves[$user_id]['if_full_att'] == 1) {
                    if (isset($full_attendance_totals[$user->id]['total']) && $full_attendance_totals[$user->id]['total'] < $inputs['quanqin_shaoyu']) {
                        $full_attendance_reward = $inputs['quanqin_shaoyu_money'];
                    } else if (isset($full_attendance_totals[$user->id]['total']) && $full_attendance_totals[$user->id]['total'] >= $inputs['quanqin_dayu']) {
                        $full_attendance_reward = $inputs['quanqin_dayu_money'];
                    }
                }
            }

            $shebao = isset($shebao_gongjijin_users[$user->id]['shebao']) && is_numeric(trim($shebao_gongjijin_users[$user->id]['shebao'])) ? trim($shebao_gongjijin_users[$user->id]['shebao']) : 0;
            $gongjijin = isset($shebao_gongjijin_users[$user->id]['gongjijin']) && is_numeric(trim($shebao_gongjijin_users[$user->id]['gongjijin'])) ? trim($shebao_gongjijin_users[$user->id]['gongjijin']) : 0;
            $data[$key]['shebao_and_gongjijin'] = sprintf('%.2f', $shebao + $gongjijin);
            $jiben_gongzi = $is_turn_positive ? $contract->regular_employee_salary : $contract->probational_period_salary;
            $data[$key]['gross_pay'] = sprintf('%.2f', $jiben_gongzi - $data[$key]['leave_deduction'] - $data[$key]['shebao_and_gongjijin']);
            $data[$key]['tax'] = Salary::tax($data[$key]['gross_pay']);
            $data[$key]['performance_score'] = 0;
            if ($is_turn_positive) {
                $performance_score = $user->performances()
                    ->where('year_month', $inputs['performance_month'])
                    ->where('status', 3)
                    ->value('total_score');
                $performance_score = empty($performance_score) ? 0 : intval($performance_score);
                $data[$key]['performance_score'] = $performance_score;
            }
            $data[$key]['performance_pay'] = sprintf('%0.2f', $contract->performance * $data[$key]['performance_score'] / 100); // 个人绩效 * 绩效分数 / 100
            $data[$key]['meal_subsidy'] = sprintf('%.2f', $inputs['meal_subsidy'] * ($workday_count - $leave_bout_count));
            $overtime_pay = 0;
            $overtime_pay += isset($leaves[$user->id]['gongzuori_jiaban_total']) && is_numeric($leaves[$user->id]['gongzuori_jiaban_total']) ? $inputs['working_day_work_overtime'] * $leaves[$user->id]['gongzuori_jiaban_total'] : 0;
            $overtime_pay += isset($leaves[$user->id]['zhoumo_jiaban_total']) && is_numeric($leaves[$user->id]['zhoumo_jiaban_total']) ? $inputs['weekend_work_overtime'] * $leaves[$user->id]['zhoumo_jiaban_total'] : 0;
            $overtime_pay += isset($leaves[$user->id]['jiejiari_jiaban_total']) && is_numeric($leaves[$user->id]['jiejiari_jiaban_total']) ? $inputs['holiday_day_work_overtime'] * $leaves[$user->id]['jiejiari_jiaban_total'] : 0;
            $overtime_pay = $contract->regular_employee_salary * $data[$key]['performance_pay'] / 22 / 8 * $overtime_pay; // 加班费不分试用转正，都是用转正的基本工资+绩效来计算
            $data[$key]['overtime_pay'] = sprintf('%0.2f', $overtime_pay);
            $data[$key]['other_fee'] = sprintf('%0.2f', $contract->other_fee);
            //$salary_for_business = $data[$key]['gross_pay'] - $data[$key]['tax'];
            //$salary_for_personnel = $data[$key]['performance_pay'] + $data[$key]['meal_subsidy'] + $data[$key]['overtime_pay'] + $full_attendance_reward + $data[$key]['gaowenbutie'] + $data[$key]['other_fee'];
            // $data[$key]['total'] = sprintf('%0.2f', $salary_for_business + $salary_for_personnel);
            $data[$key]['total'] = sprintf('%0.2f', 0);
            // 导出Excel文件数据
            $ohter_deduction = sprintf('%0.2f', 0);
            // 对公工资
            /*$tbodys_for_business[] = [
                $data[$key]['wage_month'], $data[$key]['employee_id'], $data[$key]['realname'], $data[$key]['dept_name'], $data[$key]['basic_wage'], $contract->performance,
                $data[$key]['performance_score'], $data[$key]['performance_pay'], $data[$key]['leave_deduction'], ($shebao_gongjijin_users[$user->id]['shebao'] ?? 0),
                ($shebao_gongjijin_users[$user->id]['gongjijin'] ?? 0), $data[$key]['tax'], $ohter_deduction, sprintf('%0.2f', $salary_for_business)
            ];
            // 对私工资
            $tbodys_for_personnel[] = [
                $data[$key]['wage_month'], $data[$key]['employee_id'], $data[$key]['realname'], $data[$key]['dept_name'], $data[$key]['performance_pay'], $data[$key]['overtime_pay'], $data[$key]['meal_subsidy'], $full_attendance_reward,
                $data[$key]['gaowenbutie'], $data[$key]['other_fee'], $ohter_deduction, sprintf('%0.2f', $salary_for_personnel)
            ];
            // 最终工资
            $tbodys_for_complete[] = [
                $data[$key]['wage_month'], $data[$key]['employee_id'], $data[$key]['realname'], $data[$key]['dept_name'], $data[$key]['basic_wage'], $data[$key]['leave_deduction'],
                $data[$key]['shebao_and_gongjijin'], $data[$key]['gross_pay'], $data[$key]['tax'], sprintf('%0.2f', $salary_for_business),
                $data[$key]['performance_pay'], $data[$key]['meal_subsidy'], $full_attendance_reward, $data[$key]['overtime_pay'], $data[$key]['gaowenbutie'],
                $data[$key]['other_fee'], sprintf('%0.2f', $salary_for_personnel), $ohter_deduction, $data[$key]['total']
            ];*/
            // 最终工资 除了餐补  其他全部走公账 （2019-07-04 改）
            $tbodys_for_complete[] = [
                $data[$key]['wage_month'], $data[$key]['employee_id'], $data[$key]['realname'], $data[$key]['dept_name'], $data[$key]['basic_wage'], $data[$key]['leave_deduction'],
                $data[$key]['performance_pay'], $full_attendance_reward, $data[$key]['overtime_pay'], 
                $data[$key]['gaowenbutie'], $data[$key]['other_fee'], $data[$key]['shebao_and_gongjijin'],
                $data[$key]['gross_pay'], $data[$key]['tax'], $data[$key]['meal_subsidy'], $ohter_deduction,
                $data[$key]['total']
            ];
        }
        $theads = [$theads_for_complete];
        $tbodys = [$tbodys_for_complete];
        $sheet_name = ['最终工资'];
        $file_info = exportMultipleExcel($theads, $tbodys, $sheet_name, 'gongzimingxi', 'app/public/temps', 'xls');
        if($file_info){
            systemLog('薪酬管理','创建了工资表');
        }
        return ['code' => 1, 'message' => 'success', 'data' => ['url' => asset('storage/temps/' . $file_info['file']), 'filepath' => 'storage/temps/' . $file_info['file']]];
    }

    /**
     * 薪酬管理 -> 导入最终工资
     * @Author: qinjintian
     * @Date:   2018-11-27
     */
    public function import()
    {
        $inputs = \request()->all();
        $rules = ['file' => 'required|file|max:10240'];
        $attributes = ['file' => 'Excel文件'];
        $messages = [
            'file.required' => '请选择要上传的Excel文件',
            'file.file' => '上传的必须是有效的Excel文件',
        ];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        if (!request()->isMethod('post')) {
            return ['code' => 0, 'message' => '非法操作'];
        }
        $file = request()->file('file');
        if ($file->isValid()) {
            $original_name = $file->getClientOriginalName(); // 文件原名
            $ext = $file->getClientOriginalExtension();  // 扩展名
            $real_path = $file->getRealPath(); // 临时文件的绝对路径
            $type = $file->getClientMimeType(); // image/jpeg
            if (!in_array($ext, ['xls', 'xlsx'])) {
                return ['code' => -1, 'message' => '只能上传合法的Excel文件'];
            }
        }
        $path = storage_path('app/public/temps/');
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $filename = $path . 'finally-salary.' . $ext;
        $fileinfo = $file->move($path, $filename);
        $file_path_name = $fileinfo->getPathname();
        // 读取社保公积金excel文档
        if (!file_exists($file_path_name)) {
            return ['code' => 0, 'message' => '文件不存在，请检查'];
        }
        try {
            $sheet_data = importExcel($file_path_name, [0], 1); // 第三个sheet才是最终工资，顺序不能变
        } catch (\Exception $e) {
            return ['code' => 0, 'message' => 'Excel文件读取失败，请检查文件格式是否正确'];
        }
        $payrolls = $sheet_data['data']['sheets'];
        if (count($payrolls) < 1) {
            return ['code' => 0, 'message' => '要导入的文档没有任何行信息，请检查'];
        }

        // 验证字段有效性
        foreach ($payrolls as $key => $val) {
            $vdata = ['wage_month' => trim($val[0]), 'employee_id' => intval(trim($val[1]))];
            $rules = ['employee_id' => 'required', 'wage_month' => 'required|date|date_format:"Y-m"'];
            $attributes = ['employee_id' => '员工编号', 'wage_month' => '工资月份'];
            $messages = ['wage_month.date_format' => '工资月份格式必须由 4位数年份-2位数月份 组成', 'wage_month.date' => '工资月份格式必须由 4位数年份-2位数月份 组成',];
            $validator = validator($vdata, $rules, $messages, $attributes);
            if ($validator->fails()) {
                return ['code' => -1, 'message' => $vdata['employee_id'] . '.' . trim($val[2]) . ' ' . $validator->errors()->first() . '，请检查'];
            }
        }
        $salary_model = new \App\Models\Salary;
        try {
            foreach ($payrolls as $key => $val) {
                if(empty($val[1])) continue;
                $wage_month = trim($val[0]);
                $user_id = User::where('serialnum', trim($val[1]))->value('id');
                if (!$user_id) {
                    return ['code' => 0, 'message' => '系统中未找到【' . $val[1] . '.' . $val[2] . '】，请检查'];
                }
                $data = [
                    'wage_month' => $wage_month, 'user_id' => $user_id, 'realname' => trim($val[2]), 
                    'dept_name' => trim($val[3]), 'basic_wage' => floatval(trim($val[4])), 'leave_deduction' => floatval(trim($val[5])),
                    'performance_pay' => floatval(trim($val[6])), 'full_attendance' => floatval(trim($val[7])),
                    'overtime_pay' => floatval(trim($val[8])), 'gaowenbutie' => floatval(trim($val[9])), 
                    'other_fee' => floatval(trim($val[10])), 'shebao_and_gongjijin' => floatval(trim($val[11])), 
                    'gross_pay' => floatval(trim($val[12])), 'tax' => floatval(trim($val[13])), 
                    'meal_subsidy' => floatval(trim($val[14])), 
                    'ohter_deduction' => floatval(trim($val[15])), 'total' => floatval(trim($val[16])), 
                    'salary_for_business' => 0, 'salary_for_personnel' => 0
                ];
                $salary = $salary_model->where(['wage_month' => $wage_month, 'user_id' => $user_id])->first();
                if (!$salary) {
                    $salary_model->create($data);
                } else if ($salary && in_array($salary->status, [0, 1])) {
                    $salary->update($data);
                }
            }
            systemLog('薪酬管理','导入最终工资单');
            return ['code' => 1, 'message' => '导入成功，请刷新列表查看'];
        } catch (\Exception $e) {
            return ['code' => 0, 'message' => '导入失败，请重试'];
        }
    }

    /**
     * 薪酬管理 -> 工资汇总
     * @Author: qinjintian
     * @Date:   2018-11-27
     */
    public function summary()
    {
        $inputs = \request()->all();
        $salarys = (new Salary)->querySalarysSummary($inputs);
        return ['code' => 1, 'message' => 'success', 'data' => $salarys];
    }

    /**
     * 薪酬管理 -> 发布工资
     * @Author: qinjintian
     * @Date:   2018-11-27
     */
    public function publish()
    {
        $ids = \request()->input('ids', []);
        if (count($ids) < 1) {
            return ['code' => 0, 'message' => '请先扣选要发布的工资'];
        }
        $result = Salary::whereIn('id', $ids)->update(['status' => 1]);
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('薪酬管理','发布工资');
            $user_ids = Salary::where('status', 0)->whereIn('id', $ids)->pluck('user_id')->toArray();
            addNotice($user_ids, '薪酬管理', '你有一份工资待确认，请及时确认', '', auth()->id(), 'wages-index');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 薪酬管理 -> 查看详情
     * @Author: qinjintian
     * @Date:   2018-11-27
     */
    public function show()
    {
        $id = \request()->input('id', 0);
        if ($id < 0) {
            return ['code' => 0, 'message' => '不存在这条数据记录'];
        }
        $salary = Salary::find($id);
        return $salary ? ['code' => 1, 'message' => 'success', 'data' => $salary] : ['code' => 0, 'message' => '查看失败，请重试'];
    }

    /**
     * 薪酬管理 -> 修改薪酬
     * @Author: qinjintian
     * @Date:   2018-11-27
     */
    public function update()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case '1':
                // 编辑表单数据
                $salary_id = $inputs['id'] ?? 0;
                $salary = Salary::find($salary_id);
                return $salary ? ['code' => 1, 'message' => 'success', 'data' => $salary] : ['code' => 0, 'message' => '没有这条记录，请检查'];
                break;
            default:
                // 修改薪酬
                return $this->modifySalary($inputs);
        }
    }

    /**
     * 修改薪酬数据
     */
    private function modifySalary($inputs = [])
    {
        $rules = [
            'id' => 'required|numeric|min:1',
            'basic_wage' => 'required|numeric|min:0',
            'leave_deduction' => 'required|numeric|min:0',
            'shebao_and_gongjijin' => 'required|numeric|min:0',
            'performance_pay' => 'required|numeric|min:0',
            'meal_subsidy' => 'required|numeric|min:0',
            'full_attendance' => 'required|numeric|min:0',
            'overtime_pay' => 'required|numeric|min:0',
            'gaowenbutie' => 'required|numeric|min:0',
            'other_fee' => 'required|numeric|',
        ];
        $attributes = [
            'id' => '薪酬ID',
            'basic_wage' => '基本工资',
            'leave_deduction' => '请假扣款',
            'shebao_and_gongjijin' => '代扣社保公积金',
            'performance_pay' => '绩效工资',
            'meal_subsidy' => '餐补',
            'full_attendance' => '全勤奖',
            'overtime_pay' => '加班费',
            'gaowenbutie' => '高温补贴',
            'other_fee' => '其他费用',
        ];
        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $salary_model = new Salary;
        $salary = $salary_model->find($inputs['id']);
        if (!$salary) {
            return ['code' => 0, 'message' => '不存在这条薪酬记录，请检查'];
        }
        if ($salary->status == 2) {
            return ['code' => 0, 'message' => '已经确认的薪酬不能继续修改'];
        }
        $result = $salary_model->modifySalary($salary, $inputs);
        if($result){
            $response = ['code' => 1, 'message' => '修改成功'];
            systemLog('薪酬管理','修改了['.$salary["realname"].']薪酬');
        }else{
            $response = ['code' => 0, 'message' => '修改失败，请重试'];
        }
        return $response;
    }

    /**
     * 薪酬管理 -> 我的工资
     * @Author: qinjintian
     * @Date:   2018-11-16
     */
    public function mySalary()
    {
        $inputs = request()->all();
        $data = (new Salary)->mySalary($inputs);
        return ['code' => 1, 'message' => 'success', 'data' => $data];
    }

    /**
     * 薪酬管理 -> 确认薪酬
     * @Author: qinjintian
     * @Date:   2018-11-28
     */
    public function confirm()
    {
        $inputs = \request()->all();
        if (empty($inputs['id'])) {
            return ['code' => -1, 'message' => '缺少薪资ID参数，请检查'];
        }
        $salary_model = new Salary;
        $salary = $salary_model->find($inputs['id']);
        if (!$salary) {
            return ['code' => 0, 'message' => '薪资记录不在，请检查'];
        }
        if ($salary->status == 0) {
            return ['code' => 0, 'message' => '薪资未发布，无法继续操作'];
        }
        if ($salary->status == 2) {
            return ['code' => 0, 'message' => '薪资已经确认过，无法继续操作'];
        }
        if ($salary->user_id != auth()->id()) {
            return ['code' => 0, 'message' => '非法操作，只允许操作确认自己的工资'];
        }
        $salary->status = 2;
        $result = $salary->save();
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('薪酬管理','确认薪酬');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 薪酬管理 -> 填写备注
     * @Author: renxianyong
     * @Date:   2019-02-14
     */
    public function remark(Salary $salary)
    {
        $inputs = \request()->input();
        $status = $salary->where('id',$inputs['id'])->value('status');
        $result = false;
        if($status == 0){
            $info = $salary->find($inputs['id']);
            $info->remark = $inputs['remark'];
            $result = $info->save();
        }
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('薪酬管理','填写['.$info["realname"].']的备注');
        }else{
            $response = ['code' => 0, 'message' => '薪酬已发布或已确认，不能备注'];
        }
        return $response;
    }

    /**
     * 创建工资表 -> 查看考勤统计
     * @Author: molin
     * @Date:   2019-03-28
     */
    public function statistic(){
        $inputs = request()->all();
        if(!isset($inputs['month']) || empty($inputs['month'])){
            return response()->json(['code' => 0, 'message' => '请传入月份,如:2019-02']);
        }
        $stat = new \App\Http\Controllers\Api\AttendanceStatController;
        if(isset($inputs['request_type']) && $inputs['request_type'] == 'create'){
            $result = $stat->create($inputs['month']);
            return response()->json($result);
        }
        $data = $stat->index(true,$inputs);
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 创建工资表 -> 查看历史考勤统计
     * @Author: molin
     * @Date:   2019-03-28
     */
    public function historyStatistic(){
        $inputs = request()->all();
        if(!isset($inputs['month']) || empty($inputs['month'])){
            return response()->json(['code' => 0, 'message' => '请传入月份,如:2019-02']);
        }
        $stat = new \App\Http\Controllers\Api\AttendanceStatController;
        $data = $stat->getHistoryList($inputs);
        return response()->json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

}

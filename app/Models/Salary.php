<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    protected $table = 'salarys';

    // protected $fillable = ['date', 'realname', 'dept_name', 'basic_wage', 'leave', 'shebao_and_gognjijin', 'gross_pay', 'tax', 'performance_pay', 'meal_subsidy', 'overtime_pay', 'other_fee', 'total'];

    protected $guarded = ['status'];

    // 薪酬汇总
    public function querySalarysSummary($inputs = [])
    {
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $keyword = $inputs['keyword'] ?? '';
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';
        $records_total = $this->count();
        $querys = $this->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->whereBetween('created_at', [$start_time . ' 00:00:00', $end_time . ' 23:59:59']);
        })->when($keyword, function ($query) use ($keyword) {
            $query->where('realname', 'like', '%' . $keyword . '%');
        });
        $records_filtered = $querys->count();
        $datalist = $querys->orderBy('wage_month', 'DESC')
            ->orderBy('user_id')
            ->skip($start)
            ->take($length)
            ->get();
        return ['records_total' => $records_total, 'records_filtered' => $records_filtered, 'datalist' => $datalist];
    }

    // 修改薪酬
    public function modifySalary($salary, $inputs = [])
    {
        $salary->basic_wage = $inputs['basic_wage'];
        $salary->leave_deduction = $inputs['leave_deduction'];
        $salary->shebao_and_gongjijin = $inputs['shebao_and_gongjijin'];
        $salary->performance_pay = $inputs['performance_pay'];
        $salary->meal_subsidy = $inputs['meal_subsidy'];
        $salary->full_attendance = $inputs['full_attendance'];
        $salary->overtime_pay = $inputs['overtime_pay'];
        $salary->other_fee = $inputs['other_fee'];
        $salary->gaowenbutie = $inputs['gaowenbutie'];
        $salary->gross_pay = sprintf('%0.2f', $inputs['basic_wage'] - $inputs['leave_deduction'] - $inputs['shebao_and_gongjijin']);
        $salary->tax = self::tax($salary->gross_pay);
        $salary_for_business = $salary->gross_pay - $salary->tax; // 对公工资
        $salary_for_personnel = $salary->performance_pay + $salary->meal_subsidy + $salary->overtime_pay + $salary->full_attendance + $salary->gaowenbutie + $salary->other_fee; // 对私工资
        $salary->total = $salary_for_business + $salary_for_personnel;
        return $salary->save();
    }

    // 我的薪酬
    public function mySalary($inputs = [])
    {
        $start = isset($inputs['start']) && is_numeric($inputs['start']) ? $inputs['start'] : 0;
        $length = isset($inputs['length']) && is_numeric($inputs['length']) ? $inputs['length'] : 10;
        $start_time = $inputs['start_time'] ?? '';
        $end_time = $inputs['end_time'] ?? '';
        $user = auth()->user();
        $records_total = $this->where('user_id', $user->id)->whereIn('status', [1, 2])->count();
        $querys = $this->where('user_id', $user->id)->whereIn('status', [1, 2])->when($start_time && $end_time, function ($query) use ($start_time, $end_time) {
            $query->where('wage_month', '>=', $start_time)->where('wage_month', '<=', $end_time);
        });
        $records_filtered = $querys->count();
        $datalist = $querys->orderBy('wage_month', 'DESC')
            ->skip($start)
            ->take($length)
            ->get();
        return ['records_total' => $records_total, 'records_filtered' => $records_filtered, 'datalist' => $datalist];
    }

    /**
     *  计算个税扣款
     * @param $gross_pay 税前工资合计
     * @return int|string
     */
    public static function tax($gross_pay)
    {
        $payable = $gross_pay - 5000;
        $tax = 0;
        if ($payable > 0) {
            switch ($payable) {
                case $payable <= 3000:
                    $tax = sprintf('%0.2f', $payable * 0.03);
                    break;
                case $payable > 3000 && $payable <= 12000:
                    $tax = sprintf('%0.2f', $payable * 0.1 - 210);
                    break;
                case $payable > 12000 && $payable <= 25000:
                    $tax = sprintf('%0.2f', $payable * 0.2 - 1410);
                    break;
                case $payable > 25000 && $payable <= 35000:
                    $tax = sprintf('%0.2f', $payable * 0.25 - 2660);
                    break;
                case $payable > 35000 && $payable <= 55000:
                    $tax = sprintf('%0.2f', $payable * 0.3 - 4410);
                    break;
                case $payable > 55000 && $payable <= 80000:
                    $tax = sprintf('%0.2f', $payable * 0.35 - 7160);
                    break;
                default:
                    $tax = sprintf('%0.2f', $payable * 0.45 - 15160);
            }
        }
        return sprintf('%0.2f', $tax);
    }
}

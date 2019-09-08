<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportBasicSetting extends Model
{
    protected $table = 'report_basic_settings';

    // 报表对应的用户
    public function users()
    {
        return $this->hasMany(User::class, 'report_id', 'id');
    }

    /*
     * 保存表单基础设置
     */
    public function storeBasisSetting($inputs = [])
    {
        $report_basic_setting = $this->find($inputs['id']);
        $report_basic_setting->report_type_name = $inputs['report_type_name'];
        $report_basic_setting->if_assess = $inputs['if_assess'];
        $report_basic_setting->status = $inputs['status'];
        return $report_basic_setting->save();
    }

    /**
     * 设置人员列表
     */
    public function queryUserList($inputs = [])
    {
        $keyword = $inputs['keyword'] ?? '';
        $start = $inputs['start'] ?? 0;
        $length = $inputs['length'] ?? 10;
        $user_model = new User();
        if (empty($inputs['report_id'])) {
            $records_total = $user_model->count();
        } else {
            $records_total = $user_model->where('report_id', $inputs['report_id'])->count();
        }
        $querys = $user_model->with(['dept' => function ($query) {
            $query->select(['id', 'name']);
        }, 'position' => function ($query) {
            $query->select(['id', 'name']);
        }])
            ->when(!empty($inputs['dept_id']) && is_numeric($inputs['dept_id']), function ($query) use ($inputs) {
                $query->where('dept_id', $inputs['dept_id']);
            })
            ->when(!empty($inputs['position_id']) && is_numeric($inputs['position_id']), function ($query) use ($inputs) {
                $query->where('position_id', $inputs['position_id']);
            })
            ->when(!empty($inputs['report_id']) && is_numeric($inputs['report_id']), function ($query) use ($inputs) {
                $query->where('report_id', $inputs['report_id']);
            })
            ->when(!empty($inputs['keyword']), function ($query) use ($inputs) {
                $query->where('realname', 'like', '%' . $inputs['keyword'] . '%');
            });
        $records_filtered = $querys->count(); // 符合条件的数据
        $datalist = $querys->select(['id', 'realname', 'dept_id', 'position_id', 'report_id'])
            ->skip($start)
            ->take($length)
            ->get();
        return ['records_total' => $records_total, 'records_filtered' => $records_filtered, 'datalist' => $datalist];
    }
}

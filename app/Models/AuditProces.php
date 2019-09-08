<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use phpDocumentor\Reflection\DocBlock\Description;

class AuditProces extends Model
{
    //
    protected $table = 'audit_process';

    //关联出勤申请表
    public function applyAtt()
    {
        return $this->belongsTo('App\Models\ApplyAttendance', 'apply_id', 'id');
    }

    //关联物品领用申请表
    public function applyAccess()
    {
        return $this->belongsTo('App\Models\ApplyAccess', 'apply_id', 'id');
    }

    //关联采购申请表
    public function applyPurchase()
    {
        return $this->belongsTo('App\Models\ApplyPurchase', 'apply_id', 'id');
    }

    //关联招聘申请表
    public function applyRecruit()
    {
        return $this->belongsTo('App\Models\ApplyRecruit', 'apply_id', 'id');
    }

    //关联培训申请表
    public function applyTraining()
    {
        return $this->belongsTo('App\Models\ApplyTraining', 'apply_id', 'id');
    }

    //关联转正申请表
    public function applyFormal()
    {
        return $this->belongsTo('App\Models\ApplyFormal', 'apply_id', 'id');
    }

    //关联离职申请表
    public function applyLeave()
    {
        return $this->belongsTo('App\Models\ApplyLeave', 'apply_id', 'id');
    }

    //关联报备申请表
    public function applyReport()
    {
        return $this->belongsTo('App\Models\ApplyReport', 'apply_id', 'id');
    }

    //关联用户信息
    public function hasUser()
    {
        return $this->belongsTo('App\Models\User', 'user_id', 'id');
    }

    // 获取用户合同信息
    public function contracts()
    {
        return $this->hasOne('App\Models\UserContract', 'user_id', 'user_id');
    }

    // 获取部门信息
    public function hasDept()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    public function hasMain()
    {
        return $this->hasOne('App\Models\ApplyMain', 'apply_id', 'apply_id');
    }

    /*
    * @setting_info 配置信息  
    * @apply_id 新申请的数据id
    * @type_id 申请类型  1出勤申请 2物品领用 3采购申请 4招聘申请 5培训申请 6转正申请 7 离职申请
    * @table 数据来源的主表
    */
    public function storeData($setting_info, $apply, $type_id, $table)
    {
        $apply_id = $apply->id;//当前记录id
        $current_step = $apply->step;//当前步骤
        $audit_process = [];
        $user_id = auth()->user()->id;

        $user = new \App\Models\User;
        $dept = new \App\Models\Dept;
        $steps = new \App\Models\AuditProcessStep;
        $steps_list = $steps->where('setting_id', $setting_info->id)->orderBy('step', 'asc')->get();//取出所有步骤
        $step_all_users = $step_end = $reject_step = [];
        foreach ($steps_list as $key => $value) {
            if (!empty($value)) {
                //审核步骤
                $next_user_ids = [];
                //取到每个步骤的所有审核人  步骤->审核人
                if ($value->cur_user_id == 1) {
                    $dept_id = auth()->user()->dept_id;
                    $dept_info = $dept->where('id', $dept_id)->select(['id', 'supervisor_id'])->first();
                    if (empty($dept_info)) {
                        return false;
                    }
                    $next_user_ids = [$dept_info->supervisor_id];
                } else if ($value->cur_user_id == 2) {
                    $dept_id = $value->dept_id;
                    $rank_id = $value->rank_id;
                    $users = $user->where('status', 1)->where('dept_id', $dept_id)->where('rank_id', $rank_id)->select(['id'])->get();
                    if (empty($users)) {
                        return false;
                    }
                    foreach ($users as $key => $v) {
                        $next_user_ids[] = $v->id;
                    }
                } else if ($value->cur_user_id == 3) {
                    $role_id = $value->role_id;
                    $role_list = $user->where('position_id', $role_id)->select(['id'])->get();
                    if (empty($role_list)) {
                        return false;
                    }
                    foreach ($role_list as $key => $v) {
                        $next_user_ids[] = $v->id;
                    }
                } else if ($value->cur_user_id == 4) {
                    $uid = $value->user_id;
                    if (empty($uid)) {
                        return false;
                    }
                    $next_user_ids = [$uid];
                }
                $step_all_users[$value->step] = $next_user_ids;
                if ($value->step_type == 2) {
                    $step_end[$value->step] = 1;
                }
                if ($value->if_reject == 1) {
                    $reject_step[$value->step] = $value->reject_step_id;//驳回到哪一步
                }
                if ($value->step_type == 3) {
                    $condition1 = unserialize($value->condition1);
                    foreach ($condition1 as $kk => $vv) {
                        $symbol = $this->symbol($apply[$vv['name']], $vv['value'], $vv['symbol']);
                        if (isset($apply[$vv['name']]) && is_numeric($apply[$vv['name']]) && $symbol && $vv['is_end'] == 1) {
                            //申请条件是否满足结束步骤的条件  比如 申请请假时间小于3天 当前步骤就结束掉
                            $step_end[$value->step] = 1;
                        }
                    }

                }
            }
        }
        $apply_main = new \App\Models\ApplyMain;
        $apply_types = $apply_main->apply_types;
        $user_data = $user->getIdToData();
        foreach ($step_all_users as $key => $value) {
            foreach ($value as $k => $v) {
                $tmp = [];
                $tmp['type_id'] = $type_id;//类型
                $tmp['table'] = $table;//model
                $tmp['apply_id'] = $apply_id;//新增数据的id
                $tmp['type_apply'] = $type_id . '_' . $apply_id;//type_id与apply_id的结合
                $tmp['setting_id'] = $setting_info['id'];//配置id
                $tmp['step'] = $key;//步骤
                $tmp['current_step'] = $current_step;//当前步骤
                $tmp['user_id'] = $user_id;//申请人
                $tmp['dept_id'] = $apply->dept_id;//部门
                $tmp['pre_verify_user_id'] = $user_id;//前一个审核人 
                $tmp['current_verify_user_id'] = $v;//下一个审核人
                $tmp['audit_opinion'] = '';//评价内容
                $tmp['status'] = 0;//待审核
                $tmp['is_end'] = $step_end[$key] ?? 0;//是否是结束
                $tmp['reject_step_id'] = $reject_step[$key] ?? 0;//驳回到哪一步
                $tmp['created_at'] = date('Y-m-d H:i:s');
                $tmp['updated_at'] = date('Y-m-d H:i:s');
                $audit_process[] = $tmp;
            }
            if ($current_step == $key) {
                //消息提醒
                addNotice($value, '审核', $user_data['id_realname'][$user_id].'提交了一条'.$apply_types[$type_id].'，请及时审核', '', 0, 'approval-audit-index','apply/verify');
            }
        }
        return $this->insert($audit_process);
    }

    //获取数据列表-出勤
    public function getAttList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_attendances', function ($query) use ($inputs) {
            $query->on('apply_attendances.id', '=', 'audit_process.apply_id')
                ->on('apply_attendances.step', '=', 'audit_process.step')
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 1)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 1)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_attendances.start_time', 'apply_attendances.end_time', 'apply_attendances.leave_type', 'apply_attendances.leave_time', 'apply_attendances.time_str', 'apply_attendances.remarks', 'apply_attendances.outside_addr', 'apply_attendances.type', 'apply_attendances.status_txt'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取数据列表-物品领用
    public function getAccessList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_access', function ($query) use ($inputs) {
            $query->on('apply_access.id', '=', 'audit_process.apply_id')
                ->on('apply_access.step', '=', 'audit_process.step')
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 2)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 2)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_access.if_personnel', 'apply_access.content', 'apply_access.uses'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取数据列表-采购
    public function getPurchaseList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_purchases', function ($query) use ($inputs) {
            $query->on('apply_purchases.id', '=', 'audit_process.apply_id')
                ->on('apply_purchases.step', '=', 'audit_process.step')
                ->when(isset($inputs['type_id']) && is_numeric($inputs['type_id']), function ($query) use ($inputs) {
                    return $query->where('apply_purchases.type_id', $inputs['type_id']);//固定资产、消费品
                })
                ->when(isset($inputs['goods_name']) && !empty($inputs['goods_name']), function ($query) use ($inputs) {
                    return $query->where('apply_purchases.goods_name', 'like', '%' . $inputs['goods_name'] . '%');//固定资产、消费品
                })
                ->when(isset($inputs['dept_id']) && is_numeric($inputs['dept_id']), function ($query) use ($inputs) {
                    return $query->where('apply_purchases.dept_id', $inputs['dept_id']);//领用部门
                })
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 3)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 3)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_purchases.type_id AS type', 'apply_purchases.cate_id', 'apply_purchases.goods_id', 'apply_purchases.goods_name', 'apply_purchases.num', 'apply_purchases.spec', 'apply_purchases.images', 'apply_purchases.uses', 'apply_purchases.degree_id', 'apply_purchases.if_check', 'apply_purchases.rdate'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取数据列表-招聘
    public function getRecruitList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_recruits', function ($query) use ($inputs) {
            $query->on('apply_recruits.id', '=', 'audit_process.apply_id')
                ->on('apply_recruits.step', '=', 'audit_process.step')
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 4)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 4)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_recruits.number', 'apply_recruits.status_txt'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }


    //获取数据列表-培训
    public function getTrainingList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_trainings', function ($query) use ($inputs) {
            $query->on('apply_trainings.id', '=', 'audit_process.apply_id')
                ->on('apply_trainings.step', '=', 'audit_process.step')
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 5)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 5)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_trainings.name', 'apply_trainings.type_id', 'apply_trainings.addr_id', 'apply_trainings.content', 'apply_trainings.start_time', 'apply_trainings.end_time'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取数据列表-转正
    public function getFormalList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_formals', function ($query) use ($inputs) {
            $query->on('apply_formals.id', '=', 'audit_process.apply_id')
                ->on('apply_formals.step', '=', 'audit_process.step')
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 6)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 6)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_formals.work_content', 'apply_formals.work_ok', 'apply_formals.work_learn', 'apply_formals.work_plan', 'apply_formals.formal_date', 'apply_formals.status_txt'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取数据列表-离职
    public function getLeaveList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_leaves', function ($query) use ($inputs) {
            $query->on('apply_leaves.id', '=', 'audit_process.apply_id')
                ->on('apply_leaves.step', '=', 'audit_process.step')
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 7)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 7)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_leaves.leave_reason', 'apply_leaves.leave_date', 'apply_leaves.status_txt'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取数据列表-报备
    public function getReportList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $where_query = $this->join('apply_reports', function ($query) use ($inputs) {
            $query->on('apply_reports.id', '=', 'audit_process.apply_id')
                ->on('apply_reports.step', '=', 'audit_process.step')
                ->where('audit_process.status', 0)
                ->where('audit_process.type_id', 8)
                ->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
                    return $query->where('audit_process.current_verify_user_id', $inputs['user_id']);
                });
        })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->where('type_id', 8)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $count = $where_query->count();
        $list = $where_query->select(['audit_process.*', 'apply_reports.content', 'apply_reports.remarks', 'apply_reports.status_txt'])->orderBy('audit_process.id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取前面已审核的数据
    public function getProcessList($inputs, $type_id)
    {
        $where_query = $this->where('status', 1)
            ->where('audit_opinion', '<>', '')
            ->where('type_id', $type_id)
            ->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])
            ->with(['hasMain' => function ($query) {
                return $query->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $list = $where_query->orderBy('id', 'asc')->get();

        return $list;
    }

    //获取单条流程详细数据--物品领用申请
    public function getAccessInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyAccess')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && !empty($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyAccess' => function ($query) {
                return $query->select(['id', 'step', 'content', 'uses', 'created_at', 'status', 'status_txt', 'if_personnel']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date', 'regular_employee_salary', 'performance']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 2)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }

    //获取单条流程详细数据--采购申请
    public function getPurchaseInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyPurchase')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && !empty($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyPurchase' => function ($query) {
                return $query->select(['id', 'step', 'type_id', 'cate_id', 'goods_id', 'goods_name', 'num', 'spec', 'images', 'uses', 'degree_id', 'if_check', 'rdate', 'state', 'check_user', 'remarks', 'created_at', 'status', 'status_txt']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date', 'regular_employee_salary', 'performance']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 3)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }

    //获取单条流程详细数据--培训申请
    public function getTrainingInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyTraining')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && !empty($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyTraining' => function ($query) {
                return $query->select(['id', 'step', 'name', 'user_id', 'type_id', 'addr_id', 'start_time', 'end_time', 'content', 'created_at', 'status', 'status_txt']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 5)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }

    //获取单条流程详细数据--转正申请
    public function getFormalInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyFormal')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && !empty($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyFormal' => function ($query) {
                return $query->select(['id', 'step', 'user_id', 'work_content', 'work_ok', 'work_learn', 'work_plan', 'score', 'formal_date', 'created_at', 'status', 'status_txt']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 6)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }

    //获取单条流程详细数据--离职申请
    public function getLeaveInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyLeave')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && !empty($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyLeave' => function ($query) {
                return $query->select(['id', 'step', 'leave_reason', 'leave_date', 'created_at', 'status', 'status_txt']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date', 'regular_employee_salary', 'performance']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 7)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }

    //获取单条流程详细数据--报备申请
    public function getReportInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyReport')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && !empty($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyReport' => function ($query) {
                return $query->select(['id', 'user_id', 'step', 'content', 'remarks', 'created_at', 'status', 'status_txt']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date', 'regular_employee_salary', 'performance']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 8)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }

    //获取单条流程详细数据--出勤申请
    public function getAttInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyAttendance')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && is_numeric($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyAtt' => function ($query) {
                return $query->select(['id', 'step', 'user_id', 'start_time', 'end_time', 'type', 'leave_type', 'leave_time', 'time_str', 'remarks', 'outside_addr', 'created_at', 'status', 'status_txt', 'time_data']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date', 'regular_employee_salary', 'performance']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 1)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }

    //获取单条流程详细数据--招聘申请
    public function getRecruitInfo($inputs)
    {
        $where_query = $this->where('table', 'ApplyRecruit')
            ->when(isset($inputs['id']) && is_numeric($inputs['id']), function ($query) use ($inputs) {
                return $query->where('id', $inputs['id']);
            })->when(isset($inputs['apply_id']) && is_numeric($inputs['apply_id']), function ($query) use ($inputs) {
                return $query->where('apply_id', $inputs['apply_id']);
            })->when(isset($inputs['step']) && is_numeric($inputs['step']), function ($query) use ($inputs) {
                return $query->where('step', $inputs['step']);
            })->when(isset($inputs['current_step']) && !empty($inputs['current_step']), function ($query) use ($inputs) {
                return $query->where('current_step', $inputs['current_step']);
            })->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })->with(['applyRecruit' => function ($query) {
                return $query->select(['id', 'step', 'dept_id', 'number', 'post', 'positions_id', 'reason_ids', 'reason', 'type', 'duty', 'demand', 'salary1', 'salary2', 'created_at', 'status', 'status_txt']);
            }])->with(['hasUser' => function ($query) {
                return $query->select(['id', 'realname', 'username', 'birthday', 'sex']);
            }])->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }])->with(['contracts' => function ($query) {
                return $query->select(['user_id', 'entry_date', 'positive_date', 'regular_employee_salary', 'performance']);
            }])->with(['hasMain' => function ($query) {
                return $query->where('type_id', 4)->select(['id', 'apply_id', 'status', 'status_txt']);
            }]);
        $info = $where_query->first();
        return $info;
    }


    //我的审核-列表
    public function getMyVerifyList($inputs)
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $type_apply_in = $this->when(isset($inputs['user_ids']), function($query)use($inputs){
                $query->whereIn('user_id', $inputs['user_ids']);
            })->where(function($query){
                $query->where(function($query){
                    $query->whereIn('status', [1,2,3])->where('audit_opinion','<>','');
                })->orWhere(function($query){
                    $query->whereRaw('`step`=`current_step`');
                });
            })->where('current_verify_user_id', auth()->user()->id)->pluck('type_apply')->toArray();
        $inputs['type_apply_in'] = array_unique($type_apply_in);//去重
        $where_query = $this->when(isset($inputs['user_id']) && is_numeric($inputs['user_id']), function ($query) use ($inputs) {
            return $query->where('current_verify_user_id', $inputs['user_id']);
        })->when(isset($inputs['start_time']) && !empty($inputs['start_time']) && isset($inputs['end_time']) && !empty($inputs['end_time']), function ($query) use ($inputs) {
                return $query->whereBetween('created_at', [$inputs['start_time'] . ' 00:00:00', $inputs['end_time'] . ' 23:59:59']);
            })
            ->when(isset($inputs['type_apply_in']) && is_array($inputs['type_apply_in']), function ($query) use ($inputs) {
                return $query->whereIn('type_apply', $inputs['type_apply_in']);
            })
            ->when(isset($inputs['status']) && is_numeric($inputs['status']), function ($query) use ($inputs) {
                return $query->where('status', $inputs['status']);
            })
            ->when(isset($inputs['if_verify']) && is_numeric($inputs['if_verify']), function ($query) use ($inputs) {
                return $query->whereRaw('`step`=`current_step`');
            })
            ->with(['hasDept' => function ($query) {
                return $query->select(['id', 'name', 'supervisor_id']);
            }]);
        $count = $where_query->count();
        $user_id = auth()->user()->id;
        $list = $where_query->orderByRaw("field(`current_verify_user_id`, $user_id) desc")->orderBy('status', 'asc')->orderBy('id', 'desc')->skip($start)->take($length)->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    //获取当前用户待审核数量
    public function getVerifyCount(){
        $where_query = $this->where('current_verify_user_id', auth()->user()->id)
                    ->where('status', 0)
                    ->whereRaw('`step`=`current_step`');
        return $where_query->count();
    }

    public function symbol($a, $b, $symbol)
    {
        $compare = [
            '>' => function ($a, $b) {
                return $a > $b;
            },
            '<' => function ($a, $b) {
                return $a < $b;
            },
            '=' => function ($a, $b) {
                return $a == $b;
            },
            '>=' => function ($a, $b) {
                return $a >= $b;
            },
            '<=' => function ($a, $b) {
                return $a <= $b;
            },
            '!=' => function ($a, $b) {
                return $a != $b;
            }
        ];
        return $compare[$symbol]($a, $b);
    }

}

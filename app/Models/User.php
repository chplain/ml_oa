<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes, HasRoles;

    protected $table = 'users';

    protected $guard_name = 'api';

    // 可以通过name和email字段获取token
    protected $fillable = [
        'username', 'realname', 'email', 'password', 'status', 'avatar',
    ];
    protected $hidden = [
        'password', 'remember_token', 'deleted_at',
    ];
    protected $dates = ['deleted_at'];

    public function findForPassport($username)
    {
        return $this->where('username', $username)->first();
    }

    // 获取用户所在部门

    public function dept()
    {
        return $this->belongsTo('App\Models\Dept', 'dept_id', 'id');
    }

    // 获取用户所在部门
    public function position()
    {
        return $this->belongsTo('App\Models\Position', 'position_id', 'id');
    }

    // 获取用户职级
    public function rank()
    {
        return $this->belongsTo('App\Models\Rank', 'rank_id', 'id');
    }

    // 获取用户离职登记信息

    public function dismiss()
    {
        return $this->hasOne(Dismiss::class, 'user_id', 'id');
    }

    // 获取用户教育经历信息

    public function applyFormalPass()
    {
        return $this->hasOne(ApplyFormal::class, 'user_id', 'id')->where('status', 1);
    }

    // 获取用户绩效分数

    public function performances()
    {
        return $this->hasMany(AchievementAssignUser::class, 'user_id', 'id');
    }

    // 获取用户转正流程

    public function getDataList($inputs = [])
    {
        $records_total = $this->count(); // 记录总数
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $query_where = $this->with('dept', 'position', 'roles');
        $query_where->when(isset($inputs['dept_id']) && is_numeric($inputs['dept_id']), function ($query) use ($inputs) {
            return $query->where('dept_id', $inputs['dept_id']);
        })->when(isset($inputs['keywords']) && !empty($inputs['keywords']), function ($query) use ($inputs) {
            return $query->where(function ($query) use ($inputs) {
                return $query->where('username', 'like', '%' . $inputs['keywords'] . '%')
                    ->orWhere('realname', 'like', '%' . $inputs['keywords'] . '%');
            });
        })->when(isset($inputs['user_ids']) && is_array($inputs['user_ids']), function ($query) use ($inputs) {
            return $query->whereIn('id', $inputs['user_ids']);
        });
        $count = $query_where->count();
        $list = $query_where->orderBy('id', 'desc')->when(!isset($inputs['export']), function ($query) use ($start, $length) {
            return $query->skip($start)->take($length);
        })->get();
        return ['records_total' => $records_total, 'records_filtered' => $count, 'datalist' => $list];
    }

    // 用户绩效

    /**
     * 保存用户数据
     */
    public function storeData($inputs = [])
    {
        $user = new User;
        if (!empty($inputs['id']) && is_numeric($inputs['id'])) {
            $user = $user->where('id', $inputs['id'])->first();
        }
        // 用户登录信息
        $user->username = $inputs['username'];
        $user->realname = $inputs['realname'];
        if (empty($inputs['id'])) {
            $user->password = bcrypt($inputs['password']);
        } else {
            $user->password = empty($inputs['password']) ? $user->password : bcrypt($inputs['password']);
        }
        if (empty($inputs['id'])) {
            $user->status = 1;
        } else {
            if (!empty($inputs['status'])) {
                $user->status = intval($inputs['status']);
            }
        }
        $avatars_directory = storage_path('app/public/images/avatars'); // 存放用户头像的目录
        if (!\File::isDirectory($avatars_directory)) {
            \File::makeDirectory($avatars_directory, $mode = 0777, $recursive = true); // 递归创建目录
        }
        $avatars = '/storage/images/avatars/' . array_random([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]) . '.gif'; // 给默认头像
        $user->avatar = empty($inputs['id']) ? $avatars : $user->avatar;
        // 用户基础信息
        $last_user_id = str_pad($user->orderBy('id', 'desc')->pluck('id')->first(), 3, 0, STR_PAD_LEFT); // 最后一个用户的id
        $dept_name = strtoupper(pinyin_abbr((new \App\Models\Dept)->where('id', $inputs['dept_id'])->pluck('name')->first()));
        $serialnum = 'SD-' . $dept_name . '-' . $last_user_id;
        $user->serialnum = empty($inputs['id']) ? $serialnum : $user->serialnum;
        $user->number = $inputs['number'] ?? 0; //考勤机工号
        $user->sex = $inputs['sex'];
        $user->age = $inputs['age'];
        $user->ethnic = $inputs['ethnic'];
        $user->birthday = $inputs['birthday'];
        $user->politics = $inputs['politics'];
        $user->current_home_address = $inputs['current_home_address'] ?? '';
        $user->id_card = $inputs['id_card'] ?? '';
        $user->census_address = $inputs['census_address'] ?? '';
        $user->dept_id = $inputs['dept_id'];
        $user->rank_id = $inputs['rank_id'];
        $user->position_id = $inputs['position_id'];
        $result = false;
        DB::transaction(function () use ($user, $inputs) {
            $user->save();
            // 合同信息
            $contract = $user->contracts ?: new \App\Models\UserContract;
            $contract->serialnum = $inputs['serialnum'] ?? '';
            $contract->effective_date = $inputs['effective_date'] ?? '';
            $contract->maturity_date = $inputs['maturity_date'] ?? '';
            $contract->entry_date = $inputs['entry_date'] ?? '';
            $contract->positive_date = $inputs['positive_date'] ?? NULL;
            $contract->probational_period_salary = $inputs['probational_period_salary'] ?? '';
            $contract->regular_employee_salary = $inputs['regular_employee_salary'] ?? '';
            $contract->performance = $inputs['performance'] ?? '';
            $contract->bank_card_num = $inputs['bank_card_num'] ?? '';
            $contract->gaowenbutie = $inputs['gaowenbutie'] ?? 0;
            $contract->other_fee = $inputs['other_fee'] ?? 0;
            $contract->remark = $inputs['remark'] ?? '';
            $user->contracts()->save($contract);
            // 教育经历信息
            $education = $user->educations ?: new \App\Models\UserEducation;
            $education->school_name = $inputs['school_name'] ?? '';
            $education->specialty = $inputs['specialty'] ?? '';
            $education->diploma = $inputs['diploma'] ?? '';
            $education->credentials = $inputs['credentials'] ?? '';
            $education->credentials_level = $inputs['credentials_level'] ?? '';
            $user->educations()->save($education);
        }, 5);
        $result = true;
        return $result;
    }

    /*
    * 获取列表数据
    */

    public function contracts()
    {
        return $this->hasOne('App\Models\UserContract', 'user_id', 'id');
    }

    public function educations()
    {
        return $this->hasOne('App\Models\UserEducation', 'user_id', 'id');
    }

    /**
     * 删除用户
     */
    public function destroyUsers($uids = [])
    {
        return $this->destroy($uids);
    }

    /**
     * 用户列表
     */
    public function queryUserList($inputs = [])
    {
        $start = empty($inputs['start']) ? 0 : $inputs['start'];
        $length = empty($inputs['length']) ? 10 : $inputs['length'];
        $querys = $this->with(['dept', 'rank', 'position', 'dismiss'])->where('id', '>', 1)
            ->whereHas('contracts', function ($query) use ($inputs) {
                $query->when(!empty($inputs['positive_start_date']) && !empty($inputs['positive_end_date']), function ($query) use ($inputs) {
                    $query->whereBetween('positive_date', [$inputs['positive_start_date'] . ' 00:00:00', $inputs['positive_end_date'] . ' 23:59:59']);
                })->when(!empty($inputs['maturity_start_date']) && !empty($inputs['maturity_end_date']), function ($query) use ($inputs) {
                    $query->whereBetween('maturity_date', [$inputs['maturity_start_date'] . ' 00:00:00', $inputs['maturity_end_date'] . ' 23:59:59']);
                });
            })
            ->when(!empty($inputs['keyword']), function ($query) use ($inputs) {
                $query->where('realname', 'like', '%' . $inputs['keyword'] . '%');
            })
            ->when(!empty($inputs['dept_id']) && is_numeric($inputs['dept_id']), function ($query) use ($inputs) {
                $query->where('dept_id', $inputs['dept_id']);
            })
            ->when(!empty($inputs['sex']) && is_numeric($inputs['sex']), function ($query) use ($inputs) {
                $query->where('sex', $inputs['sex']);
            })
            ->when(!empty($inputs['rank_id']) && is_numeric($inputs['rank_id']), function ($query) use ($inputs) {
                $query->where('rank_id', $inputs['rank_id']);
            })
            ->when(isset($inputs['user_ids']) && is_array($inputs['user_ids']), function ($query) use ($inputs) {
                $query->whereIn('id', $inputs['user_ids']);
            });
        $count = $querys->count(); // 符合条件的数据
        $users = $querys->when(!isset($inputs['export']), function ($query) use ($start, $length) {
            return $query->skip($start)->take($length);
        })->get();

        $datalist = [];
        $now_date = strtotime(date('Y-m-d'));//当天00：00：00
        foreach ($users as $key => $val) {
            $is_turn_positive = 0; // 试用期
            if ($val->contracts && $val->contracts->positive_date && strtotime($val->contracts->positive_date) <= $now_date) {
                $is_turn_positive = 1; // 转正期
            }
            if ($val->dismiss && $val->dismiss->resign_date && strtotime($val->dismiss->resign_date) <= $now_date) {
                $is_turn_positive = 2; // 已离职
            }
            $val['is_turn_positive'] = $is_turn_positive;
            $datalist[$key] = $val;
        }
        return ['records_filtered' => $count, 'datalist' => $datalist];
    }

    /**
     * 获取单条信息
     */
    public function queryUserInfo($inputs = [])
    {
        $querys = $this->with(['dept', 'rank', 'roles', 'contracts'])
            ->when(!empty($inputs['user_id']) && $inputs['user_id'] > 1, function($query)use($inputs){
                $query->whereHas('contracts', function ($query) use ($inputs) {
                        $query->when(!empty($inputs['positive_start_date']) && !empty($inputs['positive_end_date']), function ($query) use ($inputs) {
                            $query->whereBetween('positive_date', [$inputs['positive_start_date'], $inputs['positive_end_date']]);
                        })->when(!empty($inputs['maturity_start_date']) && !empty($inputs['maturity_end_date']), function ($query) use ($inputs) {
                            $query->whereBetween('maturity_date', [$inputs['maturity_start_date'], $inputs['maturity_end_date']]);
                        });
                });
            })
            ->when(!empty($inputs['user_id']), function ($query) use ($inputs) {
                $query->where('id', $inputs['user_id']);
            });
        return $querys->first();
    }

    /*
    * id对应用户名、id对应真名、id对应昵称、id对应职位、id对应部门
    */
    public function getIdToData()
    {
        
        $list = cache()->remember('user_data', 120, function () {
            // 用户数据-写入缓存
            $user = new User;
            $querys = $user->withTrashed()->with(['dept' => function ($query) {
                        $query->select(['id', 'name']);
                    }])
                    ->with(['rank' => function ($query) {
                        $query->select(['id', 'name']);
                    }]);
            return $querys->get();
        });

        $id_username = $id_realname = $id_dept = $id_rank = $number_id = [];
        foreach ($list as $key => $value) {
            $id_username[$value->id] = $value->username;
            $id_realname[$value->id] = $value->realname;
            if (!empty($value->dept)) {
                $id_dept[$value->id] = $value->dept->name;
            } else {
                $id_dept[$value->id] = '未知';
            }
            if (!empty($value->rank)) {
                $id_rank[$value->id] = $value->rank->name;
            } else {
                $id_rank[$value->id] = '未知';
            }

            $number_id[$value->number] = $value->id;
        }
        return ['id_username' => $id_username, 'id_realname' => $id_realname, 'id_dept' => $id_dept, 'id_rank' => $id_rank, 'number_id' => $number_id];
    }

    // 账号管理->分配权限和权限组
    public function saveUserVisPerms($inputs = [])
    {
        $user = $this->where('id', $inputs['id'])->first();
        $user->password = isset($inputs['password']) && !empty($inputs['password']) ? bcrypt($inputs['password']) : $user->password;
        $roles = isset($inputs['roles']) && is_array($inputs['roles']) ? $inputs['roles'] : [];
        $permissions = isset($inputs['permissions']) && is_array($inputs['permissions']) ? $inputs['permissions'] : [];
        $bool = false;
        try {
            DB::transaction(function () use ($user, $roles, $permissions) {
                $user->save();
                $user->syncRoles($roles);
                $user->syncPermissions($permissions);
            });
            $bool = true;
        } catch (\Exception $e) {
            $bool = false;
        }
        return $bool;
    }

    /**
     * 用户复职更新相关数据
     * renxianyong
     * 2019-1-18
     */
    public function reinstateUpd($inputs = [])
    {
        $user = new User;
        $user = $user->find($inputs['id']);
        $user->dept_id = $inputs['dept_id'];
        $user->position_id = $inputs['position_id'];
        $user->rank_id = $inputs['rank_id'];
        $result = false;
        DB::transaction(function () use ($user, $inputs) {
            $user->save();
            // 合同信息
            $contract = $user->contracts ?: new \App\Models\UserContract;
            $contract->entry_date = $inputs['entry_date'] ?? '';//复职时间
            $contract->positive_date = $inputs['positive_date'] ?? null;//转正时间
            $contract->probational_period_salary = $inputs['probational_period_salary'] ?? '';//试用工资
            $contract->regular_employee_salary = $inputs['regular_employee_salary'] ?? '';//转正基础工资
            $contract->performance = $inputs['performance'] ?? '';//绩效
            $contract->remark = $inputs['remark'] ?? '';//复职说明
            $user->contracts()->save($contract);
            //删除该用户离职信息
            $user->dismiss()->where('user_id', $inputs['id'])->delete();
        }, 5);
        $result = true;
        return $result;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Dismiss;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Medz\IdentityCard\China\Identity;

class UserController extends Controller
{
    /**
     * 添加用户
     * @Author: qinjintian
     * @Date:   2018-09-07
     */
    public function store()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 'create':
                // 加载添加表单时候需要的表单数据
                $depts = getTree((new \App\Models\Dept)->all(), 0); // 部门
                $ranks = (new \App\Models\Rank)->get(); // 职级
                $positions = (new \App\Models\Position)->get(); // 岗位
                $max_id = (new \App\Models\User)->max('id');
                $number = $max_id + 1;
                return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => ['depts' => $depts, 'ranks' => $ranks, 'positions' => $positions, 'number' => $number]]);
                break;
            default:
                // 添加用户操作
                return $this->addUser($inputs);
        }
    }

    /**
     * 批量导入员工信息
     * @Author: qinjintian
     * @Date:   2018-10-31
     */
    public function importUser()
    {
        $rules = ['file' => 'required|file|max:10240'];
        $attributes = ['file' => 'Excel文件'];
        $validator = validator(request()->all(), $rules, [], $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        if (request()->isMethod('post')) {
            $file = request()->file('file');
            // 文件是否上传成功
            if ($file->isValid()) {
                // 获取文件相关信息
                $original_name = $file->getClientOriginalName(); // 文件原名
                $ext = $file->getClientOriginalExtension();  // 扩展名
                $real_path = $file->getRealPath(); // 临时文件的绝对路径
                $type = $file->getClientMimeType(); // image/jpeg
                if (!in_array($ext, ['xls', 'xlsx'])) {
                    return ['code' => -1, 'message' => '只能上传Excel文件'];
                }
                // 上传文件
                $filename = date('YmdHis') . uniqid() . '.' . $ext;
                // 使用我们新建的public/uploads本地存储空间（目录）
                $fileinfo = $file->move(storage_path('app/public/uploads'), $filename);
                $user_data = importExcel($fileinfo->getPathname(), [0], 3);
                $user_model = new \App\Models\User;
                $dept_model = new \App\Models\Dept;
                $rank_model = new \App\Models\Rank;
                $position_model = new \App\Models\Position;
                $import_succ_count = 0; // 导入成功数
                try {
                    foreach ($user_data['data']['sheets'] as $key => $row) {
                        $username_exist = $user_model->where(['username' => trim($row[7])])->withTrashed()->count();
                        if ($username_exist > 0) {
                            continue;
                        }
                        $user['realname'] = trim($row[0]);
                        $user['ethnic'] = trim($row[1]);
                        if (trim($row[2]) == '市民') {
                            $user['politics'] = 1;
                        } else if (trim($row[2]) == '团员') {
                            $user['politics'] = 2;
                        } else if (trim($row[2]) == '党员') {
                            $user['politics'] = 3;
                        } else {
                            $user['politics'] = 4;
                        }
                        $user['current_home_address'] = trim($row[3]);
                        $user['id_card'] = trim($row[4]);
                        $peopleIdentity = new Identity($user['id_card']);//身份证插件
                        if (!$peopleIdentity->legal()) {
                            return ['code' => -1, 'message' => '第' . ($key + 1) . '行身份证号码不合法，请检查'];
                        }
                        $user['census_address'] = trim($row[5]);
                        $user['number'] = trim($row[6]);
                        $user['username'] = trim($row[7]);
                        $user['password'] = trim($row[8]);
                        $user['dept_id'] = $dept_model->where('name', trim($row[9]))->value('id') ?? 0;
                        if ($user['dept_id'] < 1) {
                            return ['code' => -1, 'message' => '第' . ($key + 1) . '行部门字段输入有误或不存在这个部门，请检查'];
                        }
                        if ($user_model->where(['username' => trim($row[7]), 'dept_id' => $user['dept_id']])->count() > 0) {
                            continue;
                        }
                        $user['rank_id'] = $rank_model->where('name', trim($row[10]))->value('id') ?? 0;
                        if ($user['rank_id'] < 1) {
                            return ['code' => -1, 'message' => '第' . ($key + 1) . '行职级字段输入有误或不存在这个职级，请检查'];
                        }
                        $user['position_id'] = $position_model->where('name', trim($row[11]))->value('id') ?? 0;
                        if ($user['position_id'] < 1) {
                            return ['code' => -1, 'message' => '第' . ($key + 1) . '行岗位字段输入有误或不存在这个岗位，请检查'];
                        }
                        $user['serialnum'] = trim($row[12]);
                        $user['effective_date'] = date('Y-m-d', strtotime(trim($row[13])));
                        $user['maturity_date'] = date('Y-m-d', strtotime(trim($row[14])));
                        $user['entry_date'] = date('Y-m-d', strtotime(trim($row[15])));
                        $user['positive_date'] = date('Y-m-d', strtotime(trim($row[16])));
                        $user['probational_period_salary'] = round(trim($row[17]), 2);
                        $user['regular_employee_salary'] = round(trim($row[18]), 2);
                        $user['performance'] = round(trim($row[19]), 2);
                        $user['other_fee'] = round(trim($row[20]), 2);
                        $user['bank_card_num'] = trim($row[21]);
                        $user['school_name'] = trim($row[22]);
                        $user['specialty'] = trim($row[23]);
                        if (trim($row[24]) == '初中') {
                            $user['diploma'] = 1;
                        } else if (trim($row[24]) == '高中/中专') {
                            $user['diploma'] = 2;
                        } else if (trim($row[24]) == '专科') {
                            $user['diploma'] = 3;
                        } else if (trim($row[24]) == '本科') {
                            $user['diploma'] = 4;
                        } else if (trim($row[24]) == '硕士') {
                            $user['diploma'] = 5;
                        } else if (trim($row[24]) == '研究生') {
                            $user['diploma'] = 6;
                        } else if (trim($row[24]) == '博士') {
                            $user['diploma'] = 7;
                        }
                        $user['credentials'] = trim($row[25]);
                        $user['credentials_level'] = trim($row[26]);
                        //使用身份证插件获取相关数据
                        $user['sex'] = $peopleIdentity->gender() == '男' ? 1 : 2 ;
                        $user['age'] = date('Y')-(int)(explode('-',$peopleIdentity->birthday())[0]);
                        $user['birthday'] = $peopleIdentity->birthday();
                        $bool = $user_model->storeData($user);
                        if ($bool) {
                            $import_succ_count++;
                        }
                    }
                    unlink($fileinfo->getPathname());
                } catch (\Exception $e) {
                    unlink($fileinfo->getPathname());
                    return ['code' => 0, 'message' => $e->getMessage()];
                }
                if($import_succ_count > 0){
                    $result = '已成功导入' . $import_succ_count . '个用户';
                    systemLog('用户管理','批量导入了['.$import_succ_count.']个用户');
                }else{
                    $result = '没有可导入的用户，可能表格和系统存在重复数据，请检查';
                }
                return ['code' => 1, 'message' => $result];
            }
        }
    }

    /**
     * 修改用户
     * @Author: qinjintian
     * @Date:   2018-09-17
     */
    public function update()
    {
        $inputs = request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 'edit':
                // 加载表单时候需要的表单数据
                $user_id = $inputs['id'] ?? 0;
                $user = (new \App\Models\User)->where('id', $user_id)->first();
                if (!$user) {
                    return response()->json(['code' => 0, 'message' => '用户不存在，请检查']);
                }
                $depts = getTree((new \App\Models\Dept)->all(), 0); // 部门
                $ranks = (new \App\Models\Rank)->get(); // 职级
                $positions = (new \App\Models\Position)->get(); // 岗位
                $form_data['user'] = $user; // 用户信息
                $form_data['contracts'] = $user->contracts()->first(); // 合同信息
                $form_data['educations'] = $user->educations()->first(); // 教育经历信息
                return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => ['form_data' => $form_data, 'depts' => $depts, 'ranks' => $ranks, 'roles' => $positions]]);
                break;
            default:
                // 修改用户
                return $this->editUser($inputs);
        }
    }

    /**
     * 删除用户
     * @Author: qinjintian
     * @Date:   2018-09-07
     */
    public function destroy()
    {
        $uids = request()->input('uids');
        if (!isset($uids) || !is_array($uids) || count($uids) < 1) {
            return response()->json(['code' => -1, 'message' => '请选择至少一个用户，且只能是数组，请检查']);
        }
        if (in_array(1, $uids)) {
            return response()->json(['code' => -1, 'message' => '系统超级管理员账号不允许删除，请检查']);
        }
        $user = new \App\Models\User;
        $deleted = $user->destroyUsers($uids);
        $response = $deleted ? ['code' => 1, 'message' => '成功删除' . $deleted . '个用户'] : ['code' => 0, 'message' => '操作失败，请重试'];
        return response()->json($response);
    }

    /**
     * 用户列表
     * @Author: qinjintian
     * @Date:   2018-09-07
     */
    public function index()
    {
        $inputs = request()->all();
        $resources = (new \App\Models\User)->queryUserList($inputs);
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $resources]);
    }


    /**
     * 导出
     * @Author: molin
     * @Date:   2018-11-01
     */
    public function export()
    {
        $inputs = request()->all();
        $data = [];
        $inputs['all'] = 1;//全部数据
        $user = new \App\Models\User;
        $fields = ['realname' => '姓名', 'sex' => '性别', 'age' => '年龄', 'ethnic' => '民族', 'birthday' => '出生年月日', 'politics' => '政治面貌', 'current_home_address' => '现住地址', 'id_card' => '身份证', 'census_address' => '身份证地址', 'dept' => '部门', 'rank' => '职级', 'position' => '岗位', 'serialnum' => '员工编号'];
        if (isset($inputs['request_type']) && $inputs['request_type'] == 'load') {
            $data = $user->queryUserList($inputs);
            $data['fields'] = $fields;
            $dept = new \App\Models\Dept;
            $dept_list = $dept->where('status', 1)->select(['id', 'name'])->get();
            $data['dept_list'] = $dept_list;
            $sex_list = [['id' => 1, 'name' => '男'], ['id' => 2, 'name' => '女']];
            $data['sex_list'] = $sex_list;
            $rank = new \App\Models\Rank;
            $rank_list = $rank->where('status', 1)->select(['id', 'name'])->get();
            $data['rank_list'] = $rank_list;
            return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $data]);
        }
        if (!isset($inputs['fields']) || !is_array($inputs['fields'])) {
            return response()->json(['code' => -1, 'message' => '缺少导出内容字段']);
        }
        $inputs['export'] = 1;//导出
        $data = $user->queryUserList($inputs);
        $export_data = $export_head = [];
        foreach ($inputs['fields'] as $f) {
            $export_head[] = $fields[$f];
        }
        foreach ($data['datalist'] as $key => $value) {
            foreach ($inputs['fields'] as $f) {
                $export_data[$key][$f] = $value->$f;
                if ($f == 'sex') {
                    $export_data[$key][$f] = $value->$f == 1 ? '男' : '女';
                }
                if ($f == 'id_card') {
                    $export_data[$key][$f] = "\t" . $value->$f;
                }
                if ($f == 'dept') {
                    $export_data[$key][$f] = $value->dept->name;
                }
                if ($f == 'rank') {
                    $export_data[$key][$f] = $value->rank->name;
                }
                if ($f == 'position') {
                    $export_data[$key][$f] = $value->position->name;
                }

            }

        }
        $filedata = pExprot($export_head, $export_data, 'user_list');
        $filepath = 'storage/exports/' . $filedata['file'];//下载链接
        $fileurl = asset('storage/exports/' . $filedata['file']);//下载链接
        return response()->json(['code' => 1, 'message' => '导出成功', 'filepath' => $filepath, 'fileurl' => $fileurl]);
    }

    /**
     * 用户详情
     * @Author: qinjintian
     * @Date:   2018-09-17
     */
    public function show()
    {
        $uid = request()->input('uid', '0');
        $user = new \App\Models\User;
        $user = $user->where('id', $uid)->first();
        if (!$user) {
            return ['code' => 0, 'message' => '不存在这个用户，请检查'];
        }
        $is_turn_positive = 0;//试用期
        $now_date = strtotime(date('Y-m-d'));//当天00：00：00
        if ($user->contracts && $user->contracts->positive_date && strtotime($user->contracts->positive_date) <= $now_date) {
            $is_turn_positive = 1; // 转正期
        }
        if ($user->dismiss && $user->dismiss->resign_date && strtotime($user->dismiss->resign_date) <= $now_date) {
            $is_turn_positive = 2; // 已离职
        }
        $user->is_turn_positive = $is_turn_positive;
        $resources['base'] = $user;
        $resources['dept'] = $user->dept()->first();
        $resources['rank'] = $user->rank()->first();
        $resources['position'] = $user->position()->get();
        $resources['contracts'] = $user->contracts()->first();
        $resources['educations'] = $user->educations()->first();
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $resources]);
    }

    /**
     * 账号列表
     * @Author: qinjintian
     * @Date:   2018-11-09
     */
    public function accountList()
    {
        $inputs = \request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case '1':
                // 账号列表页面搜索条件
                $data = [];
                $data['depts'] = (new \App\Models\Dept())->get();
                return ['code' => 1, 'message' => '数据获取成功', 'data' => $data];
                break;
            default:
                // 账号列表数据
                $user_model = new \App\Models\User();
                $data = $user_model->getDataList($inputs);
                return ['code' => 1, 'message' => '数据获取成功', 'data' => $data];
        }
    }

    /**
     * 账号管理 -> 编辑账号
     * @Author: qinjintian
     * @Date:   2018-11-12
     */
    public function edit()
    {
        $inputs = \request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case '1':
                // 账号管理->编辑账号
                return $this->getAccountFormData($inputs);
                break;
            default:
                // 编辑账号 ->保存权限和权限组
                if (empty($inputs['id'])) {
                    return ['code' => -1, 'message' => '账号ID不能为空'];
                }
                $user_model = new \App\Models\User();
                $user_name = $user_model->where('id',$inputs['id'])->value('realname');
                $result = $user_model->saveUserVisPerms($inputs);
                if($result){
                    $response = ['code' => 1, 'message' => '操作成功'];
                    systemLog('账号管理','编辑了账号['.$user_name.']');
                }else{
                    $response = ['code' => 0, 'message' => '操作失败，请重试'];
                }
                return $response;
        }
    }

    /**
     * 员工管理 -> 离职
     * @Author: qinjintian
     * @Date:   2019-01-09
     */
    public function resign()
    {
        $inputs = \request()->all();
        $request_type = isset($inputs['request_type']) ? $inputs['request_type'] : '';
        switch ($request_type) {
            case '1':
                // 离职表单数据
                return $this->resignFormData($inputs);
                break;
            default:
                // 离职记录
                return $this->resignRegister($inputs);
        }
    }

    /**
     * 员工离职记录
     * @param $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    private function resignFormData($inputs)
    {
        $user_id = $inputs['user_id'] ?? 0;
        if ($user_id < 1) {
            return ['code' => 0, 'message' => '不存在这个用户，请检查'];
        }
        $user_id = request()->input('user_id', '0');
        $user_model = new User;
        $user = $user_model->where('id', $user_id)->first();
        $resources = [];
        $resources['base'] = $user;
        $resources['dept'] = $user->dept()->first();
        $resources['rank'] = $user->rank()->first();
        $resources['position'] = $user->position()->get();
        $resources['contracts'] = $user->contracts()->first();
        $resources['educations'] = $user->educations()->first();
        return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => $resources]);
    }

    /**
     * 用户复职
     * @Author: renxianyong
     * @Date:   2019-01-17
     */
    public function reinstatement()
    {
        $inputs = \request()->all();
        $request_type = $inputs['request_type'] ?? '';
        switch ($request_type) {
            case 'edit':
                //加载表单时所需要的数据
                $user_id = $inputs['id'] ?? 0;
                $user = (new \App\Models\User)->where('id', $user_id)->first();// 员工信息
                if (!$user) {
                    return response()->json(['code' => 0, 'message' => '用户不存在，请检查']);
                }
                $depts = getTree((new \App\Models\Dept)->all(), 0); // 部门
                $positions = (new \App\Models\Position)->get(); // 岗位
                $ranks = (new \App\Models\Rank)->get();//职级
                $user->contracts = $user->contracts()->first(); // 入职和工资信息
                $user->dismiss = $user->dismiss()->first(['resign_date']);//离职信息
                return response()->json(['code' => 1, 'message' => '获取数据成功', 'data' => ['user' => $user, 'depts' => $depts, 'roles' => $positions, 'ranks' => $ranks]]);
                break;
            default:
                // 更新用户复职信息
                $rules = [
                    'dept_id' => 'required|integer|min:1',
                    'position_id' => 'required|integer|min:1',
                    'rank_id' => 'required|integer|min:1',
                    'entry_date' => 'date_format:Y-m-d|required',
                    'positive_date' => 'nullable|date_format:Y-m-d',
                    'probational_period_salary' => 'required|numeric|min:0',
                    'regular_employee_salary' => 'required|numeric|min:0',
                    'performance' => 'required|numeric|min:0',
                ];
                $attributes = [
                    'dept_id' => '部门',
                    'position_id' => '岗位',
                    'rank_id' => '职级',
                    'entry_date' => '复职日期',
                    'positive_date' => '转正日期',
                    'probational_period_salary' => '试用工资',
                    'regular_employee_salary' => '转正工资',
                    'performance' => '绩效',
                ];
                $validator = validator($inputs, $rules, [], $attributes);
                if ($validator->fails()) {
                    return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
                }
                $user_model = new \App\Models\User;
                $user = $user_model->find($inputs['id']);
                $dismiss = $user->dismiss()->first();
                if(!$dismiss){
                    return response()->json(['code'=> 0,'message' => '该用户没用离职']);
                }
                $result = $user_model->reinstateUpd($inputs);
                if($result){
                    $response = ['code' => 1, 'message' => '操作成功'];
                    systemLog('用户管理','操作了['.$user["realname"].']复职');
                }else{
                    $response = ['code' => 0, 'message' => '操作失败，请重试'];
                }
                return response()->json($response);
        }
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    private function addUser(array $inputs)
    {
        $rules = [
            'username' => 'required|alpha_num|min:1|max:20|unique:users',
            'realname' => 'required|min:2|max:5',
            'password' => 'required|min:6|max:20',
            'number' => 'required|integer|min:0|unique:users',
            'sex' => 'required|integer|min:0|max:2',
            'age' => 'required|integer|min:16|max:100',
            'ethnic' => 'required|min:1|max:32',
            'birthday' => 'date_format:Y-m-d|required',
            'politics' => 'required|integer|min:0',
            'dept_id' => 'required|integer|min:1',
            'rank_id' => 'required|integer|min:1',
            'position_id' => 'required|integer|min:1',
            'serialnum' => 'required|unique:user_contracts',
            'effective_date' => 'date_format:Y-m-d|required',
            'maturity_date' => 'date_format:Y-m-d|required',
            'entry_date' => 'date_format:Y-m-d|required',
            'positive_date' => 'nullable|date_format:Y-m-d',
            'probational_period_salary' => 'required|numeric|min:0',
            'regular_employee_salary' => 'required|numeric|min:0',
            'performance' => 'required|numeric|min:0',
        ];
        $attributes = [
            'username' => '用户名',
            'realname' => '真实姓名',
            'password' => '密码',
            'number' => '考勤机工号',
            'sex' => '性别',
            'age' => '年龄',
            'ethnic' => '民族',
            'birthday' => '生日',
            'politics' => '政治面貌',
            'dept_id' => '部门',
            'rank_id' => '职级',
            'position_id' => '岗位',
            'serialnum' => '合同编号',
            'effective_date' => '合同生效时间',
            'maturity_date' => '合同到期时间',
            'entry_date' => '入职日期',
            'positive_date' => '转正日期',
            'probational_period_salary' => '试用工资',
            'regular_employee_salary' => '转正工资',
            'performance' => '绩效',
        ];

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $user = new \App\Models\User;
        $result = $user->storeData($inputs);
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('用户管理','添加了一个用户['.$inputs["realname"].']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * @param array $inputs
     * @return array|\Illuminate\Http\JsonResponse
     */
    private function editUser(array $inputs)
    {
        $rules = [
            'username' => 'required|alpha_num|min:1|max:20|unique:users,username,' . $inputs['id'],
            'realname' => 'required|min:2|max:5',
            'sex' => 'required|integer|min:0|max:2',
            'age' => 'required|integer|min:16|max:100',
            'ethnic' => 'required|min:1|max:32',
            'birthday' => 'date_format:Y-m-d|required',
            'politics' => 'required|integer|min:0',
            'dept_id' => 'required|integer|min:1',
            'rank_id' => 'required|integer|min:1',
            'position_id' => 'required|integer|min:1',
            'serialnum' => 'required|unique:user_contracts,serialnum,' . $inputs['id'] . ',user_id',
            'effective_date' => 'date_format:Y-m-d|required',
            'maturity_date' => 'date_format:Y-m-d|required',
            'entry_date' => 'date_format:Y-m-d|required',
            'positive_date' => 'nullable|date_format:Y-m-d',
            'probational_period_salary' => 'required|numeric|min:0',
            'regular_employee_salary' => 'required|numeric|min:0',
            'performance' => 'required|numeric|min:0',
        ];
        $attributes = [
            'username' => '用户名',
            'realname' => '真实姓名',
            'number' => 'number 考勤机工号',
            'password' => '密码',
            'sex' => '性别',
            'age' => '年龄',
            'ethnic' => '民族',
            'birthday' => '生日',
            'politics' => '政治面貌',
            'dept_id' => '部门',
            'rank_id' => '职级',
            'position_id' => '岗位',
            'serialnum' => '合同编号',
            'effective_date' => '合同生效时间',
            'maturity_date' => '合同到期时间',
            'entry_date' => '入职日期',
            'positive_date' => '转正日期',
            'probational_period_salary' => '试用工资',
            'regular_employee_salary' => '转正工资',
            'performance' => '绩效',
        ];

        if (isset($inputs['number']) && is_numeric($inputs['number']) && $inputs['number'] > 0) {
            $rules['number'] = ['required', 'integer', 'min:0', Rule::unique('users')->ignore($inputs['id'])];
        } else {
            $rules['number'] = ['required', 'integer', 'min:0'];
        }

        $validator = validator($inputs, $rules, [], $attributes);
        if ($validator->fails()) {
            return response()->json(['code' => -1, 'message' => $validator->errors()->first()]);
        }
        $user = new \App\Models\User;
        $result = $user->storeData($inputs);
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('用户管理','修改了用户['.$inputs["realname"].']的信息');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function resignRegister(array $inputs): array
    {
        $rules = [
            'user_id' => 'required|integer|min:1',
            'resign_date' => 'required|date|date_format:"Y-m-d"',
        ];
        $attributes = [
            'user_id' => '用户ID',
            'resign_date' => '离职日期',
        ];
        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }
        $dismiss_model = new Dismiss;
        $user = $dismiss_model->where('user_id',$inputs['user_id'])->first();//查看数据库是否有该用户
        if($user){
            return ['code' => -1, 'message' => '该用户已离职'];
        }
        $dismiss_model->user_id = $inputs['user_id'];
        $dismiss_model->resign_date = $inputs['resign_date'];
        $dismiss_model->explain = $inputs['explain'] ?? '';
        $result = $dismiss_model->save();
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            $user_info = (new User())->where('id',$inputs['user_id'])->first();
            $realname = $user_info['realname'];
            systemLog('用户管理','操作了['.$realname.']离职');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * 首页修改密码
     * @Author: qinjintian
     * @Date:   2019-02-12
     */
    public function changePassword()
    {
        $inputs = request()->all();
        $rules = [
            'old_password' => 'required',
            'new_password' => 'required|min:6|max:20',
            'confirm_password' => 'required',
        ];
        $attributes = [
            'old_password' => '旧密码',
            'new_password' => '新密码',
            'confirm_password' => '确认密码',
        ];
        $messages = [];
        $validator = validator($inputs, $rules, $messages, $attributes);
        if ($validator->fails()) {
            return ['code' => -1, 'message' => $validator->errors()->first()];
        }

        if ($inputs['new_password'] !== $inputs['confirm_password']) {
            return ['code' => -1, 'message' => '两次密码不一致，请检查'];
        }

        $user = User::where('id', auth()->id())->first();
        if (!\Hash::check($inputs['old_password'], $user->password)) {
            return response()->json(['code' => -1, 'message' => '原密码错误，请检查']);
        }

        $user->password = bcrypt($inputs['new_password']);
        $result = $user->save();
        if ($result) {
            $response = ['code' => 1, 'message' => '修改成功'];
            systemLog('用户管理','修改密码');
        } else {
            $response = ['code' => 0, 'message' => '修改失败，请重试'];
        }
        return $response;
    }

    /**
     * 账号启用或者禁用
     * @Author: renxianyong
     * @Date:   2019-02-14
     */
    public function using(User $user)
    {
        $inputs = \request()->input();
        $status = $user->where('id',$inputs['id'])->value('status');
        $status = $status == 1 ? 0 : 1;
        $userInfo = $user->find($inputs['id']);
        $realname = $userInfo['realname'];
        $userInfo->status = $status;
        $result = $userInfo->save();
        if($result){
            $response = ['code' => 1, 'message' => '操作成功'];
            systemLog('账号管理','启用或禁用账号['.$realname.']');
        }else{
            $response = ['code' => 0, 'message' => '操作失败，请重试'];
        }
        return $response;
    }

    /**
     * @param array $inputs
     * @return array
     */
    private function getAccountFormData(array $inputs): array
    {
        if (!isset($inputs['id']) && empty($inputs['id'])) {
            return ['code' => -1, 'message' => '账号ID不能为空'];
        }
        $data = [];
        $role_model = new \App\Models\Role();
        $roles = $role_model->get();
        $user = User::where('id', $inputs['id'])->first();
        $dept = $user->dept()->first();
        $position = $user->position()->first();
        $user_permissions = $user->getDirectPermissions(); // 当前角色的权限
        $un_format_permissions = Permission::all();
        $all_permission_parent_ids = $un_format_permissions->unique('parent_id')->pluck('parent_id')->toArray();
        $leafs = [];
        $un_leafs = [];
        foreach ($user_permissions as $key => $val) {
            if (in_array($val['id'], $all_permission_parent_ids)) {
                array_push($leafs, $val['id']);
            } else {
                array_push($un_leafs, $val['id']);
            }
        }
        $user_has_permissions = [];
        $user_has_permissions['leafs'] = $leafs;
        $user_has_permissions['un_leafs'] = $un_leafs;

        $user_base = [];
        $user_base['id'] = $user['id'];
        $user_base['username'] = $user['username'];
        $user_base['realname'] = $user['realname'];
        $user_base['serialnum'] = $user['serialnum'];
        $user_base['sex_cn'] = $user['sex'] == 1 ? '男' : '女';
        $user_base['ethnic'] = $user['ethnic'];
        $user_base['birthday'] = $user['birthday'];
        $user_base['dept_name'] = $user['dept_name'];
        $user_base['position_name'] = $user['position_name'];
        $data['user'] = $user_base;
        $data['user']['dept_name'] = $dept['name'];
        $data['user']['position_name'] = $position['name'];
        $data['user']['user_has_roles'] = $user->roles()->pluck('id');
        $data['user']['user_has_permissions'] = $user_has_permissions;
        $data['roles'] = $roles;
        $data['permissions'] = getTree($un_format_permissions, 0);
        return ['code' => 1, 'message' => '获取成功', 'data' => $data];
    }
}

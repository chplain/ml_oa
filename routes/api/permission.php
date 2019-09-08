<?php

/*
|--------------------------------------------------------------------------
| Check Permission API Routes 登录后且分配有权限才能访问接口
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['auth:api', 'check.permission'], 'namespace' => 'Api'], function () {
    // 用户管理
    Route::post('user/store', 'UserController@store')->name('member-index-add'); // 添加用户
    Route::post('user/importuser', 'UserController@importUser')->name('member-index-importd'); // 导入员工
    Route::post('user/update', 'UserController@update')->name('member-index-edit'); // 修改用户
    Route::post('user/index', 'UserController@index')->name('member-index'); // 用户列表
    Route::post('user/resign', 'UserController@resign')->name('member-index-fire'); // 离职登记
    Route::post('user/show', 'UserController@show')->name('member-index-detail'); // 用户详情
    Route::post('user/export', 'UserController@export')->name('member-index-export'); // 导出员工
    Route::post('user/reinstatement', 'UserController@reinstatement')->name('member-index-resume');//用户复职

    // 权限组管理
    Route::post('role/store', 'RoleController@store')->name('setting-access-group-add'); // 添加权限组
    Route::post('role/update', 'RoleController@update')->name('setting-access-group-edit'); // 修改权限组
    Route::post('role/index', 'RoleController@index')->name('setting-access-group'); // 权限组列表

    // 权限管理
    Route::post('permission/store', 'PermissionController@store')->name('setting-access-index-add'); // 添加权限
    Route::post('permission/update', 'PermissionController@update')->name('setting-access-index-edit'); // 修改权限
    Route::post('permission/index', 'PermissionController@index')->name('setting-access-index'); // 权限列表

    // 职级管理
    Route::post('rank/store', 'RankController@store')->name('member-setting-rank-add'); // 添加职级
    Route::post('rank/update', 'RankController@update')->name('member-setting-rank-edit'); // 修改职级
    Route::post('rank/index', 'RankController@index')->name('member-setting-rank'); // 职级列表

    // 岗位管理
    Route::post('position/store', 'PositionController@store')->name('member-setting-role-add'); // 添加岗位
    Route::post('position/update', 'PositionController@update')->name('member-setting-role-edit'); // 修改岗位
    Route::post('position/index', 'PositionController@index')->name('member-setting-role'); // 岗位列表
    Route::post('position/show', 'PositionController@show')->name('member-setting-role-detail'); // 岗位详情
    Route::post('position/destroy', 'PositionController@destroy')->name('member-setting-role-destroy'); // 删除岗位

    // 部门管理
    Route::post('dept/store', 'DeptController@store')->name('member-setting-dept-add'); // 添加部门
    Route::post('dept/update', 'DeptController@update')->name('member-setting-dept-edit'); // 修改部门
    Route::post('dept/index', 'DeptController@index')->name('member-setting-dept'); // 部门列表
    Route::post('dept/destroy', 'DeptController@destroy')->name('member-setting-dept-destroy'); // 删除部门
    Route::post('dept/show', 'DeptController@show')->name('member-setting-dept-detail'); // 查看详情

    // 账号管理
    Route::post('user/accountlist', 'UserController@accountList')->name('setting-account-index'); // 账号列表
    Route::post('user/edit', 'UserController@edit')->name('setting-account-index-edit'); // 编辑账号
    Route::post('user/using', 'UserController@using')->name('setting-account-index-using');//账号启动或禁用

    // 薪酬管理
    Route::post('salary/mysalary', 'SalaryController@mySalary')->name('wages-index'); // 我的工资
    Route::post('salary/confirm', 'SalaryController@confirm')->name('wages-index-confirm'); // 确认工资
    Route::post('salary/summary', 'SalaryController@summary')->name('wages-collect'); // 工资汇总
    Route::post('salary/create', 'SalaryController@create')->name('wages-collect-add'); // 创建工资表
    Route::post('salary/import', 'SalaryController@import')->name('wages-collect-import'); // 导入最终工资单
    Route::post('salary/publish', 'SalaryController@publish')->name('wages-collect-publish'); // 发布工资
    Route::post('salary/show', 'SalaryController@show')->name('wages-collect-detail'); // 查看详情
    Route::post('salary/update', 'SalaryController@update')->name('wages-collect-edit'); // 修改薪酬
    Route::post('salary/remark', 'SalaryController@remark')->name('wages-collect-remark'); // 填写备注
    Route::post('salary/statistic', 'SalaryController@statistic')->name('wages-collect-statistic'); // 查看统计
    Route::post('salary/historyStatistic', 'SalaryController@historyStatistic')->name('wages-collect-historyStatistic'); // 查看历史统计

    // 日报管理
    Route::post('daily/mydaily', 'DailyController@myDaily')->name('report-list-index'); // 我的日报
    Route::post('daily/replenish', 'DailyController@replenish')->name('report-list-index-resubmit'); // 补写日报
    Route::post('daily/update', 'DailyController@update')->name('report-list-index-edit'); // 修改日报
    Route::post('daily/mydailyshow', 'DailyController@myDailyShow')->name('report-list-index-detail'); // 我的日报详情
    Route::post('daily/mydeptdaily', 'DailyController@myDeptDaily')->name('report-list-department'); // 我的部门日报
    Route::post('daily/notification', 'DailyController@notification')->name('report-list-department-notification'); // 我的部门日报 - 消息提醒
    Route::post('daily/summary_notification', 'DailyController@notification')->name('report-list-summary-notification'); // 日报汇总 - 消息提醒
    Route::post('daily/deptdailyshow', 'DailyController@myDeptDailyShow')->name('report-list-department-detail'); // 我的部门日报查看详情
    Route::post('daily/summary', 'DailyController@summary')->name('report-list-summary'); // 日报汇总
    Route::post('daily/summaryshow', 'DailyController@summaryShow')->name('report-list-summary-detail'); // 日报汇总查看详情
    Route::post('daily/statistical', 'DailyController@statistical')->name('report-list-summary-analysis'); // 日报统计
    Route::post('daily/basissetting', 'DailyController@basisSetting')->name('report-setting-index'); // 基础设置
    Route::post('daily/weeksetting', 'DailyController@weekSetting')->name('report-setting-weeksetting'); // 周报提交日期设置
    Route::post('daily/reportuser', 'DailyController@reportUser')->name('report-setting-index-member'); // 设置报表人员
    Route::post('daily/setting', 'DailyController@setting')->name('report-setting-personal'); // 我的日报设置

    //行业设置
    Route::post('trade/index', 'TradeController@index')->name('project-setting-index'); // 行业列表
    Route::post('trade/store', 'TradeController@store')->name('project-setting-index-add'); // 添加行业
    Route::post('trade/update', 'TradeController@update')->name('project-setting-index-edit'); // 编辑行业

    //操作记录
    Route::post('systemlog/index', 'SystemLogController@index')->name('setting-logs-access'); // 操作记录列表
    Route::post('systemlog/login', 'SystemLogController@login')->name('setting-logs-index'); // 操作记录列表
    Route::post('systemlog/show', 'SystemLogController@show')->name('setting-logs-access-detail'); // 操作记录详情

    //出勤管理
    Route::post('attendance/store', 'AttendanceSettingController@store')->name('attendance-setting-index'); // 出勤设置
    Route::post('attendance/holiday', 'AttendanceSettingController@holiday')->name('attendance-setting-index-holiday'); // 节假日设置
    Route::post('attendance/detail', 'AttendanceRecordController@detail')->name('attendance-index-self'); // 考勤记录
    Route::post('attendance/index', 'AttendanceRecordController@index')->name('attendance-index'); // 考勤管理主体
    Route::post('attendance/report', 'AttendanceRecordController@report')->name('attendance-index-report'); // 报备
    Route::post('attendance/show', 'AttendanceRecordController@show')->name('attendance-index-detail'); // 假期详情
    Route::post('holiday/index', 'HolidayTypeController@index')->name('attendance-vacation-index'); // 假期类型-列表
    Route::post('holiday/using', 'HolidayTypeController@using')->name('attendance-vacation-index-toggle'); // 假期类型-启用、禁用
    Route::post('holiday/edit', 'HolidayTypeController@edit')->name('attendance-vacation-index-edit'); // 假期类型-编辑
    Route::post('holiday/store', 'HolidayTypeController@store')->name('attendance-vacation-index-add'); // 假期类型-添加
    Route::post('holiday/detail', 'HolidayTypeController@detail')->name('attendance-vacation-details'); // 假期明细
    Route::post('holiday/show', 'HolidayTypeController@show')->name('attendance-vacation-details-view'); // 假期明细-查看详情
    Route::post('holiday/year', 'HolidayYearController@store')->name('attendance-vacation-annual'); // 年假配置
    Route::post('holiday/reward', 'HolidayYearController@reward')->name('attendance-vacation-reward'); // 年假奖励
    Route::post('holiday/deduct', 'HolidayYearController@deduct')->name('attendance-vacation-deduct'); // 年假扣减

    //出勤统计
    Route::post('attendance/stat', 'AttendanceStatController@index')->name('attendance-statistics-index');
    Route::post('attendance/statdetail', 'AttendanceStatController@detail')->name('attendance-statistics-detail');//考勤时间详情

    //工作流程
    Route::post('apply_setting/index', 'ApplyTypeController@index')->name('form-index-list'); // 表单列表
    Route::post('apply_setting/enable', 'ApplyTypeController@enable')->name('form-index-toggle'); // 表单启用、禁用
    Route::post('apply_setting/edit', 'ApplyTypeController@edit')->name('form-index-steup'); // 流程编辑
    Route::post('apply_formals/store', 'ApplyFormalsController@store')->name('approval-correction'); // 转正申请
    Route::post('apply_formals/index', 'ApplyFormalsController@index')->name('member-correction-index'); // 转正申请-汇总

    Route::post('apply/index', 'ApplyMainController@index')->name('apply-index'); // 申请汇总
    Route::post('apply/list', 'ApplyMainController@list')->name('approval-index'); // 我的申请汇总
    Route::post('apply/verify', 'ApplyMainController@verify')->name('approval-audit-index'); // 我的审核汇总
    Route::post('apply/show', 'ApplyMainController@show')->name('approval-index-detail'); // 申请汇总-查看详情
    Route::post('apply_attendance/store', 'ApplyAttendanceController@store')->name('approval-attendance'); // 出勤申请
    Route::post('apply_recruit/store', 'ApplyRecruitController@store')->name('approval-recruit'); // 招聘申请
    Route::post('apply_recruit/show', 'ApplyRecruitController@show')->name('member-recruit-collect-detail'); // 招聘申请-详情
    Route::post('apply_recruit/index', 'ApplyRecruitController@index')->name('member-recruit-collect'); // 招聘申请-汇总
    Route::post('apply_recruit/list', 'ApplyRecruitController@list')->name('member-recruit-index'); // 招聘管理-正在招聘
    Route::post('apply_recruit/update', 'ApplyRecruitController@update')->name('member-recruit-index-status'); // 招聘管理-正在招聘-开始招聘、完成、终止
    Route::post('apply_recruit/detail', 'ApplyRecruitController@detail')->name('member-recruit-index-detail'); // 招聘管理-正在招聘-查看详情
    Route::post('apply_recruit/statistics', 'ApplyRecruitController@statistics')->name('member-recruit-collect-analysis'); // 招聘管理-招聘统计
    Route::post('apply_leave/store', 'ApplyLeaveController@store')->name('approval-leave'); // 离职申请
    Route::post('apply_leave/index', 'ApplyLeaveController@index')->name('member-leave-index'); // 离职申请汇总
    Route::post('apply_leave/show', 'ApplyLeaveController@show')->name('member-leave-index-show'); // 离职申请详情
    Route::post('apply_training/store', 'ApplyTrainingController@store')->name('approval-training'); // 培训申请
    Route::post('apply_training/show', 'ApplyTrainingController@show')->name('apply_training-show'); // 培训申请-详情
    Route::post('apply_purchase/store', 'ApplyPurchaseController@store')->name('approval-purchase'); // 采购申请
    Route::post('apply_purchase/show', 'ApplyPurchaseController@show')->name('material-purchase-index-detail'); // 采购申请-详情
    Route::post('apply_purchase/index', 'ApplyPurchaseController@index')->name('material-purchase-index'); // 采购申请汇总
    Route::post('apply_purchase/mylist', 'ApplyPurchaseController@mylist')->name('apply_purchase-mylist'); //我的 采购申请汇总
    Route::post('apply_purchase/put', 'ApplyPurchaseController@put')->name('material-purchase-storage'); //资产入库
    Route::post('apply_access/store', 'ApplyAccessController@store')->name('approval-receive'); //物品领用申请
    Route::post('apply_access/index', 'ApplyAccessController@index')->name('material-approval-index'); //物品领用申请-汇总
    Route::post('apply_access/show', 'ApplyAccessController@show')->name('material-approval-index-detail'); //物品领用申请-查看详情
    Route::post('apply_accesbasissettings/mylist', 'ApplyAccessController@mylist')->name('apply_access-mylist'); //物品领用申请-我的申请

    //报备申请
    Route::post('apply_report/store', 'ApplyReportController@store')->name('attendance-index-report');//添加
    Route::post('apply_attendance/collect', 'ApplyAttendanceController@collect')->name('attendance-approval-index');//申请汇总（出勤、报备）

    //培训项目管理
    Route::post('training/index', 'TrainingProjectController@index')->name('training-setting-index'); // 培训项目列表
    Route::post('training/store', 'TrainingProjectController@store')->name('training-setting-index-add'); // 培训项目添加
    Route::post('training/update', 'TrainingProjectController@update')->name('training-setting-index-edit'); // 培训项目修改
    Route::post('training/delete', 'TrainingProjectController@delete')->name('training-setting-index-destroy'); // 培训项目删除

    //培训管理主体
    Route::post('apply_training/index', 'ApplyTrainingController@index')->name('apply_training-index'); //
    Route::post('apply_training/mylist', 'ApplyTrainingController@myList')->name('training-list-index'); // 我参加的培训
    Route::post('apply_training/mshow', 'ApplyTrainingController@myListShow')->name('training-list-index-detail'); // 我参加的培训-查看/评分
    Route::post('apply_training/mapply', 'ApplyTrainingController@myApplyList')->name('training-list-arrange'); // 我安排的培训-列表
    Route::post('apply_training/arrange', 'ApplyTrainingController@arrange')->name('training-details-index'); // 入职安排
    Route::post('apply_training/total', 'ApplyTrainingController@trainingTotal')->name('training-details-collect'); // 培训汇总

    //物资管理
    Route::post('goods_cate/store', 'GoodsCategoryController@store')->name('material-setting-index-add'); // 添加资产类型
    Route::post('goods_cate/index', 'GoodsCategoryController@index')->name('material-setting-index'); // 资产类型-列表
    Route::post('goods_cate/delete', 'GoodsCategoryController@delete')->name('material-setting-index-destroy'); // 资产类型-删除
    Route::post('goods_cate/category', 'GoodsCategoryController@category')->name('material-setting-index-category'); // 大类管理
    Route::post('goods/consumable', 'GoodsController@consumable')->name('material-stock-index'); // 公司库存-消耗品库存
    Route::post('goods/fixed', 'GoodsController@fixed')->name('material-stock-fixed'); // 公司库存-固定资产库存
    Route::post('apply_purchase/store', 'ApplyPurchaseController@store')->name('material-stock-fixed-add'); // 固定资产库存-补库存
    Route::post('goods/edit', 'GoodsController@edit')->name('material-stock-index-edit'); // 消耗品库存修改
    Route::post('goods_use/index', 'GoodsUseRecordController@index')->name('material-distribution-index'); // 固定资产分配
    Route::post('goods_use/mylist', 'GoodsUseRecordController@mylist')->name('material-list-index'); // 我的物资
    Route::post('achievement/store', 'AchievementTemplatesController@store')->name('achievement-templ-index-add'); // 创建绩效模板
    Route::post('achievement/delete', 'AchievementTemplatesController@delete')->name('achievement-templ-index-destroy'); // 删除绩效模板
    Route::post('achievement/update', 'AchievementTemplatesController@update')->name('achievement-templ-index-update'); // 编辑绩效模板
    Route::post('achievement/copy', 'AchievementTemplatesController@copy')->name('achievement-templ-index-copy'); // 复制绩效模板
    Route::post('achievement/assign', 'AchievementTemplatesController@assign')->name('achievement-templ-index-device'); // 分派
    Route::post('achievement/revoke', 'AchievementTemplatesController@revoke')->name('achievement-templ-index-revoke'); // 分派撤销
    Route::post('achievement/index', 'AchievementTemplatesController@index')->name('achievement-templ-index'); // 模板列表
    Route::post('achievement_user/index', 'AchievementAssignUserController@index')->name('achievement-list-index'); // 我的绩效列表
    Route::post('achievement_user/score', 'AchievementAssignUserController@score')->name('achievement-list-score'); // 绩效评分
    Route::post('achievement_user/verify', 'AchievementAssignUserController@verify')->name('achievement-list-audit'); // 绩效审核
    Route::post('achievement_user/list', 'AchievementAssignUserController@list')->name('achievement-list-summary'); // 绩效汇总
    Route::post('achievement_user/statistics', 'AchievementAssignUserController@statistics')->name('achievement-list-summary-analysis'); // 绩效统计

    //EDM
    Route::post('business_order/index', 'BusinessOrderController@index')->name('bill-index'); // 商务单列表
    Route::post('business_order/store', 'BusinessOrderController@store')->name('bill-add'); // 添加商务单
    Route::post('business_order/edit', 'BusinessOrderController@edit')->name('bill-edit'); // 编辑商务单
    Route::post('business_order/delete', 'BusinessOrderController@delete')->name('bill-index-del'); // 删除商务单
    Route::post('business_order/verify', 'BusinessOrderController@verify')->name('bill-examine'); // 审核商务单
    Route::post('business_order/contracts', 'BusinessOrderController@contracts')->name('bill-upload'); // 上传合同商务单
    Route::post('business_order/change', 'BusinessOrderController@change')->name('bill-update'); // 更改商务单
    Route::post('business_order/index_change_export', 'BusinessOrderController@index_change_export')->name('bill-update-export'); // 更改链接-导出链接
    Route::post('business_order/index_change_view', 'BusinessOrderController@index_change_view')->name('bill-update-view'); // 更改链接-查看详情
    Route::post('business_order/index_change_use', 'BusinessOrderController@index_change_use')->name('bill-update-use'); // 更改链接-启用/禁用
    Route::post('business_order/index_change_edit', 'BusinessOrderController@index_change_edit')->name('bill-update-edit'); // 更改链接-编辑
    Route::post('business_order/index_change_opt', 'BusinessOrderController@index_change_opt')->name('bill-update-opt'); // 更改链接-批量操作
    Route::post('business_order/index_change_add', 'BusinessOrderController@index_change_add')->name('bill-update-add'); // 更改链接-添加链接
    Route::post('business_order/create', 'BusinessOrderController@create')->name('project-create'); // 创建项目
    Route::post('business_order/collect', 'BusinessOrderController@collect')->name('bill-summary'); // 商务单汇总
    Route::post('business_customer/store', 'BusinessOrderCustomerController@store')->name('customer-add'); // 添加客户
    Route::post('business_customer/index', 'BusinessOrderCustomerController@index')->name('customer-index'); // 客户列表
    Route::post('business_customer/update', 'BusinessOrderCustomerController@update')->name('customer-edit'); // 客户列表-编辑
    Route::post('business_customer/contracts', 'BusinessOrderCustomerController@contracts')->name('customer-upload'); // 客户列表-上传合同
    Route::post('business_customer/receipt', 'BusinessOrderCustomerController@receipt')->name('customer-invoice'); // 开票公司名列表
    Route::post('business_customer/receipt_store', 'BusinessOrderCustomerController@receipt_store')->name('customer-invoice-store'); // 添加/编辑开票公司
    Route::post('project/index', 'BusinessProjectController@index')->name('project-management-index'); // 项目汇总
    Route::post('project/create', 'BusinessProjectController@create')->name('project-management-add'); // 项目列表-添加项目
    Route::post('project/update', 'BusinessProjectController@update')->name('project-management-update'); // 项目列表-编辑项目
    Route::post('project/operation', 'BusinessProjectController@operation')->name('project-management-operation'); // 项目汇总-运营情况
    Route::post('project/feedback_list', 'BusinessProjectController@feedbackList')->name('project-management-feedback-list'); // 项目汇总-运营情况-反馈列表
    Route::post('project/execute', 'BusinessProjectController@execute')->name('project-management-execute'); // 项目列表-执行详情
    Route::post('project/tpl_data', 'BusinessProjectController@tplData')->name('project-management-tpl-data'); // 项目列表-模板详情
    Route::post('project/log_index', 'BusinessProjectController@logIndex')->name('project-management-log-index'); // 项目列表-操作日志-列表
    Route::post('project/log_edit', 'BusinessProjectController@logEdit')->name('project-management-log-edit'); // 项目列表-操作日志-编辑
    Route::post('project/log_store', 'BusinessProjectController@logStore')->name('project-management-log-store'); // 项目列表-操作日志-新增记录
    Route::post('project/log_delete', 'BusinessProjectController@logDelete')->name('project-management-log-delete'); // 项目列表-操作日志-删除
    Route::post('group/index', 'ProjectGroupController@index')->name('project-delivery-section'); // 组段设置
    Route::post('group/store', 'ProjectGroupController@store')->name('group-add'); // 组段设置-添加
    Route::post('group/update', 'ProjectGroupController@update')->name('group-edit'); // 组段设置-编辑
    Route::post('group/delete', 'ProjectGroupController@delete')->name('group-delete'); // 组段设置-删除
    Route::post('plan/index', 'ProjectPlanController@index')->name('project-delivery-setting'); // 日投递计划设置
    Route::post('plan/move', 'ProjectPlanController@move')->name('project-delivery-move'); // 日投递计划设置-移动
    Route::post('plan/update', 'ProjectPlanController@update')->name('project-delivery-update'); // 日投递计划设置-设置
    Route::post('plan/suspend', 'ProjectPlanController@suspend')->name('project-delivery-suspend'); // 日投递计划设置-暂停
    Route::post('plan/batch', 'ProjectPlanController@batch')->name('project-delivery-batch'); // 日投递计划设置-批量操作
    Route::post('plan/list', 'ProjectPlanController@list')->name('project-delivery-index'); // 我的日投递计划
    Route::post('plan/summary', 'ProjectPlanController@summary')->name('project-delivery-summary'); // 日投递计划汇总
    Route::post('plan/export', 'ProjectPlanController@export')->name('project-delivery-export'); // 我的日投递计划
    Route::post('feedback/index', 'ProjectFeedbackController@index')->name('project-feedback-summary'); // 数据反馈汇总
    Route::post('feedback/export', 'ProjectFeedbackController@export')->name('project-feedback-export'); // 数据反馈汇总-导出
    Route::post('feedback/list', 'ProjectFeedbackController@list')->name('project-feedback-index'); // 我的项目数据反馈
    Route::post('feedback/update', 'ProjectFeedbackController@update')->name('project-feedback-update'); // 我的项目数据反馈-更新值
    Route::post('feedback/export_mine', 'ProjectFeedbackController@export_mine')->name('project-feedback-export-mine'); // 我的项目数据反馈-导出
    Route::post('feedback/import', 'ProjectFeedbackController@import')->name('project-feedback-import'); // 我的项目数据反馈-导入
    Route::post('links/index', 'BusinessOrderLinkController@index')->name('project-link-index'); // 链接汇总
    Route::post('links/index_view', 'BusinessOrderLinkController@index_view')->name('project-link-index-view'); // 链接汇总-查看链接
    Route::post('links/index_view_view', 'BusinessOrderLinkController@index_view_view')->name('project-link-index-view-view'); // 链接汇总-查看链接-查看详情
    Route::post('links/index_view_export', 'BusinessOrderLinkController@index_view_export')->name('project-link-index-view-export'); // 链接汇总-查看链接-导出链接
    Route::post('links/index_view_opt', 'BusinessOrderController@index_change_opt')->name('project-link-index-view-opt'); // 链接汇总-查看链接-批量操作
    Route::post('links/apply', 'BusinessOrderLinkController@apply')->name('project-link-index-view-apply'); // 链接汇总-查看链接-申请链接
    Route::post('links/index_view_use', 'BusinessOrderLinkController@index_view_use')->name('project-link-index-view-use'); // 链接汇总-查看链接-启用禁用
    Route::post('links/index_view_edit', 'BusinessOrderLinkController@index_view_edit')->name('project-link-index-view-edit'); // 链接汇总-查看链接-编辑
    Route::post('links/index_view_assign', 'BusinessOrderLinkController@index_view_assign')->name('project-link-index-view-assign'); // 链接汇总-查看链接-分配项目
    Route::post('links/index', 'BusinessOrderLinkController@index')->name('project-link-index'); // 链接汇总
    Route::post('links/apply', 'BusinessOrderLinkController@apply')->name('project-link-index-apply'); // 链接汇总-申请链接
    Route::post('links/list', 'BusinessOrderLinkController@list')->name('project-link-approval'); // 我的链接申请
    Route::post('links/verify', 'BusinessOrderLinkController@verify')->name('project-link-audit'); // 我处理的链接申请
    Route::post('links/summary', 'BusinessOrderLinkController@summary')->name('project-link-approvals'); // 链接申请汇总
    Route::post('links/deal', 'BusinessOrderLinkController@deal')->name('project-link-audit-deal'); // 链接申请汇总-处理
    Route::post('links/import', 'BusinessOrderLinkController@import')->name('project-link-audit-import'); // 链接汇总-导入链接
    Route::post('income/index', 'ProjectIncomeController@index')->name('invoice-table-summary'); // 收入到账表汇总-列表
    Route::post('income/add', 'ProjectIncomeController@add')->name('invoice-table-summary-add'); // 新增记录
    Route::post('income/create', 'ProjectIncomeController@create')->name('invoice-table-summary-create'); // 生成收入表
    Route::post('income/mine', 'ProjectIncomeController@mine')->name('invoice-table-index'); // 我的收入到账表
    Route::post('income/apply', 'ProjectIncomeController@apply')->name('invoice-table-index-apply'); // 申请开票
    Route::post('income/delete', 'ProjectIncomeController@delete')->name('invoice-table-summary-delete'); // 删除记录
    Route::post('invoice/index', 'ProjectInvoiceController@index')->name('invoice-log-index'); // 我的申请开票
    Route::post('invoice/summary', 'ProjectInvoiceController@summary')->name('invoice-log-summary'); // 申请开票汇总
    Route::post('invoice/show', 'ProjectInvoiceController@show')->name('invoice-log-summary-show'); // 申请开票汇总-查看详情
    Route::post('invoice/export', 'ProjectInvoiceController@export')->name('invoice-log-summary-export'); // 申请开票汇总-导出
    Route::post('invoice/cancel', 'ProjectInvoiceController@cancel')->name('invoice-log-summary-cancel'); // 申请开票汇总-作废
    Route::post('invoice/open', 'ProjectInvoiceController@open')->name('invoice-log-summary-open'); // 申请开票汇总-开票
    Route::post('invoice/option', 'ProjectInvoiceController@option')->name('invoice-log-summary-option'); // 申请开票汇总-批量开票
    Route::post('invoice/arrival', 'ProjectInvoiceController@arrival')->name('invoice-log-summary-arrival'); // 申请开票汇总-到帐
    Route::post('invoice/delete', 'ProjectInvoiceController@delete')->name('invoice-log-summary-delete'); // 申请开票汇总-删除
    Route::post('project/monitor', 'BusinessProjectController@monitor')->name('project-statistics-monitor'); // 项目监控
    Route::post('project/project_list', 'BusinessProjectController@ProjectList')->name('project-statistics-index'); // 数据统计-项目数据列表

    //通知公告
    Route::post('notice/store', 'NoticeController@store')->name('notice-list-index'); // 发布通知公告
    Route::post('notice/about_my_list', 'NoticeController@aboutMyList')->name('notice-list-my-notice'); // 关于我的公告
    Route::post('notice/about_my_detail', 'NoticeController@aboutMyDetail')->name('notice-list-my-notice-detail'); // 关于我的公告-详情
    Route::post('notice/mylist', 'NoticeController@myNoticeList')->name('notice-list-my-release'); // 我发布的公告
    Route::post('notice/mydetail', 'NoticeController@myNoticeDetail')->name('notice-list-my-release-detail'); // 我发布的公告-详情
    Route::post('notice/recall', 'NoticeController@recall')->name('notice-list-my-release-retract'); // 我发布的公告-撤回
    Route::post('notice_type/edit', 'NoticeTypeController@edit')->name('notice-setting-setting'); // 类型设置
    Route::post('notice_type/store', 'NoticeTypeController@store')->name('notice-setting-setting-add'); // 类型设置-添加公告类型

    //项目协作
    Route::post('team_project/index', 'TeamProjectController@index')->name('team-project-index');//项目汇总列表
    Route::post('team_project/detail', 'TeamProjectController@detail')->name('team-project-detail');//项目查看详情
    Route::post('team_project/store', 'TeamProjectController@store')->name('team-project-store');//新增项目
    Route::post('team_project/edit', 'TeamProjectController@edit')->name('team-project-edit');//编辑
    Route::post('team_project_task/gantt_index', 'TeamProjectTaskController@ganttIndex')->name('team-project-task-gantt-index');//甘特图
    Route::post('team_project_task/gantt_store', 'TeamProjectTaskController@ganttStore')->name('team-project-task-gantt-store');//新增里程碑
    Route::post('team_project_task/gantt_detail', 'TeamProjectTaskController@ganttDetail')->name('team-project-task-gantt-detail');//里程碑详情
    Route::post('team_project_task/index', 'TeamProjectTaskController@index')->name('team-project-task-index');//任务列表
    Route::post('team_project_task/detail', 'TeamProjectTaskController@detail')->name('team-project-task-detail');//任务查看详情
    Route::post('team_project_task/store', 'TeamProjectTaskController@store')->name('team-project-task-store');//新增任务
    Route::post('team_project_task/edit', 'TeamProjectTaskController@edit')->name('team-project-task-edit');//编辑任务
    Route::post('team_project_task/finish', 'TeamProjectTaskController@finish')->name('team-project-task-finish');//任务完成或终止
    Route::post('team_project_task_child/index', 'TeamProjectTaskChildController@index')->name('team-project-task-child-index');//子任务列表
    Route::post('team_project_task_child/project_index', 'TeamProjectTaskChildController@projectIndex')->name('team-project-task-child-project-index');//项目子任务列表
    Route::post('team_project_task_child/detail', 'TeamProjectTaskChildController@detail')->name('team-project-task-child-detail');//子任务查看详情
    Route::post('team_project_task_child/store', 'TeamProjectTaskChildController@store')->name('team-project-task-child-store');//新增子任务
    Route::post('team_project_task_child/edit', 'TeamProjectTaskChildController@edit')->name('team-project-task-child-edit');//编辑子任务任务
    Route::post('team_project_task_child/my_index', 'TeamProjectTaskChildController@myIndex')->name('team-project-task-child-my-index');//我的子任务列表
    Route::post('team_project_task_child/my_detail', 'TeamProjectTaskChildController@myDetail')->name('team-project-task-child-my-detail');//我的子任务详情
    Route::post('team_project_task_child/my_finish', 'TeamProjectTaskChildController@myFinish')->name('team-project-task-child-my-finish');//完成或终止我的子任务
    Route::post('team_project_task_document/index', 'TeamProjectTaskDocumentController@index')->name('team-project-task-document-index');//文档管理列表
    Route::post('team_project_task_document/delete', 'TeamProjectTaskDocumentController@delete')->name('team-project-task-document-delete');//文档管理删除文档
    Route::post('team_project/project_summary', 'TeamProjectController@projectSummary')->name('team-project-project_summary');//项目汇总列表

    //共享知识库
    Route::post('shared_knowledge/index', 'SharedKnowledgeController@index')->name('shared-knowledge-index');//共享知识库列表
    Route::post('shared_knowledge/store', 'SharedKnowledgeController@store')->name('shared-knowledge-store');//共享知识库新建
    Route::post('shared_knowledge/update', 'SharedKnowledgeController@update')->name('shared-knowledge-update');//共享知识库更新
    Route::post('shared_knowledge/delete', 'SharedKnowledgeController@delete')->name('shared-knowledge-delete');//共享知识库删除
    Route::post('shared_knowledge/detail', 'SharedKnowledgeController@detail')->name('shared-knowledge-detail');//共享知识库查看详情

    // 图库-模板库
    Route::post('template/index', 'TemplateController@index')->name('template-index'); // 模板库列表
    Route::post('template/rename', 'TemplateController@rename')->name('template-rename'); // 重命名
    Route::post('template/delete', 'TemplateController@delete')->name('template-delete'); // 删除
    Route::post('template/update', 'TemplateController@update')->name('template-update'); // 编辑
    Route::post('template/show', 'TemplateController@show')->name('template-show'); // 查看
    Route::post('template/folder', 'TemplateController@createFolder')->name('template-folder'); // 新建文件夹
    Route::post('template/upload', 'TemplateController@upload')->name('template-upload'); // 上传文件

    // 图库-UI库
    Route::post('ui/index', 'UiController@index')->name('ui-index'); // UI库列表
    Route::post('ui/folder', 'UiController@createFolder')->name('ui-folder'); // 新建文件夹
    Route::post('ui/rename', 'UiController@rename')->name('ui-rename'); // 重命名
    Route::post('ui/delete', 'UiController@delete')->name('ui-delete'); // 删除
    Route::post('ui/show', 'UiController@show')->name('ui-show'); // 查看
    Route::post('ui/upload', 'UiController@upload')->name('ui-upload'); // 上传
    Route::post('ui/update', 'UiController@update')->name('ui-update'); // 编辑


    //部门文件管理
    Route::post('file/index', 'GroupFileController@index')->name('file-index');//列表
    Route::post('file/store', 'GroupFileController@store')->name('file-index-store');//创建/编辑文件夹名称
    Route::post('file/upload', 'GroupFileController@upload')->name('file-index-upload');//上传文件
    Route::post('file/permission', 'GroupFileController@permission')->name('file-index-permission');//设置权限
    Route::post('file/encry', 'GroupFileController@encry')->name('file-index-encry');//加密
    Route::post('file/download', 'GroupFileController@download')->name('file-index-download');//下载
    Route::post('file/delete', 'GroupFileController@delete')->name('file-index-delete');//删除
    Route::post('file/move', 'GroupFileController@move')->name('file-index-move');//移动

});

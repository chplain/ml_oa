<?php

/*
|--------------------------------------------------------------------------
| API Routes 登录后即可访问接口
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['auth:api'], 'namespace' => 'Api'], function() {
    Route::post('logout', 'PublicController@logout'); // 退出登录
    Route::post('training/upload', 'TrainingProjectController@upload'); // 培训项目上传
    Route::post('daily/store', 'DailyController@store'); // 首页写日报
    Route::post('daily/copy', 'DailyController@copy'); // 复制日报
    Route::post('attendance/daka', 'AttendanceRecordController@daka'); // 首页打卡记录
    Route::post('apply/mine', 'ApplyMainController@mine'); // 首页我的申请
    Route::post('sys/permission', 'PermissionController@sysPermissions'); // 系统权限
    Route::post('notice/index', 'NoticeController@aboutMyList'); // 首页通知公告
    Route::post('change/password', 'UserController@changePassword'); // 首页修改密码
    Route::post('home/badge', 'HomeController@badge'); // 待处理事项统计
    Route::post('home/unreadmsg', 'HomeController@unreadNotified'); // 未读的信息
    Route::post('home/readmsg', 'HomeController@readNotified'); // 阅读消息
    Route::post('home/qrcode', 'HomeController@qrcode'); // 生成二维码
    Route::post('schedule/index', 'ScheduleController@index');// 日程列表
    Route::post('schedule/store', 'ScheduleController@store');// 添加日程
});

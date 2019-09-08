<?php

/*
|--------------------------------------------------------------------------
| Public API Routes 公开接口，没有限制
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::group(['namespace' => 'Api'], function() {
    Route::any('test', 'PublicController@test'); // 调试专用接口
    Route::post('login', 'PublicController@login'); // 登录
    Route::post('push', 'PublicController@push'); // 获取考勤机记录
    Route::get('download', 'PublicController@download'); // 下载文件公共方法
    Route::get('sendEmail', 'PublicController@sendEmail'); // 发送邮件
});

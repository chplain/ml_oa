<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// 微信公众号
Route::group(['middleware' => ['web'], 'namespace' => 'Web'], function () {
    Route::any('/wechat', 'WeChatController@serve'); // 微信回调地址
});

<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//api 登陆接口
Route::middleware('api')->post('/user/login', 'Api\LoginController@login');

Route::group([
    'prefix' => '/v1',
    'middleware' => ['auth:api']
], function () {
    //测试接口
    Route::get('/test', 'Api\TestController@test');


});

<?php

/**
 * 移动端路由表
 */

Route::group(['prefix' => '/mobile-terminal/rest/v1',
    'namespace' => 'MobileTerminal\Rest\V1',   'middleware' => ['api']
], function () {

    Route::get('/', ['as' => 'MobileTerminal', function () {
        return "行行通app接口";
    }]);

    //注册
    Route::post('/register', 'RegisterController@register');
    //登录
    Route::post('/login', 'LoginController@login');


    //委托接口
    Route::group(['prefix' => 'assignments' , 'middleware' => 'auth:api'], function() {
        //获取所有委托的类目
        Route::get('/classifications', ['as' => 'Categories', 'uses' => 'AssignmentController@classifications']);
        //获取委托列表
        Route::get('/index', ['as' => 'Index', 'uses' => 'AssignmentController@index']);
        //获取单个委托详情
        Route::get('/{id}/detail', ['as' => 'Detail', 'uses' => 'AssignmentController@detail']);
        //发布委托
        Route::post('/publish', ['as' => 'Create', 'uses' => 'AssignmentController@publish']);
        //接受委托
        Route::post('/accept/{id}', ['as' => '', 'uses' => 'AssignmentController@accept']);
    });



});
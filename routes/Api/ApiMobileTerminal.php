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
        Route::post('/publish', ['as' => 'Create', 'uses' => 'AssignmentController@publishAssignment']);
        //接受委托
        Route::post('/accept/{id}', ['as' => '', 'uses' => 'AssignmentController@acceptAssignment']);
        //采纳 接受的委托
        Route::post('/adapt/{id}', ['as' => '', 'uses' => 'AssignmentController@adaptAcceptedAssignment']);
        //告知完成  被采纳的 接收的委托
        Route::post('/deal/{id}', ['as' => '', 'uses' => 'AssignmentController@dealAcceptedAssignment']);
        //确认完成  委托
        Route::post('/finish/{id}', ['as' => '', 'uses' => 'AssignmentController@finishAcceptedAssignment']);
    });

    //服务接口


    //用户接口
    Route::group(['prefix' => 'user', 'middleware' => 'auth:api'], function () {
        //绑定clientId 和 userId
        Route::get('/bind/{client_id}', ['as' => 'Bind', 'uses' => 'UserController@bindUserIdAndClientId']);
        //对用户发送消息
        Route::post('/send/{user_id}', ['as' => 'Send', 'uses' => 'UserController@sendMessage']);
        //

    });

    //支付接口
    Route::group(['prefix' => 'pay'], function () {
        //绑定clientId 和 userId
        Route::get('/pay', ['as' => 'Bind', 'uses' => 'PayController@pay']);
    });





});
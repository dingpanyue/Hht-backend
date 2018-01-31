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
    //检测电话号码有没有注册过
    Route::post('/check-mobile','RegisterController@checkMobile');
    //发送验证码
    Route::post('/send-sms', 'RegisterController@sendSmsCode');
    //登录
    Route::post('/login', 'LoginController@login');
    //Pingpp回调地址
    Route::post('/notify', 'PayController@notify');
    //获取所有仲裁中的服务实例列表
    Route::get('/arbitrated-services', ['as' => '', 'uses' => 'ServiceController@ArbitratedServices']);
    //获取所有种菜中的委托列表
    Route::get('/arbitrated-assignments', ['as' => '', 'uses' => 'AssignmentController@ArbitratedAssignments']);

    Route::group(['prefix' => 'regions'], function (){
        //省份
        Route::get('/provinces', ['as' => 'Provinces', 'uses' => 'RegionController@provinces']);
        //城市
        Route::get('/{province_id}/cities', ['as' => 'Cities', 'uses' => 'RegionController@cities']);
        //地区
        Route::get('/{city_id}/areas', ['as' => 'Areas', 'uses' => 'RegionController@areas']);
    });

    Route::get('/test_notify', 'UserController@systemMessage');

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
        //取消委托
        Route::post('/cancel/{id}', ['as' => 'Cancel', 'uses' => 'AssignmentController@cancelAssignment']);
        //上传委托图片
        Route::post('/upload/{id}', ['as' => 'Upload', 'uses' => 'AssignmentController@upload']);
        //接受委托
        Route::post('/accept/{id}', ['as' => '', 'uses' => 'AssignmentController@acceptAssignment']);
        //取消接受 但未被采纳的委托
        Route::post('/cancel-accepted/{id}', ['as' => '', 'uses' => 'AssignmentController@cancelAcceptedAssignment']);
        //采纳 接受的委托
        Route::post('/adapt/{id}', ['as' => '', 'uses' => 'AssignmentController@adaptAcceptedAssignment']);
        //告知完成  被采纳的 接收的委托
        Route::post('/deal/{id}', ['as' => '', 'uses' => 'AssignmentController@dealAcceptedAssignment']);
        //确认完成  委托
        Route::post('/finish/{id}', ['as' => '', 'uses' => 'AssignmentController@finishAcceptedAssignment']);
        //拒绝完成  委托
        Route::post('/refuse-finish/{id}', ['as' => '', 'uses' => 'AssignmentController@refuseFinishingAcceptedAssignment']);
    });

    //服务接口
    Route::group(['prefix' => 'services', 'middleware' => 'auth:api'], function () {
        //获取委托列表
        Route::get('/index', ['as' => 'Index', 'uses' => 'ServiceController@index']);
        //获取所有服务的类目
        Route::get('/classifications', ['as' => 'Categories', 'uses' => 'AssignmentController@classifications']);
        //获取单个服务详情
        Route::get('/{id}/detail', ['as' => '', 'uses' => 'ServiceController@detail']);
        //获取单个服务实例详情
        Route::get('/accepted_services/{id}/detail', ['as' => '', 'uses' => 'ServiceController@acceptedServiceDetail']);
        //发布服务
        Route::post('/publish', ['as' => '', 'uses' => 'ServiceController@publishService']);
        //取消服务(service)
        Route::post('/cancel/{id}', ['as' => '', 'uses' => 'ServiceController@cancelService']);
        //上传服务图片
        Route::post('/upload/{id}', ['as' => 'Upload', 'uses' => 'ServiceController@upload']);
        //购买服务 post参数为reward 和 deadline
        Route::post('/buy/{id}', ['as' => '', 'uses' => 'ServiceController@buyService']);
        //取消购买服务的申请
        Route::post('/cancel-accepted/{id}', ['as' => '', 'uses' => 'ServiceController@cancelAcceptedService']);
        //同意 购买者  购买服务
        Route::post('/accept/{id}', ['as' => '', 'uses' => 'ServiceController@acceptBoughtService']);
        //拒绝 购买者  购买服务
        Route::post('/refuse/{id}', ['as' => '', 'uses' => 'ServiceController@refuseBoughtService']);
        //告知完成被接收的委托
        Route::post('/deal/{id}', ['as' => '', 'uses' => 'ServiceController@dealAcceptedService']);
        //确认完成被接受的委托
        Route::post('/finish/{id}', ['as' => '', 'uses' => 'ServiceController@finishAcceptedService']);
        //拒绝完成  被购买的服务
        Route::post('/refuse-finish/{id}', ['as' => '', 'uses' => 'ServiceController@refuseToFinishAcceptedService']);
    });

    //用户接口
    Route::group(['prefix' => 'user', 'middleware' => 'auth:api'], function () {
        //绑定clientId 和 userId
        Route::get('/bind/{client_id}', ['as' => 'Bind', 'uses' => 'UserController@bindUserIdAndClientId']);
        //对用户发送消息
        Route::post('/send/{user_id}', ['as' => 'Send', 'uses' => 'UserController@sendMessage']);
        //提交认证信息
        Route::post('/authentication', ['as' => '', 'uses' => 'UserController@authentication']);
        //添加地址
        Route::post('/address', ['as' => '', 'uses' => 'UserController@addAddress']);
        //设置地址为默认地址
        Route::post('/{id}/set-default', ['as' => '', 'uses' => 'UserController@setDefaultAddress']);
        //获取用户地址列表
        Route::get('/addresses', ['as' => '', 'uses' => 'UserController@getUserAddresses']);
        //上传头像
        Route::post('/upload', ['as' => '', 'uses' => 'UserController@upload']);
        //获取用户信息
        Route::get('{id}/info', ['as' => '', 'uses' => 'UserController@info']);
        //获取与某用户的所有聊天记录
        Route::get('/{user_id}/messages', ['as' => '', 'uses' => 'UserController@getMessages']);
        //用户的发布所有委托
        Route::get('/assignments', ['as' => '', 'uses' => 'AssignmentController@myAssignments']);
        //用户作为服务者接受的所有委托
        Route::get('/accepted_assignments', ['as' => '', 'uses' => 'AssignmentController@myAcceptedAssignments']);
        //获取我发布的服务
        Route::get('/services', ['as' => '', 'uses' => 'ServiceController@myServices']);
        //获取作为委托人购买的所有服务
        Route::get('/accepted_services', ['as' => '', 'uses' => 'ServiceController@myAcceptedServices']);
        //获取我所有被申请购买之后的服务
        Route::get('/bought_services', ['as' => '', 'uses' => 'ServiceController@myBoughtServices']);
        //修改/设置用户支付宝账户
        Route::post('/alipay_account' , ['as' => '', 'uses' => 'UserController@setUserAlipayAccount']);
        //设置用户支付密码
        Route::post('/payment_password', ['as' => '', 'uses' => 'UserController@setUserPaymentPassword']);
        //获取微信授权页面
        Route::post('/wechat_auth', ['as' => '', 'uses' => 'UserController@getUserWechatAuthUrl']);
        //微信回调关联用户
        Route::post('/user/{id}/wechat_auth', ['as' => '', 'uses' => 'UserController@verify']);
        //用户上传 个人中心展示的照片
        Route::post('/user-center/images', ['as' => '', 'uses' => 'UserController@uploadUserCenterImages']);
        //用户设置 擅长的classification
        Route::post('/user-center/talents', ['as' => '', 'uses' => 'UserController@setUserTalents']);
        //填写用户个人说明
        Route::post('/user-center/description', ['as' => '', 'uses' => 'UserController@setUserDescription']);
        //进入个人页面
        Route::get('/{user_id}/user-center', ['as' => '', 'uses' => 'UserController@userCenter']);
        //获取所有离线消息
        Route::get('/offline-messages', ['as' => '', 'uses' => 'UserController@offlineMessages']);
        //处理完离线消息的回调
        Route::post('/offline-messages-dealt', ['as' => '', 'uses' => 'UserController@offlineMessagesDealt']);
    });

    //支付接口
    Route::group(['prefix' => 'pay', 'middleware' => 'auth:api'], function () {
        //支付接口
        Route::get('/pay', ['as' => 'Pay', 'uses' => 'PayController@pay']);
        //退款接口
        Route::get('/refund/{type}/{pk}', ['as' => '', 'uses' => 'PayController@refund']);
        //提现接口
        Route::post('/withdrawal', ['as' => 'Withdrawals', 'uses' => 'PayController@withdrawals']);
    });






});

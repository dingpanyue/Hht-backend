<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;

use App\Http\Controllers\ApiController;
use App\Models\UserInfo;
use App\Services\SmsCodeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;


class RegisterController extends BaseController
{
    protected $smsCodeService;

    protected $userEloquent;

    public function __construct(SmsCodeService $smsCodeService, \App\Models\User $user)
    {
        $this->smsCodeService = $smsCodeService;

        $this->userEloquent = $user;

        parent::__construct();
    }

    public function register(Request $request)
    {
        $validator =  $validator = app('validator')->make($request->all(), [
            'mobile'    => 'required|unique:users,mobile|regex:/^1[34578][0-9]{9}$/',
            'sms_code'  =>  'required',
            'password' => 'required|between:6,32',
        ],[
            "mobile.require" => '必须提供电话号码',
            "mobile.regex" => '无效的电话号码',
            "mobile.unique" => '该手机号码已被注册过',
            'sms_code.required' => '验证码必须填写',
            'password.required' => '密码必须填写',
            'password.between' => '密码格式不正确'
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $inputs = $request->all();
        $mobile = $inputs['mobile'];
        $smsCode = $inputs['sms_code'];


        if ($smsCode != cache('sms'.$mobile)) {
            return self::parametersIllegal('您输入的验证码不正确');
        }

        event(new Registered($user = $this->create($request->all())));

        if ($user) {
            $userInfo = new UserInfo();
            $userInfo->user_id = $user->id;
            $userInfo->save();
        }

        return json_encode($user);
    }

    //发送短信验证码
    public function sendSmsCode(Request $request)
    {
        $validator =  $validator = app('validator')->make($request->all(), [
            "mobile"    => 'required|regex:/^1[34578][0-9]{9}$/',
        ], [
            "mobile.require" => '必须提供电话号码',
            "mobile.regex" => '无效的电话号码'
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $inputs = $request->all();
        $mobile = $inputs['mobile'];

        //发送验证码
        if ($this->smsCodeService->send($mobile)) {
            return self::success();
        } else {
            return self::error(self::CODE_SMS_SERVICE_ABNORMAL , '短信服务异常');
        }
    }

    //检测电话号码有没有注册过
    public function checkMobile(Request $request)
    {
        $validator =  $validator = app('validator')->make($request->all(), [
            "mobile"    => 'required|unique:users,mobile|regex:/^1[34578][0-9]{9}$/',
        ], [
            "mobile.require" => '必须提供电话号码',
            "mobile.unique" => '该电话号码已被注册过',
            "mobile.regex" => '无效的电话号码'
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        return self::success();
    }

    public function resetPassword(Request $request)
    {
        $validator =  $validator = app('validator')->make($request->all(), [
            "mobile"    => 'required|exists:users,mobile|regex:/^1[34578][0-9]{9}$/',
            'sms_code'  =>  'required'
        ], [
            "mobile.require" => '必须提供电话号码',
            "mobile.regex" => '无效的电话号码',
            "mobile.exists" => '该手机号码尚未注册',
            'password.required' => '密码必须填写',
            'password.between' => '密码格式不正确'
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $inputs = $request->all();
        $mobile = $inputs['mobile'];
        $smsCode = $inputs['sms_code'];

        if ($smsCode !== cache('sms'.$mobile)) {
            $sms = cache('sms'.$mobile);
            return self::parametersIllegal("您输入的验证码 $smsCode 不正确,验证码为 $sms");
        }

        $user = $this->userEloquent->where('mobile', $mobile)->first();
        $user->password = bcrypt($inputs['password']);

        if ($user->save()) {
            return self::success();
        } else {
            return self::error(self::CODE_PARAM_ILLEGAL , '修改失败，请联系管理员');
        }
    }



    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => 'user'.time(),
            'password' => bcrypt($data['password']),
            'mobile' => $data['mobile']
        ]);
    }
}
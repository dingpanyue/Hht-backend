<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;


class LoginController extends ApiController
{
    // 登录用户名标示为mobile字段
    public function username()
    {
        return 'mobile';
    }
    //登录接口，调用了ApiController中一些其他函数succeed\failed，上文未提及，用于接口格式化输出
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile'    => 'required|exists:users',
            'password' => 'required|between:6,32',
        ]);

        if ($validator->fails()) {
            $request->request->add([
                'errors' => $validator->errors()->toArray(),
                'code' => 401,
            ]);
            return $this->sendFailedLoginResponse($request);
        }

        $credentials = $this->credentials($request);

        if ($this->guard('api')->attempt($credentials, $request->has('remember'))) {
            return $this->sendLoginResponse($request);
        }

        return json_encode(['message' => 'login failed','code' => 401, 'status' => 'failed']);
    }

}
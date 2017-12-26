<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;

use App\Http\Controllers\ApiController;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;


class RegisterController extends ApiController
{

    public function register(Request $request)
    {
        //todo 验证码
        
        $validator = Validator::make($request->all(), [
            'mobile'    => 'required|unique:users,mobile|regex:/^1[34578][0-9]{9}$/',
            'sms_code'  =>  'required',
            'password' => 'required|between:6,32',
        ]);

        if ($validator->fails()) {
            $request->request->add([
                'errors' => $validator->errors()->toArray(),
                'code' => 401,
            ]);
            return $this->sendFailedLoginResponse($request);
        }

        event(new Registered($user = $this->create($request->all())));

        return json_encode($user);
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
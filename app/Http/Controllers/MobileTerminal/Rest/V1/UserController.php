<?php

namespace App\Http\Controllers\MobileTerminal\Rest\V1;

use App\Models\UserInfo;
use App\Traits\VerifyCardNo;
use GatewayWorker\Lib\Gateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    use VerifyCardNo;

    public function authentication(Request $request)
    {
        $user = $this->user;

        $validator = Validator::make($request->all(), [
            'real_name' => 'required',
            'card_no' => 'required',
            'mobile' => 'required',
        ]);

        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }

        $inputs = $request->only('real_name', 'card_no', 'mobile');

        //验证身份证号码合法性
        if (!$this->validation_filter_id_card($inputs['card_no'])) {
            return self::parametersIllegal('身份证号码不合法');
        }

        $userInfo = $user->userInfo;

        if (!$userInfo) {
            $userInfo = new UserInfo();
        } else {
            if ($userInfo->status == UserInfo::STATUS_AUTHENTICATED) {
                return self::notAllowed('您的身份已经认证过，无需再次认证');
            }
        }

        //未认证用户创建用户信息， 认证过但失败了的用户更新信息
        $userInfo->user_id = $user->id;
        $userInfo->real_name = $inputs['real_name'];
        $userInfo->card_no = $inputs['card_no'];
        $userInfo->status = UserInfo::STATUS_UNAUTHENTICATED;
        $userInfo->save();

        //todo 调用认证接口对用户进行认证 改变状态


        return self::success($user);
    }

    //绑定客户端id 和 用户id
    public function bindUserIdAndClientId($clientId)
    {
        $user = $this->user;

        Gateway::unbindUid($clientId, $user->id);
        Gateway::bindUid($clientId, $user->id);
        return self::success();
    }


    public function sendMessage($toUserId, Request $request)
    {
        $fromUserId = $this->user->id;
        $message = $request->get('message');
        $data = [
            'type' => 'chat',
            'from_user_id' => $fromUserId,
            'from_user_name' => $this->user->name,
            'time' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
        Gateway::sendToUid($toUserId, json_encode($data));
    }

    public function upload(Request $request)
    {
        $all = $request->all();
        $rules = [
            'upFile' => 'required',
        ];
        $messages = [
            'upFile.required' => '请选择要上传的文件'
        ];
        $validator = Validator::make($all, $rules, $messages);
        if ($validator->fails()) {
            return self::parametersIllegal($validator->messages()->first());
        }
        //获取上传文件的大小
        $size = $request->file('upFile')->getSize();
        //这里可根据配置文件的设置，做得更灵活一点
        if ($size > 2 * 1024 * 1024) {
            return self::parametersIllegal('上传文件不能超过2M');
        }
        //文件类型
        $mimeType = $request->file('upFile')->getMimeType();
        //这里根据自己的需求进行修改
        if ($mimeType != 'image/png') {
            return self::parametersIllegal('只能上传png格式的图片');
        }
        //扩展文件名
        $ext = $request->file('upFile')->getClientOriginalExtension();
        //判断文件是否是通过HTTP POST上传的
        $realPath = $request->file('upFile')->getRealPath();

        if (!$realPath) {
            return self::notAllowed('非法操作');
        }

        //创建以当前日期命名的文件夹
        $today = date('Y-m-d');
        //storage_path().'/app/uploads/' 这里根据 /config/filesystems.php 文件里面的配置而定
        //$dir = str_replace('\\','/',storage_path().'/app/uploads/'.$today);
        $dir = storage_path() . '/app/public/images/' . $today;
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        //上传文件
        $filename = uniqid() . '.' . $ext;//新文件名
        if (Storage::disk('public')->put('/images/' . $today . '/' . $filename, file_get_contents($realPath))) {

            $user = $this->user;

            $user->image = "/storage/images/$today/$filename";
            $user->save();

            return URL::asset($user->image);
        } else {
            return self::error(self::CODE_FAIL_TO_SAVE_IMAGE, "图片保存出错");
        }
    }
}
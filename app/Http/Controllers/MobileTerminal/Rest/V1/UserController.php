<?php
namespace App\Http\Controllers\MobileTerminal\Rest\V1;

use GatewayWorker\Lib\Gateway;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    //绑定客户端id 和 用户id
    public function bindUserIdAndClientId($clientId)
    {
        $user = $this->user;
        Gateway::bindUid($clientId, $user->id);
        return self::success();
    }

    public function sendMessage($userId, Request $request)
    {
        $message = $request->get('message');
        Gateway::sendToUid($userId, $message);
    }



}
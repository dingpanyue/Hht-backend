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



}
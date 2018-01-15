<?php
namespace App\Services;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/29
 * Time: 9:01
 */
use App\Models\Message;
use GatewayWorker\Lib\Gateway;

class GatewayWorkerService
{
    /**
     * 发送系统消息
     * @param $message
     * @param $userId
     */
    public static function sendSystemMessage($message, $userId)
    {
        $fromUserId = 0;

        $data = [
            'type' => 'system',
            'message' => $message,
            'from_user_id' => $fromUserId,
            'to_user_name' => $userId,
            'time' => date('Y-m-d H:i:s'),
        ];

        self::send($userId, $data);
    }

    /**
     * 发送用户消息
     * @param $message
     * @param $fromUserId
     * @param $toUserId
     */
    public static function sendMessageFromUser($message, $fromUserId, $toUserId)
    {
        $data = [
            'type' => 'user',
            'message' => $message,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'time' => date('Y-m-d H:i:s')
        ];

        self::send($toUserId, $data);
    }

    protected static function send($userId, $data)
    {
        if(Gateway::isUidOnline($userId)) {
            Gateway::sendToUid($userId, json_encode($data,JSON_UNESCAPED_UNICODE));
            self::save($data, Message::STATUS_SENT);
        } else {
            self::save($data);
        }

        return;
    }

    protected static function save($data, $status = Message::STATUS_UNSENT)
    {
        unset($data['time']);
        $message = new Message();
        $message->create(
            array_merge($data, ['status' => $status])
        );
        return;
    }


}
<?php

namespace App\Services;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2018/1/5
 * Time: 2:13
 */

use Flc\Dysms\Client;
use Flc\Dysms\Request\SendSms;

class SmsCodeService
{
    public function send($recNum)
    {
        $config = [
            'accessKeyId' => (env('SMS_APP_KEY','LTAISXCauKD5SdH4')),
            'accessKeySecret' => (env('SMS_APP_SECRET','r6VPEYqca22beR2EwQcwdX62PAuU6R')),
        ];

        $num = rand(100000, 999999);
        $client  = new Client($config);
        $sendSms = new SendSms;
        $sendSms->setPhoneNumbers($recNum);
        $sendSms->setSignName(env('SMS_FREE_SIGNATURE_NAME','行行通科技'));
        $sendSms->setTemplateCode(env('SMS_TEMPLATE_CODE','SMS_15105357'));
        $sendSms->setTemplateParam(['code' => $num]);
        $sendSms->setOutId('demo');

        $result = $client->execute($sendSms);

        dd($result);
        if ($result->Code != 'OK') {
            return false;
        } else {
            cache(['sms' . $recNum => $num], 15);
            return $num;
        }
    }
}

<?php

namespace App\Transformers;

use App\Models\AcceptedService;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/28
 * Time: 8:44
 */
class AcceptedServiceTransformer
{
    public static $statuses = [
        AcceptedService::STATUS_SUBMITTED => '申请中',
        AcceptedService::STATUS_UNPAID => '待支付',
        AcceptedService::STATUS_ADAPTED => '服务中',
        AcceptedService::STATUS_DEALT => '等待确认完成',
        AcceptedService::STATUS_ARBITRATED => '仲裁中',
        AcceptedService::STATUS_FINISHED => '已完成',
        AcceptedService::STATUS_FAILED => '失败',
    ];

    public static function transform(AcceptedService $acceptedService)
    {
        $statuses = self::$statuses;
        $acceptedService->status = $statuses[$acceptedService->status];
        $acceptedService->service = ServiceTransformer::transform($acceptedService->service, false);

        return $acceptedService;
    }

    public static function transformList($acceptedServices)
    {
        foreach ($acceptedServices as $k =>$acceptedService) {
            $acceptedServices[$k] = self::transform($acceptedService);
        }

        return $acceptedServices;
    }
}
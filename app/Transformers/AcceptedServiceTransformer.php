<?php

namespace App\Transformers;

use App\Models\AcceptedService;
use App\Services\OperationLogService;

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

    public static function transform(AcceptedService $acceptedService, $includeService = true)
    {
        $statuses = self::$statuses;
        $acceptedService->status = $statuses[$acceptedService->status];

        if ($includeService) {
            $acceptedService->service = ServiceTransformer::transform($acceptedService->service, false);
        }

        if ($acceptedService->operations) {
            $acceptedService->operations = OperationLogTransformer::transformList($acceptedService->operations);
        }

        return $acceptedService;
    }

    public static function transformList($acceptedServices, $includeService =  true)
    {
        foreach ($acceptedServices as $k =>$acceptedService) {
            $acceptedServices[$k] = self::transform($acceptedService, $includeService);
        }

        return $acceptedServices;
    }
}
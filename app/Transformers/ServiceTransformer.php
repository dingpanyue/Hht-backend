<?php
namespace App\Transformers;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/28
 * Time: 7:32
 */

use App\Models\Service;
use App\Services\Helper;

class ServiceTransformer
{
    public static $statuses = [
        Service::STATUS_PUBLISHED => '已发布'
    ];

    public static function transform(Service $service, $includeAcceptedServices = true)
    {
        $classifications = Helper::transformToKeyValue(app('assignment_classifications'), 'id', 'name');

        $statuses = self::$statuses;

        $service->classification = $classifications[$service->classification];
        $service->status = $statuses[$service->status];

        if($includeAcceptedServices) {
            if ($service->acceptedServices) {
                $service->accepted_services = AcceptedServiceTransformer::transformList($service->acceptedServices, false);
            }

            if ($service->acceptedServicesCommitted) {
                $service->accepted_services_committed = AcceptedServiceTransformer::transformList($service->acceptedServicesCommitted, false);
            }
        }
        return $service;
    }

    public static function transformList($services)
    {
        foreach ($services as $k =>$service) {
            $services[$k] = self::transform($service);
        }

        return $services;
    }
}
<?php
namespace App\Services;
use App\Models\AcceptedService;
use App\Models\User;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/10
 * Time: 8:13
 */

class AcceptedServiceService
{
    protected $acceptedServiceEloqument;

    protected $operationLogService;

    public function __construct(AcceptedService $acceptedServiceEloqument, OperationLogService $operationLogService)
    {
        $this->acceptedServiceEloqument = $acceptedServiceEloqument;
        $this->operationLogService = $operationLogService;
    }

    public function getAcceptedServiceById($id)
    {
        $acceptedAssignment = $this->acceptedServiceEloqument->find($id);
        return $acceptedAssignment;
    }

    public function getAcceptedServiceDetailById($id)
    {
        $acceptedService = $this->acceptedServiceEloqument->with('service')->with('assignUser')->with('assignUser.userInfo')->with('serveUser')->with('serveUser.userInfo')->find($id);
        $operations = $this->getServiceOperationLog($acceptedService);
        $acceptedService->operations = $operations;
        return $acceptedService;
    }

    //获取我作为购买人， 购买或准备购买的accpetedService
    public function getAcceptedServicesByUser(User $user, $status = 'all')
    {
        $acceptedServices = $this->acceptedServiceEloqument->with('service')->where('assign_user_id', $user->id)->orderBy('status', 'asc');

        if ($status != 'all') {
            $acceptedServices = $acceptedServices->where('status', $status);
        }

        $acceptedServices = $acceptedServices->get();
        return $acceptedServices;
    }

    public function getAcceptedServicesByServeUser(User $user, $status = 'all')
    {
        $acceptedServices = $this->acceptedServiceEloqument->with('service')->where('serve_user_id', $user->id)->orderBy('updated_at', 'desc');

        if ($status != 'all') {
            $acceptedServices = $acceptedServices->where('status', $status);
        }

        $acceptedServices = $acceptedServices->get();
        return $acceptedServices;
    }

    public function getServiceOperationLog(AcceptedService $acceptedService)
    {
        $operations = $this->operationLogService->getServiceOperationLogs($acceptedService);
        return $operations;
    }
}
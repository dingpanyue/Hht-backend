<?php
namespace App\Services;
use App\Models\AcceptedService;

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

    public function getServiceOperationLog(AcceptedService $acceptedService)
    {
        $operations = $this->operationLogService->getServiceOperationLogs($acceptedService);
        return $operations;
    }
}
<?php
namespace App\Services;
use App\Models\AcceptedService;
use App\Models\OperationLog;
use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/10
 * Time: 4:55
 */

class ServiceService
{
    protected $serviceEloqument;

    protected $operationLogService;

    public function __construct(Service $service, OperationLogService $operationLogService)
    {
        $this->serviceEloqument = $service;

        $this->operationLogService = $operationLogService;
    }

    //获取指定服务
    public function getServiceById($serviceId)
    {
        $service = $this->serviceEloqument->with('user')->find($serviceId);
        return $service;
    }

    public function create($userId, $params, $status = Service::STATUS_PUBLISHED)
    {
        unset($params['status']);
        $service = new Service();
        $params = array_merge($params, ['user_id' => $userId, 'status' => $status]);

        try {
            $service = $service->create(
                $params
            );
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        //日志记录 创建 状态已发布
        $this->operationLogService->log(
            OperationLog::OPERATION_CREATE,
            OperationLog::TABLE_SERVICES,
            $service->id,
            $userId,
            '',
            OperationLog::STATUS_PUBLISHED
        );

        return $service;
    }

    //购买服务
    public function buyService($userId, $serviceId, $reward, $deadline)
    {
        $service = self::getServiceById($serviceId);

        if (!$service) {
            throw new NotFoundHttpException();
        }

        $acceptedService = new AcceptedService();

        $acceptedService = $acceptedService->create(
            [
                'assign_user_id' => $userId,
                'serve_user_id' => $service->user_id,
                'parent_id' => $service->id,
                'reward' => $reward,
                'deadline' => $deadline,
                'status' => AcceptedService::STATUS_SUBMITTED,
                'comment' => '',
            ]
        );

        //记录日志 -- 购买服务
        $this->operationLogService->log(
            OperationLog::OPERATION_BUY,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $userId,
            '',
            OperationLog::STATUS_COMMITTED
        );

        return $acceptedService;
    }

    //同意 购买服务
    public function acceptBoughtService(AcceptedService $acceptedService)
    {

        $acceptedService->status = AcceptedService::STATUS_UNPAID;
        $acceptedService->save();

        //记录日志 -- 采纳接受的委托
        $this->operationLogService->log(
            OperationLog::OPERATION_ACCEPT,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $acceptedService->serve_user_id,
            OperationLog::STATUS_COMMITTED,
            OperationLog::STATUS_UNPAID
        );

        //todo 发送推送
        return $acceptedService;
    }

    public function dealAcceptedService(AcceptedService $acceptedService)
    {
        $acceptedService->status = AcceptedService::STATUS_DEALT;
        $acceptedService->save();

        //记录日志 -- 采纳接受的委托
        $this->operationLogService->log(
            OperationLog::OPERATION_DEAL,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $acceptedService->serve_user_id,
            OperationLog::STATUS_ADAPTED,
            OperationLog::STATUS_DEALT
        );

        //todo 发送推送
        return $acceptedService;

    }

    public function finishAcceptedService(AcceptedService $acceptedService)
    {
        if ($acceptedService->status == AcceptedService::STATUS_ADAPTED) {
            $originStatus = OperationLog::STATUS_ADAPTED;
        } else {
            $originStatus = OperationLog::STATUS_DEALT;
        }

        $acceptedService = DB::transaction(function () use ($acceptedService, $originStatus) {
            $acceptedService->status = AcceptedService::STATUS_FINISHED;
            $acceptedService->save();

            //记录日志 -- 采纳接受的委托
            $this->operationLogService->log(
                OperationLog::OPERATION_FINISH,
                OperationLog::TABLE_ACCEPTED_SERVICES,
                $acceptedService->id,
                $acceptedService->assign_user_id,
                $originStatus,
                OperationLog::STATUS_FINISHED
            );

            //todo 把钱打到serve_user账户

            return $acceptedService;
        });

        //todo 发送推送
        return $acceptedService;
    }



}
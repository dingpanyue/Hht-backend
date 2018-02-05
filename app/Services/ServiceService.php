<?php

namespace App\Services;

use App\Models\AcceptedService;
use App\Models\OperationLog;
use App\Models\Order;
use App\Models\Service;
use App\Models\TimedTask;
use App\Models\User;
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
    protected $flowLogService;

    public function __construct(Service $service, OperationLogService $operationLogService, FlowLogService $flowLogService)
    {
        $this->serviceEloqument = $service;
        $this->operationLogService = $operationLogService;
        $this->flowLogService = $flowLogService;
    }

    //获取指定服务 简略
    public function getServiceById($serviceId)
    {
        $service = $this->serviceEloqument->with('user')->find($serviceId);
        return $service;
    }

    //我发布的服务
    public function getServicesByUser(User $user, $status = 'all')
    {
        $services = $this->serviceEloqument->with('acceptedServicesCommitted')->where('user_id', $user->id)->where('expired_at', '>', date('Y-m-d H:i:s'))->orderBy('status', 'asc');

        if ($status != 'all') {
            $services->where('status', $status);
        }

        $services = $services->get();
        return $services;
    }

    //根据服务id获取服务信息  附带服务产生的acceptedService中等待处理的信息
    public function getServiceDetailById($id)
    {
        $service = $this->serviceEloqument->with('acceptedServicesCommitted')->with('acceptedServicesCommitted.assignUser')->with('acceptedServicesCommitted.assignUser.userInfo')->with('user')->with('user.userInfo')->find($id);
        return $service;
    }

    public function getList($params, $status = Service::STATUS_PUBLISHED)
    {
        $params = array_filter($params);

        dd(date('Y-m-d H:i:s'));
        $services = $this->serviceEloqument->with('user')->with('user.userInfo')->where('status', $status)->whereDate('expired_at', '>', date('Y-m-d H:i:s'));
        if (isset($params['classification'])) {
            $services = $services->where('classification', $params['classification']);
        }
        if (isset($params['keyword'])) {
            $services = $services->where('title', 'like', $params['keyword']);
        }
        $orderBy = 'created_at';
        $order = 'desc';
        if (isset($params['order_by'])) {
            $orderBy = $params['order_by'];
        }
        if (isset($params['order'])) {
            $order = $params['order'];
        }

        if (isset($params['near_by']) && $params['near_by'] && isset($params['lng']) && isset($params['lat'])) {
            $services = $services->orderBy($orderBy, $order)->get();
            $nearbyServices = [];

            foreach ($services as $k => $service) {
                $distance = Helper::getDistance($params['lng'], $params['lat'], $service->lng, $service->lat);

                if ($distance <= 5) {
                    $service->distance = $distance;
                    $nearbyServices[] = $service;
                }
            }
            $services = $nearbyServices;
        } else {
            $services = $services->orderBy($orderBy, $order)->paginate('20');
        }

        return $services;
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

    //取消服务  但不影响已经提交的购买申请  和已经在处理中的服务
    public function cancelService(Service $service)
    {
        $service->status = Service::STATUS_CANCELED;
        $service->save();

        return $service;
    }

    //购买服务
    public function buyService($userId, $serviceId, $reward, $deadline, $comment)
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
                'comment' => $comment,
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

        //推送
        $message = "您发布的服务 $service->title 有了一个新的购买请求";
        GatewayWorkerService::sendSystemMessage($message, $service->user_id);

        return $acceptedService;
    }

    //同意 购买服务
    public function acceptBoughtService(AcceptedService $acceptedService)
    {

        $acceptedService->status = AcceptedService::STATUS_UNPAID;
        $acceptedService->save();

        $service = $acceptedService->service;

        //定时检查30分钟有没有支付
        $timedTask = new TimedTask();
        $timedTask->name = "服务 $acceptedService->id expire";
        $timedTask->command = "expire serve $acceptedService->id";
        $timedTask->start_time = date('Y-m-d H:i', strtotime('now + 30 minutes')) . ':00';
        $timedTask->result = 0;
        $timedTask->save();

        //记录日志 -- 采纳接受的委托
        $this->operationLogService->log(
            OperationLog::OPERATION_ACCEPT,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $acceptedService->serve_user_id,
            OperationLog::STATUS_COMMITTED,
            OperationLog::STATUS_UNPAID
        );

        //推送
        $message = "您购买服务 $service->title 的请求已被接受，请尽快支付";
        GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);

        return $acceptedService;
    }

    //拒绝 购买服务
    public function refuseBoughtService(AcceptedService $acceptedService)
    {
        $acceptedService->status = AcceptedService::STATUS_REFUSED;
        $acceptedService->save();

        $service = $acceptedService->service;

        //记录日志 -- 拒绝接受的委托
        $this->operationLogService->log(
            OperationLog::OPERATION_REFUSE,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $acceptedService->serve_user_id,
            OperationLog::STATUS_COMMITTED,
            OperationLog::STATUS_REFUSED
        );

        //推送
        $message = "您购买服务 $service->title 的请求已被拒绝";
        GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);

        return $acceptedService;

    }

    //提交完成
    public function dealAcceptedService(AcceptedService $acceptedService)
    {
        $acceptedService->status = AcceptedService::STATUS_DEALT;
        $acceptedService->save();

        $service = $acceptedService->service;

        //记录日志 -- 采纳接受的委托
        $this->operationLogService->log(
            OperationLog::OPERATION_DEAL,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $acceptedService->serve_user_id,
            OperationLog::STATUS_ADAPTED,
            OperationLog::STATUS_DEALT
        );

        //推送
        $message = "您购买的服务 $service->title 已被提交完成，请核实后确认，如在截止时间内未对此委托进行操作，系统将默认完成该服务";
        GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);

        return $acceptedService;

    }

    //确认完成
    public function finishAcceptedService(AcceptedService $acceptedService)
    {
        if ($acceptedService->status == AcceptedService::STATUS_ADAPTED) {
            $originStatus = OperationLog::STATUS_ADAPTED;
        } else {
            $originStatus = OperationLog::STATUS_DEALT;
        }

        $globalConfigs = app('global_configs');
        $rate = $globalConfigs['service_fee_rate'];

        $order = Order::where('type', 'service')->where('primary_key', $acceptedService->id)->where('status', 'succeed')->first();

        $service = $acceptedService->service;

        $acceptedService = DB::transaction(function () use ($acceptedService, $originStatus, $rate, $order) {
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

            //把报酬打到serve_user 账户          增加服务星级
            $serveUser = $acceptedService->serveUser;
            $serveUserInfo = $serveUser->userInfo;

            //更新余额
            $serveUserInfo->balance = $serveUserInfo->balance + $acceptedService->reward * (1 - $rate);
            //更新积分
            $serveUserInfo->serve_points = $serveUserInfo->serve_points + (int)$acceptedService->reward;
            $serveUserInfo->save();

            //todo 增加流水记录（余额的形式）
            $this->flowLogService->log(
                $acceptedService->assign_user_id,
                'orders',
                Order::BALANCE,
                $order->id,
                -$order->fee
            );

            $assignUser = $acceptedService->assignUser;
            $assignUserInfo = $assignUser->userInfo;


            //更新委托人积分
            $assignUserInfo->assign_points = $assignUserInfo->assign_points + (int)$acceptedService->reward;
            $assignUserInfo->save();

            return $acceptedService;
        });

        //推送
        $message = "您提交完成的服务 $service->title 已被确认完成，服务报酬已经打入您的余额";
        GatewayWorkerService::sendSystemMessage($message, $acceptedService->serve_user_id);

        return $acceptedService;
    }

    //拒绝完成
    public function refuseToFinishAcceptedService(AcceptedService $acceptedService)
    {
        $acceptedService->status = AcceptedService::STATUS_ARBITRATED;
        $acceptedService->save();

        $service = $acceptedService->service;

        //记录日志 -- 拒绝完成接受的服务
        $this->operationLogService->log(
            OperationLog::OPERATION_REFUSE_FINISH,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $acceptedService->assign_user_id,
            OperationLog::STATUS_DEALT,
            OperationLog::STATUS_ARBITRATED
        );

        //todo 推送给客服 客服系统还没做

        //推送
        $message = "您售出的服务 $service->title 已被购买方拒绝完成，请耐心等待客服介入";
        GatewayWorkerService::sendSystemMessage($message, $acceptedService->serve_user_id);

        return $acceptedService;
    }

    //取消购买服务的申请
    public function cancelAcceptedService(AcceptedService $acceptedService)
    {
        $acceptedService->status = AcceptedService::STATUS_CANCELED;
        $acceptedService->save();

        $service = $acceptedService->service;

        $this->operationLogService->log(
            OperationLog::OPERATION_CANCEL,
            OperationLog::TABLE_ACCEPTED_SERVICES,
            $acceptedService->id,
            $acceptedService->assign_user_id,
            OperationLog::STATUS_COMMITTED,
            OperationLog::STATUS_CANCELED
        );

        $message = "您的服务 $service->title 有一个购买申请已被购买方取消";
        GatewayWorkerService::sendSystemMessage($message, $acceptedService->serve_user_id);

        return $acceptedService;
    }


}
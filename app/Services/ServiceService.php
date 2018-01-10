<?php
namespace App\Services;
use App\Models\AcceptedService;
use App\Models\OperationLog;
use App\Models\Service;
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

    public function __construct(Service $service, OperationLogService $operationLogService)
    {
        $this->serviceEloqument = $service;
        $this->operationLogService = $operationLogService;
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
        $service = $this->serviceEloqument->with('acceptedServicesCommitted')->with('user')->with('user.userInfo')->find($id);
        return $service;
    }

    public function getList($params,  $status = Service::STATUS_PUBLISHED)
    {
        $params = array_filter($params);

        $services = $this->serviceEloqument->where('status', $status)->whereDate('expired_at', '>', date('Y-m-d H:i:s'));
        if (isset($params['classification'])) {
            $services = $services->where('classification', $params['classification']);
        }
        if (isset($params['keyword'])) {
            $assignments = $services->where('title', 'like', $params['keyword']);
        }
        $orderBy = 'created_at';
        $order = 'desc';
        if (isset($params['order_by'])) {
            $orderBy = $params['order_by'];
        }
        if (isset($params['order'])) {
            $order = $params['order'];
        }

        $services = $services->orderBy($orderBy, $order)->paginate('20');

        if (isset($params['near_by']) && $params['near_by'] && isset($params['lng']) && isset($params['lat'])) {
            foreach ($services as $k => $service) {
                $distance = Helper::getDistance($params['lng'], $params['lat'], $service->lng, $service->lat);
                if ($distance > 5) {
                    unset($services[$k]);
                }
            }
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

    public function finishAcceptedService(AcceptedService $acceptedService)
    {
        if ($acceptedService->status == AcceptedService::STATUS_ADAPTED) {
            $originStatus = OperationLog::STATUS_ADAPTED;
        } else {
            $originStatus = OperationLog::STATUS_DEALT;
        }

        $globalConfigs = app('global_configs');
        $rate = $globalConfigs['service_fee_rate'];

        $service = $acceptedService->service;

        $acceptedService = DB::transaction(function () use ($acceptedService, $originStatus, $rate) {
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
            $serveUserInfo->serve_points = $serveUserInfo->serve_ponts + (int)$acceptedService->reward;
            $serveUserInfo->save();

            //todo 增加流水记录（余额的形式）

            $assignUser = $acceptedService->assignUser;
            $assignUserInfo = $assignUser->userInfo;


            //更新委托人积分
            $assignUserInfo->assign_points = $assignUserInfo->assign_ponts + (int)$acceptedService->reward;
            $assignUserInfo->save();

            return $acceptedService;
        });

        //推送
        $message = "您提交完成的服务 $service->title 已被确认完成，服务报酬已经打入您的余额";
        GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);

        return $acceptedService;
    }

    //todo 拒绝完成


}
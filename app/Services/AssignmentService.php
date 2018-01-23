<?php
namespace App\Services;

use App\Models\AcceptedAssignment;
use App\Models\Assignment;
use App\Models\OperationLog;
use App\Models\Order;
use App\Models\TimedTask;
use App\Models\User;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/11/3
 * Time: 14:36
 */
class AssignmentService
{
    protected $assignmentEloqument;
    protected $operationLogService;
    protected $flowLogService;

    public function __construct(Assignment $assignment, OperationLogService $operationLogService, FlowLogService $flowLogService)
    {
        $this->assignmentEloqument = $assignment;
        $this->operationLogService = $operationLogService;
        $this->flowLogService = $flowLogService;
    }

    //创建（发布）委托
    public function create($userId, $params, $status = Assignment::STATUS_UNPAID)
    {
        unset($params['status']);
        $assignment = new Assignment();
        $params = array_merge($params, ['user_id' => $userId, 'status' => $status]);

        try {
            $assignment = $assignment->create(
                $params
            );
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        //定时任务
        $timedTask = new TimedTask();
        $timedTask->name = "委托$assignment->id expire";
        $timedTask->command = "expire assign $assignment->id";
        $timedTask->start_time = date('Y-m-d H:i', strtotime($assignment->expired_at)) . ':00';
        $timedTask->result = 0;
        $timedTask->save();

        //日志记录 创建 状态未支付
        $this->operationLogService->log(
            OperationLog::OPERATION_CREATE,
            OperationLog::TABLE_ASSIGNMENTS,
            $assignment->id,
            $userId,
            '',
            OperationLog::STATUS_UNPAID
        );

        return $assignment;
    }

    //获取委托列表
    public function getList($params,  $status = Assignment::STATUS_WAIT_ACCEPT)
    {
        $params = array_filter($params);

        $assignments = $this->assignmentEloqument->with('user')->with('user.userInfo')->where('status', $status)->whereDate('expired_at', '>', date('Y-m-d H:i:s'));

        if (isset($params['classification'])) {
            $assignments = $assignments->where('classification', $params['classification']);
        }

        if (isset($params['keyword'])) {
            $assignments = $assignments->where('title', 'like', $params['keyword']);
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
            $assignments = $assignments->orderBy($orderBy, $order)->get();
            $nearbyAssignments = [];

            foreach ($assignments as $k => $assignment) {
                $distance = Helper::getDistance($params['lng'], $params['lat'], $assignment->lng, $assignment->lat);

                if ($distance <= 5) {
                    $assignment->distance = $distance;
                    $nearbyAssignments[] = $assignment;
                }
            }

            $assignments = $nearbyAssignments;
        } else {
            $assignments = $assignments->orderBy($orderBy, $order)->paginate('20');
        }

        return $assignments;
    }

    //获取指定委托
    public function getAssignmentById($assignmentId)
    {
        $assignment = $this->assignmentEloqument->with('user')->with('acceptedAssignments')->find($assignmentId);
        return $assignment;
    }

    //接受委托
    public function acceptAssignment($userId, $assignmentId, $reward = null, $deadline = null)
    {
        $assignment = self::getAssignmentById($assignmentId);

        if (!$assignment) {
            throw new NotFoundHttpException();
        }

        $acceptedAssignment = new AcceptedAssignment();

        if ($assignment->reward && $assignment->deadline) {
            $acceptedAssignment = $acceptedAssignment->create(
                [
                    'assign_user_id' => $assignment->user_id,
                    'serve_user_id' => $userId,
                    'parent_id' => $assignment->id,
                    'reward' => $assignment->reward,
                    'deadline' => $assignment->deadline,
                    'status' => AcceptedAssignment::STATUS_SUBMITTED,
                    'comment' => '',
                ]
            );
        } else {
            //发布者的委托中有什么取什么
            if ($assignment->reward) {
                $reward = $assignment->reward;
            }

            if ($assignment->deadline) {
                $deadline = $assignment->deadline;
            }

            $acceptedAssignment = $acceptedAssignment->create(
                [
                    'assign_user_id' => $assignment->user_id,
                    'serve_user_id' => $userId,
                    'parent_id' => $assignment->id,
                    'reward' => $reward,
                    'deadline' => $deadline,
                    'status' => AcceptedAssignment::STATUS_SUBMITTED,
                    'comment' => '',
                ]
            );
        }

        //记录日志 -- 接受委托
        $this->operationLogService->log(
            OperationLog::OPERATION_ACCEPT,
            OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
            $acceptedAssignment->id,
            $userId,
            '',
            OperationLog::STATUS_COMMITTED
        );

        //推送
        $message = "您发布的委托 $assignment->title 有人申请接受";
        GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);

        return $acceptedAssignment;
    }

    //采纳接受的委托
    public function adaptAcceptedAssignment(AcceptedAssignment $acceptedAssignment)
    {
        $assignment = $this->assignmentEloqument->find($acceptedAssignment->parent_id);

        $acceptedAssignment = DB::transaction(function () use ($acceptedAssignment, $assignment) {

            $acceptedAssignment->status = AcceptedAssignment::STATUS_ADAPTED;
            $acceptedAssignment->save();

            $assignment->status = Assignment::STATUS_ADAPTED;
            $assignment->adapted_assignment_id = $acceptedAssignment->id;
            $assignment->save();

            //添加定时任务， 检测 deadline
            $timedTask = new TimedTask();
            $timedTask->name = "接受的委托$acceptedAssignment->id 达到deadline";
            $timedTask->command = "outDate assign $acceptedAssignment->id";
            $timedTask->start_time = date('Y-m-d H:i', strtotime($acceptedAssignment->deadline)) . ':00';
            $timedTask->result = 0;
            $timedTask->save();

            return $acceptedAssignment;
        });

        //记录日志 -- 采纳接受的委托
        $this->operationLogService->log(
            OperationLog::OPERATION_ADAPT,
            OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
            $acceptedAssignment->id,
            $acceptedAssignment->assign_user_id,
            OperationLog::STATUS_COMMITTED,
            OperationLog::STATUS_ADAPTED
        );

        //推送
        $message = "您接受委托 $assignment->title 的申请已被采纳，请在 $acceptedAssignment->deadline 之前完成委托并提交给委托人";
        GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->serve_user_id);

        return $acceptedAssignment;
    }

    //提交  完成的委托给assign_user 确认
    public function dealAcceptedAssignment(AcceptedAssignment $acceptedAssignment, $userId)
    {
        $acceptedAssignment->status = AcceptedAssignment::STATUS_DEALT;
        $acceptedAssignment->save();

        $assignment = $acceptedAssignment->assignment;

        //日志记录 serve_user解决问题，提交申请让assign_user确认
        $this->operationLogService->log(
            OperationLog::OPERATION_DEAL,
            OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
            $acceptedAssignment->id,
            $userId,
            OperationLog::STATUS_ADAPTED,
            OperationLog::STATUS_DEALT
        );

        //推送
        $message = "您发布的委托 $assignment->title 的已被提交完成，请核实后确认，如在截止时间内未对此委托进行操作，系统将默认完成该委托";
        GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);

        return $acceptedAssignment;
    }

    //确认 委托已经完成。 可以是assign_user 直接确认   也可以是serve_user 先提交再确认
    public function finishAcceptedAssignment(AcceptedAssignment $acceptedAssignment, $userId)
    {
        if ($acceptedAssignment->status == AcceptedAssignment::STATUS_ADAPTED) {
            $originStatus = OperationLog::STATUS_ADAPTED;
        } else {
            $originStatus = OperationLog::STATUS_DEALT;
        }

        $globalConfigs = app('global_configs');
        $rate = $globalConfigs['service_fee_rate'];

        $assignment = $this->assignmentEloqument->find($acceptedAssignment->parent_id);
        $order = Order::where('type', 'assignment')->where('primary_key', $assignment->id)->where('status', 'succeed')->first();

        $acceptedAssignment = DB::transaction(function () use ($acceptedAssignment, $rate, $assignment, $order) {

            $acceptedAssignment->status = AcceptedAssignment::STATUS_FINISHED;
            $acceptedAssignment->save();


            $assignment->status = Assignment::STATUS_FINISHED;
            $assignment->save();

            //把报酬打到serve_user 账户          增加服务星级
            $serveUser = $acceptedAssignment->serveUser;
            $serveUserInfo = $serveUser->userInfo;

            //更新余额
            $serveUserInfo->balance = $serveUserInfo->balance + $acceptedAssignment->reward * (1 - $rate);
            //更新积分
            $serveUserInfo->serve_points = $serveUserInfo->serve_points + (int)$acceptedAssignment->reward;
            $serveUserInfo->save();

            //流水日志
            $this->flowLogService->log(
                $acceptedAssignment->serve_user_id,
                'orders',
                Order::BALANCE,
                $order->id,
                -$order->fee
            );

            $assignUser = $acceptedAssignment->assignUser;
            $assignUserInfo = $assignUser->userInfo;


            //更新委托人积分
            $assignUserInfo->assign_points = $assignUserInfo->assign_points + (int)$acceptedAssignment->reward;
            $assignUserInfo->save();

            return $acceptedAssignment;
        });

        //日志记录委托完成
        $this->operationLogService->log(
            OperationLog::OPERATION_FINISH,
            OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
            $acceptedAssignment->id,
            $userId,
            $originStatus,
            OperationLog::STATUS_FINISHED
        );

        //推送
        $message = "您接受的委托 $assignment->title 的已被确认完成，委托报酬已经打入您的余额";
        GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->serve_user_id);

        return $acceptedAssignment;
    }

    public function refuseFinishingAcceptedAssignment(AcceptedAssignment $acceptedAssignment, $userId)
    {
        $acceptedAssignment->status = AcceptedAssignment::STATUS_ARBITRATED;
        $acceptedAssignment->save();

        $assignment = $this->assignmentEloqument->find($acceptedAssignment->parent_id);

        //日志记录 assign_user拒绝解决问题，提交申请让assign_user确认
        $this->operationLogService->log(
            OperationLog::OPERATION_REFUSE_FINISH,
            OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
            $acceptedAssignment->id,
            $userId,
            OperationLog::STATUS_DEALT,
            OperationLog::STATUS_ARBITRATED
        );

        //todo 推送给客服 客服系统还没做


        //推送给提交完成的人
        $message = "您提交的完成委托 $assignment->title 的请求已被拒绝，目前交由客服处理中，请联系客服或者委托人了解详情";
        GatewayWorkerService::sendSystemMessage($message, $acceptedAssignment->serve_user_id);

        return $acceptedAssignment;

    }

    public function getAssignmentOperationLog(Assignment $assignment)
    {
        $operations = $this->operationLogService->getAssignmentOperationLogs($assignment);
        return $operations;
    }

    //我发布的委托
    public function getAssignmentsByUser(User $user, $status = 'all')
    {
        //自己发布的委托
        $assignments = $this->assignmentEloqument->with('acceptedAssignments')->where('user_id', $user->id)->orderBy('status', 'asc');

        if ($status != 'all') {
            $assignments->where('status', $status);
        }

        $assignments = $assignments->get();

        return $assignments;
    }
}
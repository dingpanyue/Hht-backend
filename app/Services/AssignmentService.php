<?php
namespace App\Services;

use App\Models\AcceptedAssignment;
use App\Models\Assignment;
use App\Models\OperationLog;
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

    public function __construct(Assignment $assignment, OperationLogService $operationLogService)
    {
        $this->assignmentEloqument = $assignment;
        $this->operationLogService = $operationLogService;
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
    public function getList($params, array $status = [Assignment::STATUS_WAIT_ACCEPT])
    {
        $params = array_filter($params);

        $assignments = $this->assignmentEloqument->where('status', 'in', $status);
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

        $assignments = $assignments->orderBy($orderBy, $order)->paginate('20');

        if ($params['near_by'] && isset($params['lng']) && isset($params['lat'])) {
            foreach ($assignments as $k => $assignment) {
                $distance = Helper::getDistance($params['lng'], $params['lat'], $assignment->lng, $assignment->lat);
                if ($distance > 5) {
                    unset($assignments[$k]);
                }
            }
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

        return $acceptedAssignment;
    }

    //采纳接受的委托
    public function adaptAcceptedAssignment(AcceptedAssignment $acceptedAssignment)
    {
        $acceptedAssignment = DB::transaction(function () use ($acceptedAssignment) {

            $acceptedAssignment->status = AcceptedAssignment::STATUS_ADAPTED;
            $acceptedAssignment->save();

            $assignment = $this->assignmentEloqument->find($acceptedAssignment->parent_id);
            $assignment->status = Assignment::STATUS_ADAPTED;
            $assignment->adapted_assignment_id = $acceptedAssignment->id;
            $assignment->save();

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

        //todo 发送推送
        return $acceptedAssignment;
    }

    //提交  完成的委托给assign_user 确认
    public function dealAcceptedAssignment(AcceptedAssignment $acceptedAssignment, $userId)
    {
        $acceptedAssignment->status = AcceptedAssignment::STATUS_DEALT;
        $acceptedAssignment->save();

        //日志记录 serve_user解决问题，提交申请让assign_user确认
        $this->operationLogService->log(
            OperationLog::OPERATION_DEAL,
            OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
            $acceptedAssignment->id,
            $userId,
            OperationLog::STATUS_ADAPTED,
            OperationLog::STATUS_DEALT
        );


        //todo 发送推送

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

        $acceptedAssignment = DB::transaction(function () use ($acceptedAssignment, $rate) {

            $acceptedAssignment->status = AcceptedAssignment::STATUS_FINISHED;
            $acceptedAssignment->save();

            $assignment = $this->assignmentEloqument->find($acceptedAssignment->parent_id);
            $assignment->status = Assignment::STATUS_FINISHED;
            $assignment->save();

            //把报酬打到serve_user 账户          增加服务星级
            $serveUser = $acceptedAssignment->serveUser;
            $serveUserInfo = $serveUser->userInfo;

            //更新余额
            $serveUserInfo->balance = $serveUserInfo->balance + $acceptedAssignment->reward * (1 - $rate);
            //更新积分
            $serveUserInfo->serve_points = $serveUserInfo->serve_ponts + (int)$acceptedAssignment->reward;
            $serveUserInfo->save();

            //todo 增加流水记录（余额的形式）

            $assignUser = $acceptedAssignment->assignUser;
            $assignUserInfo = $assignUser->userInfo;


            //更新委托人积分
            $assignUserInfo->assign_points = $assignUserInfo->assign_ponts + (int)$acceptedAssignment->reward;
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

        //todo 推送

        return $acceptedAssignment;
    }

    public function refuseFinishingAcceptedAssignment(AcceptedAssignment $acceptedAssignment, $userId)
    {
        $acceptedAssignment->status = AcceptedAssignment::STATUS_ARBITRATED;
        $acceptedAssignment->save();

        //日志记录 assign_user拒绝解决问题，提交申请让assign_user确认
        $this->operationLogService->log(
            OperationLog::OPERATION_REFUSE_FINISH,
            OperationLog::TABLE_ACCEPTED_ASSIGNMENTS,
            $acceptedAssignment->id,
            $userId,
            OperationLog::STATUS_DEALT,
            OperationLog::STATUS_ARBITRATED
        );

        //todo 推送给客服

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
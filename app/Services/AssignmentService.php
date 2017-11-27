<?php
namespace App\Services;

use App\Models\AcceptedAssignment;
use App\Models\Assignment;
use App\Models\OperationLog;
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
        $params = array_merge($params, ['public_user_id' => $userId, 'status' => $status]);

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
            Assignment::class,
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
        return $assignments;
    }

    //获取指定委托
    public function getAssignmentById($assignmentId)
    {
        $assignment = $this->assignmentEloqument->with('user')->find($assignmentId);
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
                    'created_from' => 'assignment',
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
                    'created_from' => 'assignment',
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
            AcceptedAssignment::class,
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
            $assignment->save();

            return $acceptedAssignment;
        });

        //记录日志 -- 采纳接受的委托
        $this->operationLogService->log(
            OperationLog::OPERATION_ADAPT,
            AcceptedAssignment::class,
            $acceptedAssignment->id,
            $acceptedAssignment->assign_user_id,
            OperationLog::STATUS_COMMITTED,
            OperationLog::STATUS_ADAPTED
        );

        //todo 发送推送
        return $acceptedAssignment;
    }

    //取消 采纳的 接受的委托
    public function cancelAcceptedAssignment(AcceptedAssignment $acceptedAssignment, $userId)
    {
        $acceptedAssignment->status = AcceptedAssignment::STATUS_CANCELED;
        $acceptedAssignment->save();

        //记录日志

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
            AcceptedAssignment::class,
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

        $acceptedAssignment = DB::transaction(function () use ($acceptedAssignment) {

            $acceptedAssignment->status = AcceptedAssignment::STATUS_FINISHED;
            $acceptedAssignment->save();

            $assignment = $this->assignmentEloqument->find($acceptedAssignment->parent_id);
            $assignment->status = Assignment::STATUS_FINISHED;
            $assignment->save();

            //todo 把报酬打到serve_user 账户



            return $acceptedAssignment;
        });

        //日志记录委托完成
        $this->operationLogService->log(
            OperationLog::OPERATION_FINISH,
            AcceptedAssignment::class,
            $acceptedAssignment->id,
            $userId,
            $originStatus,
            OperationLog::STATUS_FINISHED
        );

        //todo 推送

        return $acceptedAssignment;
    }
}
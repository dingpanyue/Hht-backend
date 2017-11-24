<?php

namespace App\Services;

use App\Models\AcceptedAssignment;
use App\Models\Assignment;
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

    public function __construct(Assignment $assignment)
    {
        $this->assignmentEloqument = $assignment;
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

            $acceptedAssignment = DB::transaction(function () use ($acceptedAssignment, $assignment, $userId) {
                $acceptedAssignment = $acceptedAssignment->create(
                    [
                        'accepted_user_id' => $userId,
                        'assignment_id' => $assignment->id,
                        'reward' => $assignment->reward,
                        'deadline' => $assignment->deadline,
                        //todo 状态为已接受且已确认同意
                        'status' => '',
                        'comment' => ''
                    ]
                );

                $assignment->status = Assignment::STATUS_ACCEPTED;
                $assignment->save();

                return $acceptedAssignment;
            });

            return $acceptedAssignment;
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
                    'accepted_user_id' => $userId,
                    'assignment_id' => $assignment->id,
                    'reward' => $reward,
                    'deadline' => $deadline,
                    //todo 状态为已接受但未确认同意
                    'status' => '',
                    'comment' => ''
                ]
            );
            return $acceptedAssignment;
        }
    }


}
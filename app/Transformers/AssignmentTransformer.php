<?php
namespace App\Transformers;
use App\Models\Assignment;
use App\Services\Helper;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/27
 * Time: 5:22
 */
class AssignmentTransformer
{
    public  static function transform(Assignment $assignment)
    {
        $classifications = Helper::transformToKeyValue(app('assignment_classifications'), 'id', 'name');

        $statuses = [
            Assignment::STATUS_UNPAID => '未支付',
            Assignment::STATUS_WAIT_ACCEPT => '待选择服务方',
            Assignment::STATUS_ADAPTED => '已采纳',
            Assignment::STATUS_CANCELED => '已取消',
            Assignment::STATUS_FINISHED => '已完成',
            Assignment::STATUS_FAILED => '委托失败',
            Assignment::STATUS_REFUNDING => '退款中'
        ];

        if ($assignment->status == Assignment::STATUS_WAIT_ACCEPT) {
            $assignment->apply_count = count($assignment->acceptedAssignments);
        }

        if (!$assignment->classifications()) {
            $assignment->classifications = $assignment->classifications()->get('classification');
        }

        $classificationsArray = [];
        foreach ($assignment->classifications as $classification) {
            $classifications[] = $classifications[$classification];
        }
        $assignment->classifications = $classificationsArray;

        $assignment->status = $statuses[$assignment->status];

        if ($assignment->acceptedAssignments) {
            $assignment->accepted_assignments = AcceptedAssignmentTransformer::transformList($assignment->acceptedAssignments, false);
        }

        if ($assignment->operations) {
            $assignment->operations = OperationLogTransformer::transformList($assignment->operations);
        }

        if ($assignment->adaptedAssignment) {
            $assignment->adapted_assignment = AcceptedAssignmentTransformer::transform($assignment->adaptedAssignment, false);
        }



        return $assignment;
    }

    public static function transformList($assignments)
    {
        foreach ($assignments as $k => $assignment) {
            $assignments[$k] = self::transform($assignment);
        }
        return $assignments;
    }
}
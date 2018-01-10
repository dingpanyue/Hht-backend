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
            Assignment::STATUS_WAIT_ACCEPT => '待接受',
            Assignment::STATUS_ADAPTED => '已采纳',
            Assignment::STATUS_CANCELED => '已取消',
            Assignment::STATUS_FINISHED => '已完成',
            Assignment::STATUS_FAILED => '委托失败'
        ];

        $assignment->classification = $classifications[$assignment->classification];
        $assignment->status = $statuses[$assignment->status];

        if ($assignment->acceptedAssignments) {
            $assignment->accepted_assignments = AcceptedAssignmentTransformer::transformList($assignment->acceptedAssignments, false);
        }

        if ($assignment->operations) {
            $assignment->operations = OperationLogTransformer::transformList($assignment->operations);
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
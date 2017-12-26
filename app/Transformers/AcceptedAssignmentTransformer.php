<?php
namespace App\Transformers;
use App\Models\AcceptedAssignment;
use App\Models\Assignment;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/27
 * Time: 5:54
 */

class AcceptedAssignmentTransformer
{
    public static function transform(AcceptedAssignment $acceptedAssignment, $withAssignment = true)
    {
        $statuses = [
            AcceptedAssignment::STATUS_SUBMITTED => '待采纳',
            AcceptedAssignment::STATUS_ADAPTED => '已采纳',
            AcceptedAssignment::STATUS_CANCELED => '已取消',
            AcceptedAssignment::STATUS_DEALT => '已解决',
            AcceptedAssignment::STATUS_ARBITRATED => '仲裁中',
            AcceptedAssignment::STATUS_FINISHED => '已完成',
            AcceptedAssignment::STATUS_FAILED => '委托失败'
        ];

        $acceptedAssignment->status = $statuses[$acceptedAssignment->status];

        if ($withAssignment) {
            $acceptedAssignment->assignment = AssignmentTransformer::transform($acceptedAssignment->assignment);
        }

        return $acceptedAssignment;
    }

    public static function transformList($acceptedAssignments, $withAssignment = true)
    {
        foreach ($acceptedAssignments as $k => $acceptedAssignment) {
            $acceptedAssignments[$k] = self::transform($acceptedAssignment, $withAssignment);
        }
        return $acceptedAssignments;
    }
}
<?php
namespace App\Services;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/11/26
 * Time: 18:18
 */

use App\Models\AcceptedAssignment;

class AcceptedAssignmentService
{
    protected $acceptedAssignmentEloqument;

    public function __construct(AcceptedAssignment $acceptedAssignment)
    {
        $this->acceptedAssignmentEloqument = $acceptedAssignment;
    }

    public function getAcceptedAssignmentById($id)
    {
        $acceptedAssignment = $this->acceptedAssignmentEloqument->find($id);
        return $acceptedAssignment;
    }

    public function getAcceptedAssignments($serveUserId = null, $assignUserId = null, $createdFrom = null, $parentId = null, $status = null)
    {
        $acceptedAssignments = $this->acceptedAssignmentEloqument;

        if ($serveUserId) {
            $acceptedAssignments = $acceptedAssignments->where('serve_user_id', $serveUserId);
        }

        if ($assignUserId) {
            $acceptedAssignments = $acceptedAssignments->where('assign_user_id', $assignUserId);
        }

        if ($createdFrom) {
            $acceptedAssignments = $acceptedAssignments->where('created_from', $createdFrom);
        }

        if ($parentId) {
            $acceptedAssignments = $acceptedAssignments->where('parent_id', $parentId);
        }

        if ($status) {
            $acceptedAssignments = $acceptedAssignments->where('status', $status);
        }

        return $acceptedAssignments;
    }

}
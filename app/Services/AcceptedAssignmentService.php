<?php
namespace App\Services;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/11/26
 * Time: 18:18
 */

use App\Models\AcceptedAssignment;
use App\Models\User;

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

    public function getAcceptedAssignments($serveUserId = null, $assignUserId = null, $parentId = null, $status = null)
    {
        $acceptedAssignments = $this->acceptedAssignmentEloqument;

        if ($serveUserId) {
            $acceptedAssignments = $acceptedAssignments->where('serve_user_id', $serveUserId);
        }

        if ($assignUserId) {
            $acceptedAssignments = $acceptedAssignments->where('assign_user_id', $assignUserId);
        }

        if ($parentId) {
            $acceptedAssignments = $acceptedAssignments->where('parent_id', $parentId);
        }

        if ($status) {
            $acceptedAssignments = $acceptedAssignments->where('status', $status);
        }

        return $acceptedAssignments;
    }

    //获取我作为服务者接受的委托
    public function getAcceptedAssignmentsByUser(User $user, $status = 'all')
    {
        $acceptedAssignments = $this->acceptedAssignmentEloqument->with('assignment')->where('serve_user_id', $user->id)->orderBy('updated_at', 'desc');

        if ($status != 'all') {
            $acceptedAssignments = $acceptedAssignments->where('status', $status);
        }

        $acceptedAssignments = $acceptedAssignments->get();
        return $acceptedAssignments;
    }

}
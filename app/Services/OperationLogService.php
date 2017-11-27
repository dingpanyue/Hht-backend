<?php
namespace App\Services;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/11/26
 * Time: 22:01
 */

use App\Models\Assignment;
use App\Models\OperationLog;
use App\Models\Service;


class OperationLogService
{
    public function log($operation, $table, $primaryKey, $userId, $origin_status, $final_status, $comment = '')
    {
        $operationLog = new OperationLog();
        $operationLog->operation = $operation;
        $operationLog->table = $table;
        $operationLog->primary_key = $primaryKey;
        $operationLog->user_id = $userId;
        $operationLog->origin_status = $origin_status;
        $operationLog->final_status = $final_status;
        $operationLog->comment = $comment;

        if ($operationLog->save()) {
            return true;
        } else {
            return false;
        }
    }

    public function getAssignmentOperationLogs(Assignment $assignment) {

    }

    public function getServiceOperationLogs(Service $service) {

    }
}

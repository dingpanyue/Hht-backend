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
    protected $operationLogEloqument;

    public function __construct(OperationLog $operationLog)
    {
        $this->operationLogEloqument = $operationLog;
    }

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
        //在被采纳之前的操作    也就是创建 和 支付
        $operationsBeforeAdapt = $this->operationLogEloqument->with('user')->where('table', OperationLog::TABLE_ASSIGNMENTS)
            ->where('primary_key', $assignment->id)->orderBy('created_at', 'asc')->get()->toArray();

        //已经采纳过 接收的委托的情况
        $operationsAfterAdapt = [];
        if ($assignment->adapted_assignment_id) {
            $operationsAfterAdapt = $this->operationLogEloqument->with('user')->where('table', OperationLog::TABLE_ACCEPTED_ASSIGNMENTS)
                ->where('primary_key', $assignment->adapted_assignment_id)->orderBy('created_at', 'asc')
                ->get()->toArray();
        }

        $operations = array_merge($operationsBeforeAdapt, $operationsAfterAdapt);

        return $operations;
    }

    public function getServiceOperationLogs(Service $service) {

    }
}

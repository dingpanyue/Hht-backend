<?php

namespace App\Transformers;

use App\Models\OperationLog;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/27
 * Time: 8:44
 */
class OperationLogTransformer
{
    public static $operations = [
        OperationLog::OPERATION_CREATE => '创建',          //创建，即发布  委托 或者 服务
        OperationLog::OPERATION_BUY => '购买服务',                //购买服务
        OperationLog::OPERATION_PAY => '支付',                //支付
        OperationLog::OPERATION_ACCEPT => '接受',         //接受，即接受  委托 或则 服务
        OperationLog::OPERATION_ADAPT => '采纳',           //采纳，采纳 被接受的委托 或者 服务
        OperationLog::OPERATION_CANCEL => '取消',        //取消
        OperationLog::OPERATION_DEAL => '提交完成',
        OperationLog::OPERATION_FINISH => '确认完成',
        OperationLog::OPERATION_REFUSE_FINISH => '拒绝完成',
        OperationLog::OPERATION_REFUND => '退款'
    ];

    public static $statuses = [
        OperationLog::STATUS_UNPAID => '未支付',
        OperationLog::STATUS_COMMITTED => '已提交，申请中',
        OperationLog::STATUS_PUBLISHED => '已发布',
        OperationLog::STATUS_WAIT_ACCEPT => '等待接受委托',
        OperationLog::STATUS_ADAPTED => '已采纳',
        OperationLog::STATUS_CANCELED => '已取消',
        OperationLog::STATUS_DEALT => '提交完成，等待确认',
        OperationLog::STATUS_FINISHED => '确认完成',
        OperationLog::STATUS_FAILED => '委托失败',
        OperationLog::STATUS_ARBITRATED => '仲裁中',
        OperationLog::STATUS_REFUNDING => '退款中',
        '' => '-'
    ];

    public static function transform($operationLog)
    {
        if(is_array($operationLog)) {
            $operationLog['operation'] = self::$operations[$operationLog['operation']];
            $operationLog['origin_status'] = self::$statuses[$operationLog['origin_status']];
            $operationLog['final_status'] = self::$statuses[$operationLog['final_status']];
        } else {
            $operationLog->operation = self::$operations[$operationLog->operation];
            $operationLog->origin_status = self::$statuses[$operationLog->origin_status];
            $operationLog->final_status = self::$statuses[$operationLog->final_status];
        }
        return $operationLog;
    }

    public static function transformList($operationLogs)
    {
        foreach ($operationLogs as $k => $operationLog) {
            $operationLogs[$k] = self::transform($operationLog);
        }
        return $operationLogs;
    }
}
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
    public function transform(OperationLog $operationLog)
    {
        $operations = [
            OperationLog::OPERATION_CREATE => '创建',          //创建，即发布  委托 或者 服务
            OperationLog::OPERATION_BUY => '购买服务',                //购买服务
            OperationLog::OPERATION_PAY => '支付',                //支付
            OperationLog::OPERATION_ACCEPT => '接受',         //接受，即接受  委托 或则 服务
            OperationLog::OPERATION_ADAPT => '采纳',           //采纳，采纳 被接受的委托 或者 服务
            OperationLog::OPERATION_CANCEL => '取消',        //取消
            OperationLog::OPERATION_DEAL => '提交完成',
            OperationLog::OPERATION_FINISH => '确认完成',
            OperationLog::OPERATION_REFUSE_FINISH => '拒绝完成',
        ];

        $statuses = [
            OperationLog::STATUS_UNPAID => 'unpaid',
            OperationLog::STATUS_COMMITTED => 'committed',
            OperationLog::STATUS_PUBLISHED => 'published',
            OperationLog::STATUS_WAIT_ACCEPT => 'wait_accept',
            OperationLog::STATUS_ADAPTED => 'adapted',
            OperationLog::STATUS_CANCELED => 'canceled',
            OperationLog::STATUS_DEALT => 'dealt',
            OperationLog::STATUS_FINISHED => 'finished',
            OperationLog::STATUS_FAILED => 'failed',
            OperationLog::STATUS_ARBITRATED => 'arbitrated',
        ];
    }


}
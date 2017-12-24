<?php
namespace App\Services;
use App\Models\FlowLog;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/11
 * Time: 18:16
 */

class FlowLogService
{
    protected $flowLogEloqument;

    public function __construct(FlowLog $flowLog)
    {
        $this->flowLogEloqument = $flowLog;
    }

    public function log($userId, $table, $method, $primaryKey, $amount)
    {
        $this->flowLogEloqument->create(
            [
                'user_id' => $userId,
                'table' => $table,
                'method' => $method,
                'primary_key' => $primaryKey,
                'amount' => $amount
            ]
        );
    }
}
<?php

namespace App\Listeners;

use App\Events\RefundSucceed;
use App\Models\AcceptedService;
use App\Models\Assignment;
use App\Models\OperationLog;
use App\Models\Order;
use App\Services\FlowLogService;
use App\Services\GatewayWorkerService;
use App\Services\OperationLogService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class DealWithRefund
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    protected $operationLogService;

    protected $flowLogService;

    public function __construct(OperationLogService $operationLogService, FlowLogService $flowLogService)
    {
        //
        $this->flowLogService = $flowLogService;
        $this->operationLogService = $operationLogService;
    }

    /**
     * Handle the event.
     *
     * @param  RefundSucceed  $event
     * @return void
     */
    public function handle(RefundSucceed $event)
    {
        //
        $data = $event->data;

        $refund_id = $data->id;

        /**
         * @var $order Order
         */
        if ($data->succeed == true) {
            $order = Order::where('refund_id', $refund_id)->first();

            if ($order->type == Order::TYPE_ASSIGNMENT) {
                /**
                 * @var $assignment Assignment
                 */
                $assignment = Assignment::where('id', $order->primary_key)->first();

                if ($assignment->status == Assignment::STATUS_REFUNDING) {
                    DB::transaction(function () use ($order, $assignment) {
                        $order->status = Order::STATUS_REFUNDED;
                        $order->save();

                        $assignment->status = Assignment::STATUS_FAILED;
                        $assignment->save();
                        //添加操作日志
                        $this->operationLogService->log(
                            OperationLog::OPERATION_REFUND,
                            OperationLog::TABLE_ASSIGNMENTS,
                            $assignment->id,
                            $assignment->user_id,
                            OperationLog::STATUS_REFUNDING,
                            OperationLog::STATUS_FAILED
                        );

                        //流水日志 负数
                        $this->flowLogService->log(
                            $assignment->user_id,
                            'orders',
                            $order->method,
                            $order->id,
                            -$order->fee
                        );
                    });
                    $message = "您发布的委托 $assignment->title 的退款申请已处理成功，退款打入您的支付账户，委托取消";
                    GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);
                }
            }

            if ($order->type == Order::TYPE_SERVICE) {

                $acceptedService = AcceptedService::where('id', $order->primary_key)->first();
                DB::transaction(function () use ($order, $acceptedService) {

                    $order->status = Order::STATUS_REFUNDED;
                    $order->save();

                    $acceptedService->status = Assignment::STATUS_FAILED;
                    $acceptedService->save();

                    //添加操作日志
                    $this->operationLogService->log(
                        OperationLog::OPERATION_REFUND,
                        OperationLog::TABLE_ACCEPTED_SERVICES,
                        $acceptedService->id,
                        0,
                        OperationLog::STATUS_REFUNDING,
                        OperationLog::STATUS_FAILED
                    );

                    //流水日志 负数
                    $this->flowLogService->log(
                        $acceptedService->assign_user_id,
                        'orders',
                        $order->method,
                        $order->id,
                        -$order->fee
                    );
                });
                $message = "您购买的服务的退款已处理成功，退款打入您的支付账户，委托取消";
                GatewayWorkerService::sendSystemMessage($message, $acceptedService->assign_user_id);
            }
        }
    }
}

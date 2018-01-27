<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\OperationLog;
use App\Models\Order;
use App\Models\UserInfo;
use App\Services\FlowLogService;
use App\Services\GatewayWorkerService;
use App\Services\OperationLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Expire extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expire {type} {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $operationLogService;

    protected $flowLogService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(OperationLogService $operationLogService, FlowLogService $flowLogService)
    {
        $this->operationLogService = $operationLogService;

        $this->flowLogService = $flowLogService;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $type = $this->argument('type');
        $id = $this->argument('id');

        $assignment = Assignment::where('id', $id)->first();

        //已经支付没人接的
        if ($assignment->status == Assignment::STATUS_WAIT_ACCEPT) {
            DB::transaction(function () use ($assignment) {

                $assignment->status = Assignment::STATUS_FAILED;
                $assignment->save();

                $order = Order::where('type', 'assignment')->where('primary_key', $assignment->id)->where('status', 'succeed')->first();

                if (!$order) {
                    Log::info("处理委托 $assignment->id outdate时出现错误，没有对应的订单");
                    throw new \Exception();
                }
                if ($order->method == Order::BALANCE) {
                    //返回余额
                    $user = $assignment->user;
                    $balance = UserInfo::where('user_id', $user->id)->pluck('balance');
                    $originBalance = $balance[0];
                    $finalBalance = $originBalance + $order->fee;

                    $userInfo = $user->userInfo;
                    $userInfo->balance = $finalBalance;
                    $userInfo->save();

                    //修改订单状态
                    $order->status = Order::STATUS_REFUNDED;
                    $order->save();

                    //添加操作日志
                    $this->operationLogService->log(
                        OperationLog::OPERATION_REFUND,
                        OperationLog::TABLE_ASSIGNMENTS,
                        $assignment->id,
                        0,
                        OperationLog::STATUS_WAIT_ACCEPT,
                        OperationLog::STATUS_FAILED,
                        "委托已过期，无人接收"
                    );

                    //流水日志 负数
                    $this->flowLogService->log(
                        $assignment->user_id,
                        'orders',
                        $order->method,
                        $order->id,
                        -$order->fee
                    );

                    $message = "您发布的委托由于超过期限未有人接，委托已失败,已退款到您的余额";
                    GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);

                } else {
                    $charge_id = $order->charge_id;

                    \Pingpp\Pingpp::setApiKey(env('sk_live_WznDOCij50iHS4ab9Svf1ev1'));
                    \Pingpp\Pingpp::setPrivateKeyPath(storage_path('private.key'));

                    $ch = \Pingpp\Charge::retrieve($charge_id);//ch_id 是已付款的订单号

                    try {
                        $refund = $ch->refunds->create(
                            array(
                                'amount' => $order->fee * 100,
                                'description' => 'Refund Description'
                            )
                        );
                    } catch (\Exception $e) {
                        \Log::error($e->getCode(), $e->getMessage());
                    }

                    //把委托状态改为退款中   将退款id 写入order
                    DB::transaction(function () use ($order, $assignment, $refund) {
                        $assignment->status = Assignment::STATUS_REFUNDING;
                        $assignment->save();

                        $order->refund_id = $refund->id;
                        $order->save();
                    });

                    $message = "您发布的委托由于超过期限未有人接，委托已失败,已退款到您的余额";
                    GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);
                }
            });
        }

        //未支付的
        if ($assignment->status == Assignment::STATUS_UNPAID) {

            $assignment->status = Assignment::STATUS_FAILED;
            $assignment->save();

            $message = "您发布的委托由于过期仍未支付，已经自动取消";
            GatewayWorkerService::sendSystemMessage($message, $assignment->user_id);

            $this->operationLogService->log(
                OperationLog::OPERATION_CANCEL,
                OperationLog::TABLE_ASSIGNMENTS,
                $assignment->id,
                0,
                OperationLog::STATUS_UNPAID,
                OperationLog::STATUS_FAILED,
                "委托过期无人支付，委托失败"
            );
        }
    }
}

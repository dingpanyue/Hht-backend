<?php

namespace App\Listeners;

use App\Events\TransferSucceed;
use App\Models\Withdrawal;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateWithdrawalStatus
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  TransferSucceed  $event
     * @return void
     */
    public function handle(TransferSucceed $event)
    {
        $data = $event->data;
        $status = $event->status;

        $orderNo = $data->order_no;

        $withdrawal = Withdrawal::where('out_trade_no', $orderNo)->where('status', Withdrawal::STATUS_PROCESSING)->first();

        if (!$withdrawal) {
            \Log::error('单号为'.$orderNo.'的提现 回调时未能找到提现单');
        }
        try {
            $withdrawal->status = $status;
            $withdrawal->save();
        } catch (\Exception $e) {
            \Log::error('单号为'.$orderNo.'的提现 回调时未能成功保存提现单状态');
        }
    }
}

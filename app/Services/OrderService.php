<?php
namespace App\Services;
use App\Models\Order;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/11
 * Time: 12:49
 */

class OrderService
{
    protected $orderEloqument;

    public function __construct(Order $order)
    {
        $this->orderEloqument = $order;
    }

    public function getOrderByOutTradeNo($outTradeNo)
    {
        $order = $this->orderEloqument->where('out_trade_no', $outTradeNo)->first();

        if (!$order) {
            throw new NotFoundHttpException();
        }

        return $order;
    }



}
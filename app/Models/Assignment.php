<?php
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/10/31
 * Time: 13:59
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    //
    const STATUS_UNPAID = 0;                  //待支付
    const STATUS_WAIT_ACCEPT = 1;            //已支付，等待接受委托
    const STATUS_ACCEPTED = 2;               //委托已经被接受
    const STATUS_CANCELED = 3;               //委托被发布人取消
    const STATUS_EXPIRED = 4;                //委托逾期无人接受
    const STATUS_COMMITTED = 5;              //接受的委托一辈完成提交，等待发布人确认状态
    const STATUS_FAILED = 6;                 //委托失败
    const STATUS_SUCCESS = 7;                //委托成功
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/3
 * Time: 8:32
 */

/**
 * Class Order
 * @package App\Models
 * @property integer $id
 * @property string $type
 * @property integer $primary_key
 * @property string $method
 * @property number $fee
 * @property string $out_trade_no
 * @property string $status
 * @property integer $user_id
 * @property string $created_at
 * @property string $updated_at
 */
class Order extends Model
{
    const TYPE_ASSIGNMENT = 'assignment';
    const TYPE_SERVICE = 'service';

    const ALIPAY = 'alipay';
    const WX= 'wx';
    const UPACP = 'upacp';
    const BALANCE = 'balance';

    const STATUS_PREPARING = 'preparing';
    const STATUS_SUCCEED = 'succeed';
    const STATUS_REFUNDED = 'refunded';


}
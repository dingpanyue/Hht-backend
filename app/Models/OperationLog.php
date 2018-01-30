<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class OperationLogs
 * @package App\Models
 * @property integer $id
 * @property string $operation
 * @property string $table
 * @property integer $primary_key
 * @property integer $user_id
 * @property string $origin_status
 * @property string $final_status
 * @property string $comment
 * @property string $created_at
 * @property string $updated_at
 */
class OperationLog extends Model
{
    const OPERATION_CREATE = 'create';          //创建，即发布  委托 或者 服务
    const OPERATION_BUY = 'buy';                //购买服务
    const OPERATION_PAY = 'pay';                //支付
    const OPERATION_ACCEPT = 'accept';         //接受，即接受  委托 或则 服务
    const OPERATION_ADAPT = 'adapt';           //采纳，采纳 被接受的委托 或者 服务
    const OPERATION_CANCEL = 'cancel';        //取消
    const OPERATION_DEAL = 'deal';
    const OPERATION_FINISH = 'finish';
    const OPERATION_REFUSE_FINISH = 'refuse_finish';
    const OPERATION_REFUND = 'refund';
    const OPERATION_REFUSE = 'refuse';

    const STATUS_UNPAID = 'unpaid';
    const STATUS_COMMITTED = 'committed';
    const STATUS_PUBLISHED = 'published';
    const STATUS_WAIT_ACCEPT = 'wait_accept';
    const STATUS_ADAPTED = 'adapted';
    const STATUS_CANCELED = 'canceled';
    const STATUS_DEALT = 'dealt';
    const STATUS_FINISHED = 'finished';
    const STATUS_FAILED = 'failed';
    const STATUS_ARBITRATED = 'arbitrated';
    const STATUS_REFUNDING = 'refunding';
    const STATUS_REFUSED = 'refused';

    const TABLE_ASSIGNMENTS = 'assignments';
    const TABLE_SERVICES = 'services';
    const TABLE_ACCEPTED_ASSIGNMENTS = 'accepted_assignments';
    const TABLE_ACCEPTED_SERVICES = 'accepted_services';


    public $fillable = [
        'operation',
        'table',
        'primary_key',
        'user_id',
        'origin_status',
        'final_status',
        'comment',
        'created_at',
        'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

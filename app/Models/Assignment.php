<?php
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/10/31
 * Time: 13:59
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $user_id
 * @property string $title
 * @property integer $classification
 * @property string $introduction
 * @property integer $province_id
 * @property integer $city_id
 * @property integer $area_id
 * @property number $lng
 * @property number $lat
 * @property string $detail_address
 * @property number $reward
 * @property string $expired_at
 * @property string $deadline
 * @property integer $status
 * @property string $comment
 * @property string $create_at
 * @property string $updated_at
 */
class Assignment extends Model
{
    //
    const STATUS_UNPAID = 0;                  //待支付
    const STATUS_WAIT_ACCEPT = 1;            //已支付，等待接受委托
    const STATUS_ADAPTED = 2;               //委托已经被接受
    const STATUS_CANCELED = 3;               //委托被发布人取消
    const STATUS_FINISHED = 4;                //委托逾期无人接受
    const STATUS_FAILED = 5;                 //委托失败

    public $fillable = [
        'title',
        'classification',
        'introduction',
        'province_id',
        'city_id',
        'area_id',
        'lng',
        'lat',
        'detail_address',
        'reward',
        'expired_at',
        'deadline',
        'comment',
        'public_user_id',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
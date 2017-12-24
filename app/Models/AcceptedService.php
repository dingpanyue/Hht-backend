<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $assign_user_id
 * @property integer $serve_user_id
 * @property integer $parent_id
 * @property number $reward
 * @property string $deadline
 * @property integer $status
 * @property string $comment
 * @property string $created_at
 * @property string $updated_at
 */
class AcceptedService extends Model
{
    const STATUS_SUBMITTED = 1;
    const STATUS_UNPAID = 2;
    const STATUS_ADAPTED = 3;
    const STATUS_DEALT = 4;
    const STATUS_FINISHED = 5;
    const STATUS_FAILED = 6;

    public $fillable = [
        'assign_user_id',
        'serve_user_id',
        'parent_id',
        'reward',
        'deadline',
        'status',
        'comment',
        'created_at',
        'updated_at'
    ];

    //创建者（委托或者服务）
    public function service()
    {
        return $this->belongsTo(Assignment::class,'parent_id');
    }

    //委托人
    public function assignUser()
    {
        return $this->belongsTo(User::class, 'assign_user_id');
    }

    //服务人
    public function serveUser()
    {
        return $this->belongsTo(User::class, 'serve_user_id');
    }

}

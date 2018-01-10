<?php
namespace App\Models;
/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/29
 * Time: 9:09
 */

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    const STATUS_SENT = 'sent';
    const STATUS_UNSENT = 'unsent';
    const STATUS_SEEN = 'seen';
    const STATUS_UNSEEN = 'unseen';

    protected $fillable = [
        'type',
        'message',
        'from_user_id',
        'to_user_id',
        'status'
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
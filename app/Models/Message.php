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

    protected $fillable = [
        'type',
        'message',
        'from_user_id',
        'to_user_id',
        'status'
    ];
}
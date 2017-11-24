<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/10/31
 * Time: 14:38
 */
class AcceptedAssignment extends Model
{

    public function assignment()
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'accept_user_id');
    }
}

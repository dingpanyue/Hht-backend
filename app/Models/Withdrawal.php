<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    const STATUS_PROCESSING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 2;

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
}

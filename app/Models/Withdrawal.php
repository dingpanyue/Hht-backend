<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    const STATUS_PROCESSING = 0;
    const STATUS_SUCCESS = 1;
}

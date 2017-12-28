<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $user_id
 * @property string $real_name
 * @property string $card_no
 * @property float $balance
 * @property integer $assign_points
 * @property integer $serve_points
 */
class UserInfo extends Model
{
    const STATUS_UNAUTHENTICATED = 'unauthenticated';
    const STATUS_AUTHENTICATED = 'authenticated';

    protected $table = 'user_info';

    protected $hidden = [
        'balance',
        'real_name',
        'card_no'
    ];

}

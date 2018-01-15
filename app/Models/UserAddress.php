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
class UserAddress extends Model
{
    protected $fillable = [
        'user_id',
        'province_id',
        'city_id',
        'area_id',
        'detail_address',
        'mobile',
        'receiver',
        'postcode',
        'is_default'
    ];
}

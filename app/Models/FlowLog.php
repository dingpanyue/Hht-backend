<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class FlowLog
 * @package App\Models
 * @property integer $id
 * @property integer $user_id
 * @property string $table
 * @property string $method
 * @property integer $primary_key
 * @property string $created_at
 * @property string $updated_at
 * @property float $amount
 */
class FlowLog extends Model
{
    protected $fillable = [
        'user_id',
        'table',
        'method',
        'primary_key',
        'amount'
    ];


}

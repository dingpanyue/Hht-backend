<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class OperationLogs
 * @package App\Models
 * @property integer $id
 * @property string $operation
 * @property string $table
 * @property integer $primary_key
 * @property integer $user_id
 * @property string $origin_status
 * @property string $final_status
 * @property string $comment
 * @property string $created_at
 * @property string $updated_at
 */
class OperationLog extends Model
{
    //
    public $fillable = [
        'operation',
        'table',
        'primary_key',
        'user_id',
        'origin_status',
        'final_status',
        'comment',
        'created_at',
        'updated_at'
    ];

}

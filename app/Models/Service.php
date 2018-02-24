<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $user_id
 * @property string $title
 * @property integer $classification
 * @property string $introduction
 * @property integer $province_id
 * @property integer $city_id
 * @property integer $area_id
 * @property number $lng
 * @property number $lat
 * @property string $detail_address
 * @property number $reward
 * @property string $expired_at
 * @property string $deadline
 * @property integer $status
 * @property string $comment
 * @property integer $adapted_assignment_id
 * @property string $create_at
 * @property string $updated_at
 * @property array $operations
 */
class Service extends Model
{
    //
    const STATUS_PUBLISHED = 0;
    const STATUS_CANCELED = 1;


    public $fillable = [
        'title',
        'classification',
        'introduction',
        'province_id',
        'city_id',
        'area_id',
        'lng',
        'lat',
        'detail_address',
        'reward',
        'expired_at',
        'deadline',
        'comment',
        'user_id',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    //待处理的acceptedAssignments
    public function AcceptedServicesCommitted()
    {
        return $this->hasMany(AcceptedService::class,'parent_id')->where('status', AcceptedService::STATUS_SUBMITTED)
            ->where('deadline', '>', date('Y-m-d H:i:s'));
    }

    public function classifications()
    {
        return $this->hasMany(Service::class,'service_id', 'id');
    }
}

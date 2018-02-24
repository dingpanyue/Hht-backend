<?php
namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'mobile'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function findForPassport($username)
    {
        return self::where('mobile', $username)->first();
    }

    public function userInfo()
    {
        return $this->hasOne(UserInfo::class, 'user_id');
    }

    public function balance()
    {

    }

    public function userAccount()
    {
        return $this->hasOne(UserAccount::class, 'user_id');
    }

    public function userCenter()
    {
        return $this->hasOne(UserCenter::class, 'user_id');
    }

    public function userTalents()
    {
        return $this->hasMany(UserTalent::class, 'user_id');
    }

    public function configs()
    {
        return $this->hasOne(UserConfig::class, 'user_id');
    }
}

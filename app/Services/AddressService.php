<?php
namespace App\Services;
use App\Models\Region;
use App\Models\UserAddress;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2018/1/10
 * Time: 23:27
 */

class AddressService
{
    protected $addressEloqument;

    protected $regionEloqument;

    public function __construct(UserAddress $address, Region $region)
    {
        $this->addressEloqument = $address;

        $this->regionEloqument = $region;
    }

    public function create($userId, $input)
    {
        $hasDefaultAddress = $this->addressEloqument->where('user_id', $userId)->where('is_default', 1)->count();

        if ($hasDefaultAddress) {
            $isDefault = 0;
        } else {
            $isDefault = 1;
        }

        $params = array_merge($input, [
            'user_id' => $userId,
            'is_default' => $isDefault
        ]);

        $address = $this->addressEloqument->create($params);

        if ($address) {
            return $address;
        } else {
            return false;
        }
    }

    public function getAddress(UserAddress $userAddress)
    {
        $province = $this->regionEloqument->find($userAddress->province_id)->Name;
        $city = $this->regionEloqument->find($userAddress->city_id)->Name;
        $area = $this->regionEloqument->find($userAddress->area_id)->Name;
        $address = $province.$city.$area.$userAddress->detail_address;
        return $address;
    }

}
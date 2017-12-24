<?php
namespace App\Services;
use App\Models\AcceptedService;

/**
 * Created by PhpStorm.
 * User: hasee
 * Date: 2017/12/10
 * Time: 8:13
 */

class AcceptedServiceService
{
    protected $acceptedServiceEloqument;

    public function __construct(AcceptedService $acceptedServiceEloqument)
    {
        $this->acceptedServiceEloqument = $acceptedServiceEloqument;
    }

    public function getAcceptedServiceById($id)
    {
        $acceptedAssignment = $this->acceptedServiceEloqument->find($id);
        return $acceptedAssignment;
    }


}
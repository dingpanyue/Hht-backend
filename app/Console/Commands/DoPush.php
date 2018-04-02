<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Services\GatewayWorkerService;
use App\Services\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class DoPush extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push {assignment_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $assignmentId = $this->argument('assignment_id');
        $assignment = Assignment::find($assignmentId);

        $lng = $assignment->lng;
        $lat = $assignment->lat;

        $keys = Redis::keys("*");
        foreach ($keys as $key) {
            $data = unserialize(Redis::get($key));
            $distance = Helper::getDistance($lng, $lat, $data['location'][0], $data['location'][1]);
            if ($distance <= 5) {
                $distance = sprintf("%.2f",$distance*100);
                GatewayWorkerService::sendSystemMessage("距您 $distance 米处有人发布了需求《$assignment->title 》,需求价格 $assignment->reward 元", $data['user_id']);
            }
        }

    }
}

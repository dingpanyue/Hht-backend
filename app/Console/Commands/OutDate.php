<?php

namespace App\Console\Commands;

use App\Models\AcceptedAssignment;
use App\Models\AcceptedService;
use Illuminate\Console\Command;

class OutDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outDate {type} {id}';

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
        //
        $type = $this->argument('type');
        $id = $this->argument('id');

        //委托过期(指到达deadline)
        if ($type == 'assign')
        {
            $acceptedAssignment = AcceptedAssignment::find($id);

            //委托在被采纳之后  双方都没有后续处理   则判定委托失败   信用等级降低   退款     双方推送
            if ($acceptedAssignment->status == AcceptedAssignment::STATUS_ADAPTED) {
                echo 1;
                return;

            }

            //委托在被确认完成之后，没有后续处理    则完成委托   信用等级增加   打款     双方推送
            if ($acceptedAssignment->status == AcceptedAssignment::STATUS_DEALT) {
                echo 2;
                return;

            }

            //委托已经是完成的状态  do nothing
            if ($acceptedAssignment->status == AcceptedAssignment::STATUS_FINISHED) {
                echo 3;
                return;
            }
            echo 4;
            return;
        }

        //服务过期(指到达deadline)
        if ($type == 'serve')
        {
            $acceptedService = AcceptedService::find($id);

            //服务在被购买之后  双方都没有后续处理   则判定服务失败   信用等级降低   退款     双方推送
            if ($acceptedService->status == AcceptedAssignment::STATUS_ADAPTED) {
                echo 1;
                return;
            }

            //服务在被确认完成之后，没有后续处理    则完成服务   信用等级增加   打款     双方推送
            if ($acceptedService->status == AcceptedAssignment::STATUS_DEALT) {
                echo 2;
                return;

            }

            //服务已经是完成的状态  do nothing
            if ($acceptedService->status == AcceptedAssignment::STATUS_FINISHED) {
                echo 3;
                return;
            }
            echo 4;
            return;

        }
    }
}

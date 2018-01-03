<?php

namespace App\Console\Commands;

use App\Models\TimedTask;
use Illuminate\Console\Command;

class Timer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '定时任务';


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

        $nowTime = date('Y-m-d H:i') . ':00';
        $this->info($nowTime);
        $timed_tasks = TimedTask::where('start_time', $nowTime)->get();

        foreach ($timed_tasks as $timed_task) {
            $timed_task->result = 1;
            $out = [];
            exec('php artisan '.$timed_task->command, $out);
            $timed_task->last_log = join("\n", $out);
            $timed_task->end_time = date('Y-m-d H:i:s');
            $timed_task->save();
        }

        return 0;
    }
}

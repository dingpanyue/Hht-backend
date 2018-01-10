<?php
namespace App\Providers;

use App\Models\Assignment;
use App\Models\AssignmentClassification;
use App\Models\GlobalConfig;
use App\Services\Helper;
use Illuminate\Support\ServiceProvider;

class GlobalConfigServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //全局变量， 为了方便存储在了数据库中
        $configs = cache('global_configs', null);

        if (!$configs) {
            $globalConfigs = GlobalConfig::all();
            $configs =  Helper::transformToKeyValue($globalConfigs, 'key', 'value');
            cache('global_configs', $configs);
        }

        $this->app->singleton('global_configs', function() use ($configs){
           return $configs;
        });

        //所有委托的分类
        $assignmentClassifications = cache('assignment_classifications', null);

        if (!$assignmentClassifications) {
            $assignmentClassifications = AssignmentClassification::all();
            cache('assignment_classifications', $assignmentClassifications);
        }

        $this->app->singleton('assignment_classifications', function() use ($assignmentClassifications){
            return $assignmentClassifications;
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
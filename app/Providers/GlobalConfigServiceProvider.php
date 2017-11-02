<?php

namespace App\Providers;


use App\Models\GlobalConfig;
use App\Services\Helper;
use Illuminate\Support\ServiceProvider;

class GlobalConfigServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
        $configs = cache('global_configs', null);
        if (!$configs) {
            $globalConfigs = GlobalConfig::all();
            $configs =  Helper::transformToKeyValue($globalConfigs, 'key', 'value');
            cache('global_configs', $configs);
        }
        $this->app->singleton('global_configs', function() use ($configs){
           return $configs;
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
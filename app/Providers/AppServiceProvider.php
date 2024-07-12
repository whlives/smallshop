<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //判断是否是https
        if (config('app.is_https')) {
            URL::forceScheme('https');
        }
        //验证金额
        Validator::extend('price', function ($attribute, $value, $parameters) {
            return preg_match("/(^[1-9]\d*(\.\d{1,2})?$)|(^0(\.\d{1,2})?$)/", $value);
        });
        //验证手机
        Validator::extend('mobile', function ($attribute, $value, $parameters) {
            return preg_match("/^1[3456789]{1}\d{9}$/", $value);
        });
        
        Route::pattern('id', '[0-9]+');
        Route::pattern('page', '[0-9]+');
        Route::pattern('limit', '[0-9]+');
        Route::pattern('category_id', '[0-9]+');
        Route::pattern('parent_id', '[0-9]+');
        Route::pattern('seller_id', '[0-9]+');
    }
}

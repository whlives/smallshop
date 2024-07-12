<?php

use App\Exceptions\ApiError;
use App\Services\LogService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Psr\Http\Client\RequestExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
    //web: __DIR__.'/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('admin')
                ->namespace('App\Http\Controllers\Admin')
                ->group(base_path('routes/admin.php'));
            Route::prefix('seller')
                ->namespace('App\Http\Controllers\Seller')
                ->group(base_path('routes/seller.php'));
            Route::prefix('v1')
                ->namespace('App\Http\Controllers\V1')
                ->group(base_path('routes/v1.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Exception $e) {
            $return = [
                'code' => '10000',
                'status_code' => '200',
                'msg' => ''
            ];
            if ($e instanceof ApiError) {
                //接口报错处理
                $error_info = $e->getMessage();
                if (strpos($error_info, '|')) {
                    $error_info = explode('|', $error_info);
                    $return['code'] = $error_info[0];
                    $return['msg'] = $error_info[1];
                } else {
                    $return['msg'] = $error_info;
                }
                return response()->json($return, 200, ['Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_UNICODE);
            }
            $statusCode = 500;
            if ($e instanceof HttpExceptionInterface) {
                $statusCode = $e->getStatusCode();
            } elseif ($e instanceof RequestExceptionInterface) {
                $statusCode = 400;
            }
            $debug = config('app.debug');
            if ($statusCode == 500 && $debug) {
                $return['status_code'] = '500';
                $return['msg'] = $e->getMessage();
                $return['file'] = $e->getFile() ?: '';
                $return['line'] = $e->getLine() ?: '';
                //在这里可以替换成写入到第三方的日志库
                LogService::putLog('500error', $return);
                return response()->json($return, 200, ['Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_UNICODE);
            }
            return false;
        });
    })->create();

<?php

namespace App\Exceptions;

use App\Services\LogService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        ApiError::class
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * 将异常记录到日志
     * @param Throwable $e
     * @return void
     * @throws Throwable
     */
    public function report(Throwable $e)
    {
        if (!$e instanceof ApiError) {
            /*$debug = config('app.debug');
            if ($e->getMessage() && !$debug) {
                $return = [
                    'msg' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'addtime' => get_date()
                ];
                //在这里可以替换成写入到第三方的日志库
                LogService::putLog('500error', $return);
            } else {
                parent::report($e);
            }*/
            parent::report($e);
        }
    }

    /**
     * 将异常转换为 HTTP 响应。
     * @param $request
     * @param Throwable $e
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response|void
     */
    public function render($request, Throwable $e)
    {
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
        //接管500错误
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
            return response()->json($return, 200, ['Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_UNICODE);
        }
        return parent::render($request, $e);
    }
}

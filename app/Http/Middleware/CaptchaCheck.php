<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 3:22 PM
 */

namespace App\Http\Middleware;

use App\Libs\Sms;
use Closure;

/**
 * api接口验证短信验证码
 */
class CaptchaCheck
{
    /**
     * api接口验证短信验证码
     * @param $request
     * @param Closure $next
     * @return mixed|void
     * @throws \App\Exceptions\ApiError
     */
    public function handle($request, Closure $next)
    {
        $code = (int)$request->post('code');
        $mobile = $request->post('mobile');
        $sms = new Sms();
        $res = $sms->checkCaptcha($mobile, $code);
        if ($res === true) {
            return $next($request);
        } else {
            api_error($res);
        }
    }
}
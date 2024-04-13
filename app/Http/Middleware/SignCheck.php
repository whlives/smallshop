<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 4:10 PM
 */

namespace App\Http\Middleware;

use App\Services\SignService;
use Closure;

class SignCheck
{
    /**
     * 验证签名
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function handle($request, Closure $next)
    {
        $post_data = $request->post();
        SignService::checkSign($post_data);//验证签名
        return $next($request);
    }
}

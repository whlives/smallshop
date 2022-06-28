<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;

class SellerToken
{
    /**
     * 商家token验证
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws ApiError
     */
    public function handle(Request $request, Closure $next)
    {
        $tokenService = new TokenService();
        $tokenService->setTime(3600 * 2);
        $token = $tokenService->getToken();
        if (!$token) {
            api_error(__('admin.invalid_token'));
        } elseif (!isset($token['type']) || $token['type'] != 'seller') {
            api_error(__('admin.invalid_token'));
        } elseif ($token['user_agent'] != request()->userAgent()) {
            //验证设备
            $tokenService->delToken();
            api_error(__('admin.invalid_token'));
        }
        return $next($request);
    }
}

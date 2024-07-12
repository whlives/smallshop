<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/11
 * Time: 4:30 PM
 */

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Services\TokenService;
use Closure;

class ApiToken
{
    /**
     * 验证token
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws ApiError
     */
    public function handle($request, Closure $next)
    {
        $token_service = new TokenService();
        $token_data = $token_service->getToken();
        if (!$token_data || !$token_data['id']) {
            api_error(__('api.invalid_token'));
        } elseif (!isset($token_data['type']) || $token_data['type'] != 'api') {
            api_error(__('admin.invalid_token'));
        }
        $this->checkDevice($token_data);
        return $next($request);
    }

    /**
     * 验证设备是否异常
     * @param array $token_data
     * @return void
     * @throws ApiError
     */
    public function checkDevice(array $token_data)
    {
        $device = get_device();
        $platform = get_platform();
        if ($token_data['device'] != $device) {
            api_error(__('api.invalid_device'));
        }
        if ($token_data['platform'] != $platform) {
            api_error(__('api.invalid_platform'));
        }
    }
}

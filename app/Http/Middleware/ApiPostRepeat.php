<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\Admin\AdminLog;
use App\Models\Admin\AdminRole;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ApiPostRepeat
{
    /**
     * 数据参数重复请求过滤
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws ApiError
     */
    public function handle(Request $request, Closure $next)
    {
        $post_data = $request->post();
        $md5 = md5(json_encode($post_data));
        $redis_key = 'api_post_repeat:' . $md5;
        $is_repeat = Redis::get($redis_key);
        if ($is_repeat) {
            api_error(__('api.request_frequent'));
        }
        Redis::setex($redis_key, 3, 1);
        return $next($request);
    }

}

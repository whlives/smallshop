<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use App\Models\Admin\AdminLog;
use App\Models\Admin\AdminRole;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;

class AdminToken
{
    /**
     * 后台token验证
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
        } elseif (!isset($token['type']) || $token['type'] != 'admin') {
            api_error(__('admin.invalid_token'));
        } elseif ($token['user_agent'] != request()->userAgent()) {
            //验证设备
            $tokenService->delToken();
            api_error(__('admin.invalid_token'));
        }
        $this->checkRole($token);//验证用户权限
        $this->log($request, $token);//记录操作日志
        return $next($request);
    }

    /**
     * 权限验证
     * @param array $token token信息
     * @return bool|void
     * @throws ApiError
     */
    public function checkRole(array $token)
    {
        if ($token['role_id'] == 1) return true;
        $role_right = AdminRole::adminRight($token['role_id']);
        $url_path = request()->path();
        if (in_array($url_path, $role_right['right'])) {
            return true;
        } else {
            api_error(__('admin.role_error'));
        }
    }

    /**
     * 记录操作日志
     * @param $request
     * @param array $token
     * @return void
     */
    public function log($request, array $token)
    {
        $admin_log_type = get_custom_config('admin_log_type');
        if (!$admin_log_type) return true;
        $admin_id = $token['id'];
        $username = $token['username'];
        $url = $request->url();
        $post_data = $request->all();
        unset($post_data['token']);
        unset($post_data['limit']);
        unset($post_data['page']);
        if (count($post_data) < 2) return false;//普通查询的默认不记录
        $content = json_encode($post_data, JSON_UNESCAPED_UNICODE);
        $ip = $request->getClientIp();
        if ($admin_log_type == AdminLog::LOG_TYPE_MYSQL) {
            $log = [
                'admin_id' => $admin_id,
                'username' => $username,
                'url' => $url,
                'ip' => $ip,
                'content' => $content
            ];
            AdminLog::query()->create($log);
        } elseif ($admin_log_type == AdminLog::LOG_TYPE_FILE) {
            try {
                //每个小时生成一个文件
                $dir = storage_path() . '/logs/admin_log/' . date('Ym/d');
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }
                $file_name = $dir . '/' . date('H') . '.log';
                $text = $admin_id . '||' . $username . '||' . get_date() . '||' . $ip . '||' . $url . '||' . $content;
                file_put_contents($file_name, $text . PHP_EOL, FILE_APPEND);
            } catch (\Exception $e) {

            }
        }
    }
}

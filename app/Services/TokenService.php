<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/1/7
 * Time: 3:41 PM
 */

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TokenService
{
    private int $expire_time = 3600 * 24 * 31;
    private string $token_prefix = 'api_token:';

    /**
     * 设置过期时间
     * @param $expire_time
     * @return void
     */
    public function setTime($expire_time)
    {
        $this->expire_time = $expire_time;
    }

    /**
     * 获取过期时间
     * @return float|int
     */
    public function getTime()
    {
        return $this->expire_time;
    }

    /**
     * 设置token
     * @param array $data 用户数据
     * @return string
     */
    public function setToken(array $data): string
    {
        if (!$data['id']) return false;
        $str = Str::random(20) . $data['id'] . time();
        $token_name = md5($str);
        $redis_key = $this->redisKey($token_name);
        Redis::setex($redis_key, $this->expire_time, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $token_name;
    }

    /**
     * 获取token数据
     * @param string $token_name
     * @return mixed
     */
    public function getToken(string $token_name = ''): mixed
    {
        $redis_key = $this->redisKey($token_name);
        $token_data = Redis::get($redis_key);
        if ($token_data) {
            $token_data = json_decode($token_data, true);
        }
        return $token_data;
    }

    /**
     * 刷新token
     * @param string $token_name
     * @return bool
     */
    public function refreshToken(string $token_name = ''): bool
    {
        $redis_key = $this->redisKey($token_name);
        Redis::expire($redis_key, $this->expire_time);
        return true;
    }

    /**
     * 删除token
     * @param string $token_name
     * @return bool
     */
    public function delToken(string $token_name = ''): bool
    {
        $redis_key = $this->redisKey($token_name);
        Redis::del($redis_key);
        return true;
    }

    /**
     * 获取token存储key
     * @param string $token_name
     * @return string
     */
    public function redisKey(string $token_name = ''): string
    {
        if (!$token_name) {
            $token_name = $this->getTokenName();
        }
        return $this->token_prefix . $token_name;
    }

    /**
     * 获取token名称
     * @return string|null
     */
    public function getTokenName(): string|null
    {
        $token_name = request()->input('token');
        if (!$token_name) {
            $token_name = request()->cookie('token');
            if (!$token_name) {
                $token_name = request()->header('Authorization');
                if (!$token_name) {
                    $token_name = request()->header('token');
                }
            }
        }
        return $token_name;
    }
}

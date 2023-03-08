<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/7/26
 * Time: 14:21 PM
 */

namespace App\Libs\Weixin;

use Illuminate\Support\Facades\Cache;

class AccessToken implements \EasyWeChat\Kernel\Contracts\AccessToken
{
    public array $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 获取access_tokenAccessTokenInterface
     * @param array $config
     * @return mixed|null
     */
    public function getToken(): string
    {
        $cache_key = get_cache_key('access_token', $this->config['app_id']);
        $access_token = Cache::get($cache_key);
        if (!$access_token) {
            $access_token = self::refreshAccessToken($this->config);
        }
        return $access_token;
    }

    public function toQuery(): array
    {
        return ['access_token' => $this->getToken()];
    }

    /**
     * 获取最新access_token
     * @param array $config
     * @return mixed|void
     */
    public static function refreshAccessToken(array $config)
    {
        $cache_key = get_cache_key('access_token', $config['app_id']);
        $url = 'https://api.weixin.qq.com/cgi-bin/token';
        $data = [
            'grant_type' => 'client_credential',
            'appid' => $config['app_id'],
            'secret' => $config['secret']
        ];
        $res = curl($url, $data);
        $token_data = json_decode($res, true);
        if (isset($token_data['access_token'])) {
            $access_token = $token_data['access_token'];
            Cache::put($cache_key, $access_token, ($token_data['expires_in'] - 900));
            return $access_token;
        } else {
            api_error($token_data['errmsg']);
        }
    }

}
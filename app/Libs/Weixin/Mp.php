<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 10:21 AM
 */

namespace App\Libs\Weixin;

use App\Models\System\Payment;
use EasyWeChat\OfficialAccount\Application;

class Mp
{
    private $app;
    public array $custom_config;

    function __construct()
    {
        $platform = get_platform();
        if (in_array($platform, [Payment::CLIENT_TYPE_IOS, Payment::CLIENT_TYPE_ANDROID])) {
            $type = 'app';//app等使用开放平台
        } else {
            $type = 'mp';
        }
        $this->custom_config = get_custom_config_all();
        $config = [
            'app_id' => $this->custom_config[$type . '_appid'],
            'secret' => $this->custom_config[$type . '_secret'],
            'token' => '',
            'aes_key' => ''
        ];
        $this->app = new Application($config);
    }

    /**
     * 获取微信用户授权信息
     * @param string $code
     * @return false|mixed|void
     * @throws \App\Exceptions\ApiError
     */
    public function userInfo(string $code)
    {
        try {
            if (!$code) return false;
            $oauth = $this->app->getOauth();
            $user = $oauth->userFromCode($code);
            $user = $user->toArray();
            return $user['raw'];
        } catch (\Exception $e) {
            api_error($e->getMessage());
        }
    }

    /**
     * 获取jssdk
     * @param string $url
     * @return mixed[]|void
     * @throws \App\Exceptions\ApiError
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function jsSdk(string $url)
    {
        try {
            $utils = $this->app->getUtils();
            return $utils->buildJsSdkConfig(
                url: $url,
                jsApiList: [],
                openTagList: [],
                debug: false,
            );
        } catch (\Exception $e) {
            api_error($e->getMessage());
        }
    }
}
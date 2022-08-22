<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 10:21 AM
 */

namespace App\Libs\Weixin;

use App\Libs\Aliyun\Oss;
use App\Libs\Upload;
use EasyWeChat\MiniApp\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MiniProgram
{
    private $app;
    public array $custom_config;

    function __construct()
    {
        $this->custom_config = get_custom_config_all();
        $config = [
            'app_id' => $this->custom_config['mini_appid'],
            'secret' => $this->custom_config['mini_secret'],
        ];
        $this->app = new Application($config);
    }

    /**
     * 获取session_key
     * @param string $code
     * @return array|false|mixed|void
     * @throws \App\Exceptions\ApiError
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function sessionKey(string $code)
    {
        try {
            $cache_key = 'weixin_session_key:' . $code;
            $session_data = Cache::get($cache_key);
            if (!$session_data) {
                $utils = $this->app->getUtils();
                $result = $utils->codeToSession($code);
                if (isset($result['session_key'])) {
                    $session_data = $result;
                    Cache::put($cache_key, $session_data, $this->custom_config['cache_time']);
                } else {
                    return false;
                }
            }
            return $session_data;
        } catch (\Exception $e) {
            api_error($e->getMessage());
        }
    }

    /**
     * 获取手机号
     * @param string $code
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\BadResponseException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getPhoneNumber(string $code)
    {
        $data = [
            'code' => $code
        ];
        $response = $this->app->getClient()->postJson('wxa/business/getuserphonenumber', $data);
        $res = $response->toArray(false);
        if (isset($res['phone_info'])) {
            return $res['phone_info'];
        } else {
            return $res['errmsg'];
        }
    }

    /**
     * 获取解密后的信息
     * @param string $code
     * @param string $iv
     * @param string $encrypt_data
     * @return array|void
     * @throws \App\Exceptions\ApiError
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function decryptData(string $code, string $iv, string $encrypt_data)
    {
        try {
            $session_data = self::sessionKey($code);
            if ($session_data) {
                $utils = $this->app->getUtils();
                $decrypted_data = $utils->decryptSession($session_data['session_key'], $iv, $encrypt_data);
                if ($decrypted_data) {
                    $decrypted_data['openid'] = $session_data['openid'];
                    $decrypted_data['unionid'] = $session_data['unionid'] ?? '';
                }
                return $decrypted_data;
            }
        } catch (\Exception $e) {
            api_error($e->getMessage());
        }
    }

    /**
     * 生成小程序码
     * @param array $param 参数
     * @param string $page_path 路径
     * @param int $width
     * @return bool|string
     */
    public function createQrcode(array $param, string $page_path, int $width = 400)
    {
        try {
            $scene = http_build_query($param);
            $response = $this->app->getClient()->postJson('/wxa/getwxacodeunlimit', [
                'scene' => $scene,
                'page' => $page_path,
                'width' => $width,
                'check_path' => !config('app.debug'),//测试环境不校验page
            ]);
            $upload = new Upload();
            $dir = $upload->getQrcodeDir($scene);//获取存储地址
            $url = $dir . md5($scene) . '.jpg';
            $content = $response->getContent(true);
            Storage::put($url, $content);
            $upload_type = $this->custom_config['upload_type'];
            if ($upload_type == 1) {
                //上传到阿里云
                $tmp_file_name = storage_path('app') . '/' . $url;
                $oss = new Oss();
                $oss_url = $oss->uploadOss($url, $tmp_file_name);
                unlink($tmp_file_name);
                return $oss_url;
            } else {
                return $this->custom_config['img_domain'] . '/' . $url;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 生成商品小程序码
     * @param int $goods_id
     * @return bool|string
     */
    public function createGoodsQrcode(int $goods_id)
    {
        return self::createQrcode(['g_id' => $goods_id], 'pages/shop/goodsDetail');
    }
}
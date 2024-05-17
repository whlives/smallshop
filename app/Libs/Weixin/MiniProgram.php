<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/12
 * Time: 10:21 AM
 */

namespace App\Libs\Weixin;

use App\Exceptions\ApiError;
use App\Libs\Aliyun\Oss;
use App\Libs\Upload;
use App\Models\Financial\Trade;
use App\Models\System\ExpressCompany;
use App\Models\System\Payment;
use EasyWeChat\Kernel\Exceptions\BadResponseException;
use EasyWeChat\MiniApp\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class MiniProgram
{
    private Application $app;
    public array $custom_config;
    private array $config;

    function __construct()
    {
        $this->custom_config = get_custom_config_all();
        $this->config = [
            'app_id' => $this->custom_config['mini_appid'],
            'secret' => $this->custom_config['mini_secret'],
        ];
        $this->app = new Application($this->config);
        //使用自定义的access_token
        $access_token = new AccessToken($this->config);
        $this->app->setAccessToken($access_token);
    }

    /**
     * 获取session_key
     * @param string $code
     * @return array|false|mixed|void
     * @throws ApiError
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
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
                    return '';
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
     * @throws BadResponseException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getPhoneNumber(string $code)
    {
        $data = [
            'code' => $code
        ];
        $response = $this->app->getClient()->postJson('wxa/business/getuserphonenumber', $data);
        $res = $response->toArray(false);
        if (isset($res['errcode']) && $res['errcode'] == 40001) {
            AccessToken::refreshAccessToken($this->config);//刷新access_token
        }
        return $res['phone_info'] ?? $res['errmsg'];
    }

    /**
     * 获取解密后的信息
     * @param string $code
     * @param string $iv
     * @param string $encrypt_data
     * @return array|void
     * @throws ApiError
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
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
            if ($response->isFailed()) {
                //判断是否是access_token异常并刷新
                $error = $response->toArray(false);
                if ($error['errcode'] == 40001) {
                    AccessToken::refreshAccessToken($this->config);//刷新access_token
                }
                return false;
            }
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

    /**
     * 生成分享邀请小程序码
     * @param array $param
     * @return bool|string
     */
    public function createShareQrcode(array $param)
    {
        return self::createQrcode($param, 'pages/homepage/goods');
    }

    /**
     * 发货信息录入
     * @param array $order
     * @param array $express_company
     * @param string|null $delivery_code
     * @return mixed|true
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function uploadShippingInfo(array $order, array $express_company, string|null $delivery_code = '')
    {
        try {
            $trade_data = Trade::query()->where('id', $order['trade_id'])->first();
            //没有发货的微信订单才处理
            if ($trade_data['send_at'] || $trade_data['payment_id'] != Payment::PAYMENT_WECHAT) return true;
            $data = [
                'order_key' => [
                    'order_number_type' => 2,
                    'transaction_id' => $order['payment_no'],
                ],
                'logistics_type' => $express_company['type'],
                'delivery_mode' => 'UNIFIED_DELIVERY',
                'upload_time' => date('c', time()),
            ];
            if ($express_company['type'] == ExpressCompany::TYPE_EXPRESS) {
                $shipping_list = [
                    'tracking_no' => $delivery_code,
                    'express_company' => $express_company['weixin_code'],
                    'item_desc' => '您购买的订单' . $order['order_no'],
                    'contact' => [
                        'receiver_contact' => $order['tel'] ? mb_substr($order['tel'], 0, 3) . '****' . substr($order['tel'], -4, 4) : '133****1234',
                    ]
                ];
            } else {
                $shipping_list = [
                    'item_desc' => '您购买的订单' . $order['order_no'],
                ];
            }
            $data['shipping_list'] = [$shipping_list];
            $data['payer']['openid'] = $trade_data['payment_user'];
            $response = $this->app->getClient()->postJson('wxa/sec/order/upload_shipping_info', $data);
            $res = $response->toArray(false);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                Trade::query()->where('id', $order['trade_id'])->update(['send_at' => get_date()]);
                return true;
            } else {
                if ($res['errcode'] == 40001) {
                    AccessToken::refreshAccessToken($this->config);//刷新access_token
                }
                return $res['errmsg'];
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 查询发货信息
     * @param array $order
     * @return mixed|true
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getShippingInfo(array $order)
    {
        try {
            $data = [
                'transaction_id' => $order['payment_no'],
            ];
            $response = $this->app->getClient()->postJson('wxa/sec/order/get_order', $data);
            $res = $response->toArray(false);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return $res['order'];
            } else {
                if ($res['errcode'] == 40001) {
                    AccessToken::refreshAccessToken($this->config);//刷新access_token
                }
                return $res['errmsg'];
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 确认发货提醒
     * @param array $order
     * @return mixed|true
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function confirmShippingInfo(array $order)
    {
        $trade_data = Trade::query()->where('id', $order['trade_id'])->first();
        if (!$trade_data['send_at']) return true;
        $res = self::getShippingInfo($order);
        if (!is_array($res)) return false;
        if (isset($res['order_state']) && $res['order_state'] != 2) return false;
        if (isset($res['shipping']) && $res['shipping']['delivery_mode'] != ExpressCompany::TYPE_EXPRESS) return false;
        try {
            $data = [
                'transaction_id' => $order['payment_no'],
            ];
            $response = $this->app->getClient()->postJson('wxa/sec/order/notify_confirm_receive', $data);
            $res = $response->toArray(false);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                if ($res['errcode'] == 40001) {
                    AccessToken::refreshAccessToken($this->config);//刷新access_token
                }
                return $res['errmsg'];
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 查询快递公司
     * @return array|false|mixed
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getExpress()
    {
        try {
            $data = [
                'errcode' => 0
            ];
            $response = $this->app->getClient()->postJson('cgi-bin/express/delivery/open_msg/get_delivery_list', $data);
            $res = $response->toArray(false);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return $res;
            } else {
                if ($res['errcode'] == 40001) {
                    AccessToken::refreshAccessToken($this->config);//刷新access_token
                }
                return $res['errmsg'];
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}

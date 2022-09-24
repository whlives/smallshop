<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/14
 * Time: 3:47 PM
 */

namespace App\Libs\Payment;

use App\Models\System\Payment;
use App\Services\LogService;
use App\Services\TokenService;
use App\Services\TradeService;
use EasyWeChat\Pay\Application;

/**
 * 微信支付
 */
class Wechat
{
    public string $platform = '';
    public string $type = '';
    public string $appid = '';
    public array $config = [];
    public string $notify_url = '';

    public function __construct($platform)
    {
        $custom_config = get_custom_config_all();
        $this->platform = $platform;
        if (in_array($platform, [Payment::CLIENT_TYPE_ANDROID, Payment::CLIENT_TYPE_IOS])) {
            $this->type = 'app';//app等使用开放平台
        } elseif ($platform == 'wechat') {
            $this->type = 'mini';
        } else {
            $this->type = 'mp';
        }
        $this->notify_url = url('/v1/out_push/pay_notify/' . Payment::PAYMENT_WECHAT . '/' . $this->platform);
        $this->appid = $custom_config[$this->type . '_appid'];
        $this->config = [
            'mch_id' => $custom_config[$this->type . '_mch_id'],
            'private_key' => $custom_config[$this->type . '_key_path'],
            'certificate' => $custom_config[$this->type . '_cert_path'],
            'secret_key' => $custom_config[$this->type . '_api_key'],//v3 API秘钥
            'v2_secret_key' => $custom_config[$this->type . '_api_key'],//v2 API秘钥
        ];
        $this->app = new Application($this->config);
    }

    /**
     * 获取支付信息
     * @param array $pay_info
     * @return array|mixed|mixed[]
     * @throws \App\Exceptions\ApiError
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function getPayData(array $pay_info)
    {
        $post_data = [
            'mchid' => $this->config['mch_id'],
            'out_trade_no' => $pay_info['trade_no'],
            'appid' => $this->appid,
            'description' => $pay_info['title'],
            'notify_url' => $this->notify_url,
            'amount' => [
                'total' => intval($pay_info['subtotal'] * 100),
                'currency' => "CNY"
            ],
        ];
        if (in_array($this->platform, ['mp', 'wechat'])) {
            //获取openid，在登陆的时候已经存到token
            $token_service = new TokenService();
            $token_data = $token_service->getToken();
            if (!$token_data || !$token_data['openid']) {
                api_error(__('api.payment_openid_error'));
            }
            $trade_type = 'jsapi';
            $post_data['payer']['openid'] = $token_data['openid'];
        } elseif ($this->platform == 'web') {
            $trade_type = 'native';
        } elseif ($this->platform == 'h5') {
            $trade_type = 'h5';
        } else {
            $trade_type = 'app';
        }
        $response = $this->app->getClient()->postJson('v3/pay/transactions/' . $trade_type, $post_data);
        $res = $response->toArray(false);
        if (is_array($res) && isset($res['prepay_id'])) {
            $return_data = [];
            $utils = $this->app->getUtils();
            switch ($this->platform) {
                case 'mp':
                    $return_data = $utils->buildBridgeConfig($res['prepay_id'], $this->appid, 'RSA');
                    break;
                case 'wechat':
                    $return_data = $utils->buildMiniAppConfig($res['prepay_id'], $this->appid, 'RSA');
                    break;
                case 'web':
                    $return_data['code_url'] = $res['code_url'];
                    break;
                case 'h5':
                    $return_data['h5_url'] = $res['h5_url'];
                    break;
                default:
                    $return_data = $utils->buildAppConfig($res['prepay_id'], $this->appid);
                    break;
            }
            return $return_data;
        } else {
            return $res['message'];
        }
    }

    /**
     * 退款申请
     * @param array $refund_info
     * @return bool|mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function refund(array $refund_info)
    {
        $post_data = [
            'out_trade_no' => $refund_info['trade_no'],
            'out_refund_no' => $refund_info['refund_no'],
            'amount' => [
                'refund' => intval($refund_info['amount'] * 100)
            ]
        ];
        $response = $this->app->getClient()->postJson('v3/refund/domestic/refunds', $post_data);
        $res = $response->toArray(false);
        if (isset($res['refund_id']) && $res['refund_id']) {
            return true;
        } else {
            return $res['message'];
        }
    }

    /**
     * 支付回调
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function notify()
    {
        $server = $this->app->getServer();
        $server->handlePaid(function ($message) {
            LogService::putLog('wechat_alipay', $message);//记录回调日志
            $transaction_id = $message['transaction_id'];
            $post_data = [
                'query' => [
                    'mchid' => $message['mchid'],
                ]
            ];
            $response = $this->app->getClient()->get('v3/pay/transactions/id/' . $transaction_id, $post_data);
            $res = $response->toArray(false);
            if (isset($res['trade_state']) && $res['trade_state'] == 'SUCCESS') {
                $return = [
                    'trade_no' => $message['out_trade_no'],
                    'pay_total' => format_price(round($message['amount']['total'] / 100, 2)),
                    'payment_no' => $message['transaction_id'],
                    'payment_id' => Payment::PAYMENT_WECHAT
                ];
                $res = TradeService::updatePayStatus($return);
                if ($res) {
                    return true;
                } else {
                    return '支付失败';
                }
            } else {
                return $res['message'];
            }
        });
        return $server->serve();
    }

    /**
     * 支付成功
     * @return string
     */
    public function success()
    {
        return 'success';
    }

    /**
     * 支付失败
     * @return string
     */
    public function fail()
    {
        return 'fail';
    }
}
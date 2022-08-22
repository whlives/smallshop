<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/14
 * Time: 3:47 PM
 */

namespace App\Libs\Payment;

use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;
use App\Models\System\Payment;
use App\Services\LogService;

/**
 * 支付宝支付
 */
class Alipay
{
    public string $platform = '';

    public function __construct($platform)
    {
        $custom_config = get_custom_config_all();
        $this->platform = $platform;
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';
        $options->appId = $custom_config['alipay_appid'];
        // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
        $options->merchantPrivateKey = $custom_config['alipay_private_key'];
        //$options->alipayCertPath = '<-- 请填写您的支付宝公钥证书文件路径 -->';
        //$options->alipayRootCertPath = '<-- 请填写您的支付宝根证书文件路径 -->';
        //$options->merchantCertPath = '<-- 请填写您的应用公钥证书文件路径 -->';
        //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
        $options->alipayPublicKey = $custom_config['alipay_public_key'];
        //可设置异步通知接收服务地址（可选）
        $options->notifyUrl = url('/v1/out_push/pay_notify/' . Payment::PAYMENT_ALIPAY . '/' . $this->platform);
        //可设置AES密钥，调用AES加解密相关接口时需要（可选）
        //$options->encryptKey = "<-- 请填写您的AES密钥 -->";
        Factory::setOptions($options);
    }

    /**
     * 获取支付信息
     * @param array $pay_info
     * @return array|string|void
     * @throws \App\Exceptions\ApiError
     */
    public function getPayData(array $pay_info)
    {
        try {
            if ($this->platform == Payment::CLIENT_TYPE_H5) {
                //h5支付
                $res = Factory::payment()->wap()->pay($pay_info['title'], $pay_info['trade_no'], $pay_info['subtotal'], $pay_info['quit_url'], $pay_info['return_url']);
            } elseif ($this->platform == Payment::CLIENT_TYPE_WEB) {
                //web支付
                $res = Factory::payment()->page()->pay($pay_info['title'], $pay_info['trade_no'], $pay_info['subtotal'], $pay_info['return_url']);
            } else {
                //app支付
                $res = Factory::payment()->app()->pay($pay_info['title'], $pay_info['trade_no'], $pay_info['subtotal']);
            }
            $responseChecker = new ResponseChecker();
            if ($responseChecker->success($res)) {
                return ['alipay' => $res->body];
            } else {
                return '支付请求失败' . $res->msg . $res->subMsg;
            }
        } catch (\Exception $e) {
            return '支付请求失败' . $e->getMessage();
        }
    }

    /**
     * 退款申请
     * @param array $refund_info
     * @return bool|string
     */
    public function refund(array $refund_info)
    {
        try {
            $res = Factory::payment()->common()->refund($refund_info['trade_no'], $refund_info['amount']);
            $responseChecker = new ResponseChecker();
            if ($responseChecker->success($res)) {
                return true;
            } else {
                return '退款失败' . $res->msg . $res->subMsg;
            }
        } catch (\Exception $e) {
            return '退款失败' . $e->getMessage();
        }
    }

    /**
     * 支付回调
     * @return array|false|void
     */
    public function notify()
    {
        try {
            $post_data = request()->post();
            LogService::putLog('pay_alipay', $post_data);//记录回调日志
            $check = Factory::payment()->common()->verifyNotify($post_data);
            if ($check) {
                $res = Factory::payment()->common()->query($post_data['out_trade_no']);
                $responseChecker = new ResponseChecker();
                if ($responseChecker->success($res)) {
                    return [
                        'trade_no' => $post_data['out_trade_no'],
                        'pay_total' => format_price($post_data['total_amount']),
                        'payment_no' => $post_data['trade_no'],
                        'payment_id' => Payment::PAYMENT_ALIPAY
                    ];
                } else {
                    return false;
                }
            }
        } catch (\Exception $e) {
            return false;
        }
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
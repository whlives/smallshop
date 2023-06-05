<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/16
 * Time: 3:23 PM
 */

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LogService
{

    /**
     * 记录日志
     * @param string $type 日志类型
     * @param array|string $data 数据
     * @return false|void
     */
    public static function putLog(string $type, array|string $data)
    {
        //日志类型pay_alipay支付宝支付，pay_wechat微信支付，delivery_subscribe物流订阅，delivery_notify物流推送，delivery_e_order电子面单
        $type_class = [
            '500error', 'crontab', 'pay_alipay', 'pay_wechat', 'delivery_subscribe', 'delivery_notify', 'delivery_e_order'
        ];
        if (!in_array($type, $type_class)) {
            return false;
        }
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        //如果是其他记录日志的方法在这里改写
        Log::build([
            'driver' => 'daily',
            'path' => storage_path('logs/' . $type . '/' . $type . '.log'),
        ])->info($data);
    }
}

<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/13
 * Time: 10:49 PM
 */

namespace App\Http\Controllers\V1;

use App\Models\Financial\Trade;
use App\Models\System\Payment;
use App\Services\TradeService;
use Illuminate\Http\Request;

class PayController extends BaseController
{

    /**
     * 获取支付方式
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function payment(Request $request)
    {
        $type = (int)$request->post('type', Trade::TYPE_ORDER);
        $payment = Payment::getPayment($type);
        return $this->success($payment);
    }

    /**
     * 获取支付信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\ApiError
     */
    public function payData(Request $request)
    {
        $m_id = $this->getUserId();
        $type = (int)$request->post('type', Trade::TYPE_ORDER);
        $order_no = $request->post('order_no');
        $payment_id = (int)$request->post('payment_id');
        $return_url = $request->post('return_url');//支付宝网页支付的时候需要
        $quit_url = $request->post('quit_url');//支付宝H5支付的时候需要
        if (!$type || !isset(Trade::TYPE_DESC[$type]) || !$order_no || !$payment_id) {
            api_error(__('api.missing_params'));
        }
        $platform = get_platform();
        //支付宝网页支付必须设置回调地址
        if ($payment_id == Payment::PAYMENT_ALIPAY && in_array($platform, [Payment::CLIENT_TYPE_WEB, Payment::CLIENT_TYPE_H5]) && !$return_url) {
            api_error(__('api.payment_alipay_return_url_error'));
        }
        //支付宝H5支付必须设置中途退出返回商户网站的地址
        if ($payment_id == Payment::PAYMENT_ALIPAY && $platform == Payment::CLIENT_TYPE_H5 && !$quit_url) {
            api_error(__('api.payment_alipay_quit_url_error'));
        }
        //余额充值不支持余额支付
        if ($type == Trade::TYPE_RECHARGE && $payment_id == Payment::PAYMENT_BALANCE) {
            api_error(__('api.recharge_not_balance_pay'));
        }
        //验证支付方式是否存在
        $payment = Payment::find($payment_id);
        if (!$payment || $payment['status'] != Payment::STATUS_ON || !in_array($platform, explode(',', $payment['client_type']))) {
            api_error(__('api.payment_error'));
        }
        $pay_info = TradeService::getPayData($m_id, $type, $order_no);
        $pay_info['return_url'] = $return_url;//支付宝网页支付的时候需要
        $pay_info['quit_url'] = $quit_url;//支付宝H5支付的时候需要
        $pay_data = [];
        if ($pay_info['subtotal'] > 0) {
            //请求支付信息
            $class_name = '\App\Libs\Payment\\' . $payment['class_name'];
            $pay = new $class_name($platform);
            $pay_data = $pay->getPayData($pay_info);
            if ($pay_data && is_array($pay_data)) {
                if (!isset($pay_data['is_pay'])) {
                    $pay_data['is_pay'] = 1;
                }
            } else {
                api_error($pay_data);
            }
        } else {
            $pay_data['payment_id'] = 0;
            $pay_data['payment_no'] = '';
            $pay_data['pay_total'] = 0;
            $pay_data['is_pay'] = 0;
        }
        $pay_data['trade_no'] = $pay_info['trade_no'];
        //不需要支付的修改支付状态
        if ($pay_data['is_pay'] == 0) {
            $pay_status_res = TradeService::updatePayStatus($pay_data);
            if (!$pay_status_res) {
                api_error(__('api.fail'));
            }
        }
        return $this->success($pay_data);
    }

    /**
     * 查询交易单是否支付成功
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \App\Exceptions\ApiError
     */
    public function tradeStatus(Request $request)
    {
        $trade_no = $request->post('trade_no');
        if (!$trade_no) {
            api_error(__('api.missing_params'));
        }
        $trade = Trade::where('trade_no', $trade_no)->first();
        if ($trade && $trade['status'] == Trade::STATUS_ON) {
            return $this->success();
        } else {
            api_error(__('api.trade_not_pay'));
        }
    }
}
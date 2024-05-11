<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/29
 * Time: 4:23 PM
 */

namespace App\Services;

use App\Models\Financial\BalanceRecharge;
use App\Models\Financial\Trade;
use App\Models\Financial\TradeRefund;
use App\Models\Order\Order;
use App\Models\System\Payment;

class TradeService
{

    /**
     * 生成交易单单号
     * @return string
     */
    public static function getTradeNo(): string
    {
        return date('ymdHis', time()) . rand(100000, 999999);
    }


    /**
     * 生成退款交易单单号
     * @return string
     */
    public static function getRefundNo(): string
    {
        return date('ymdHis', time()) . rand(100000, 999999);
    }

    /**
     * 获取支付信息
     * @param int $m_id
     * @param int $type
     * @param string $order_no
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function getPayData(int $m_id, int $type, string $order_no)
    {
        $order_info = [];
        switch ($type) {
            case Trade::TYPE_ORDER:
                $order_info = self::checkOrderInfo($m_id, $order_no);
                break;
            case Trade::TYPE_RECHARGE:
                $order_info = self::checkRechargeInfo($m_id, $order_no);
                break;
        }
        if (!$order_info) {
            api_error(__('api.trade_order_info_error'));
        }
        //添加交易单
        $trade_data = [
            'm_id' => $m_id,
            'trade_no' => self::getTradeNo(),
            'order_no' => $order_info['order_no'],
            'type' => $type,
            'subtotal' => $order_info['subtotal'],
            'platform' => get_platform()
        ];
        $res = Trade::query()->create($trade_data);
        if (!$res) {
            api_error(__('api.trade_create_fail'));
        }
        return [
            'title' => $order_info['title'],
            'm_id' => $trade_data['m_id'],
            'type' => $trade_data['type'],
            'subtotal' => $trade_data['subtotal'],
            'trade_no' => $trade_data['trade_no'],
        ];
    }

    /**
     * 订单支付
     * @param int $m_id
     * @param string $order_no
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function checkOrderInfo(int $m_id, string $order_no)
    {
        $order_no = explode(',', $order_no);
        if (!$order_no) {
            api_error(__('api.order_error'));
        }
        //开始验证订单
        $order_list = Order::query()->select('status', 'subtotal')->where('m_id', $m_id)->whereIn('order_no', $order_no)->get();
        if ($order_list->isEmpty()) {
            api_error(__('api.order_error'));
        }
        $subtotal = 0;
        foreach ($order_list as $order) {
            //存在已经支付或取消的订单
            if ($order['status'] != Order::STATUS_WAIT_PAY) {
                api_error(__('api.order_pay_status_error'));
            }
            $subtotal += $order['subtotal'];
        }
        return [
            'title' => '订单支付',
            'order_no' => json_encode($order_no),
            'subtotal' => $subtotal,
        ];
    }

    /**
     * 余额充值
     * @param int $m_id
     * @param string $order_no
     * @return array
     * @throws \App\Exceptions\ApiError
     */
    public static function checkRechargeInfo(int $m_id, string $order_no)
    {
        //开始验证订单
        $recharge = BalanceRecharge::query()->where(['m_id' => $m_id, 'recharge_no' => $order_no])->first();
        if (!$recharge) {
            api_error(__('api.recharge_error'));
        } elseif ($recharge['status'] != BalanceRecharge::STATUS_OFF) {
            api_error(__('api.recharge_status_error'));
        }
        return [
            'title' => '充值',
            'order_no' => $order_no,
            'subtotal' => $recharge['amount'],
        ];
    }

    /**
     * 修改支付状态
     * @param array $notify_data
     * @return bool
     */
    public static function updatePayStatus(array $notify_data)
    {
        //查询交易单
        $trade = Trade::query()->where('trade_no', $notify_data['trade_no'])->first();
        if (!$trade) {
            return false;
        }
        if ($trade['status'] == Trade::STATUS_ON) {
            return true;//已经支付
        }
        //风险标示
        $flag = Trade::FLAG_YES;
        if ($notify_data['pay_total'] >= $trade['subtotal']) {
            $flag = Trade::FLAG_NO;
        }
        //修改交易单状态
        $update_trade = [
            'status' => Trade::STATUS_ON,
            'payment_id' => $notify_data['payment_id'],
            'payment_no' => $notify_data['payment_no'],
            'pay_total' => $notify_data['pay_total'],
            'payment_user' => $notify_data['payment_user'] ?? '',
            'flag' => $flag,
            'pay_at' => get_date()
        ];
        $res = Trade::query()->where('id', $trade['id'])->update($update_trade);
        if (!$res) {
            return false;
        }
        //支付成功后去修改订单和充值单状态
        $trade_data = [
            'trade_id' => $trade['id'],
            'flag' => $update_trade['flag'],
            'payment_id' => $notify_data['payment_id'],
            'payment_no' => $notify_data['payment_no'],
        ];
        switch ($trade['type']) {
            case Trade::TYPE_ORDER:
                //订单支付
                $trade_data['order_no'] = json_decode($trade['order_no'], true);
                $res_status = OrderService::updatePayOrder($trade_data);
                break;
            case Trade::TYPE_RECHARGE:
                //充值单
                $trade_data['order_no'] = $trade['order_no'];
                $res_status = BalanceRecharge::updatePayStatus($trade_data);
                break;
            default:
                $res_status = false;
                break;
        }
        if (!$res_status) {
            //如果对应的订单修改失败交易单改成异常，需要去验证是否退款
            Trade::query()->where('id', $trade['id'])->update(['status' => Trade::STATUS_ABNORMAL]);
        }
        return true;
    }

    /**
     * 退款申请
     * @param int $trade_id
     * @param string $order_no 订单号
     * @param float $amount 退款金额
     * @param int $type 类型
     * @param string|null $note 备注
     * @return string|bool|null
     */
    public static function tradeRefund(int $trade_id, string $order_no, float $amount, int $type, string|null $note = ''): bool|string|null
    {
        $trade = Trade::query()->find($trade_id);
        if (!$trade) {
            return __('admin.trade_error');
        } elseif ($trade['status'] != Trade::STATUS_ON) {
            return __('admin.trade_status_error');
        }
        $payment = Payment::query()->find($trade['payment_id']);
        if (!$payment) {
            return __('api.payment_error');
        }
        //退款交易单
        $refund_no = self::getRefundNo();
        $trade_refund = [
            'm_id' => $trade['m_id'],
            'refund_no' => $refund_no,
            'trade_no' => $trade['trade_no'],
            'order_no' => $order_no,
            'type' => $type,
            'subtotal' => $amount,
            'payment_id' => $trade['payment_id'],
            'payment_no' => $trade['payment_no'],
            'platform' => $trade['platform'],
            'status' => TradeRefund::STATUS_ON,
            'pay_at' => get_date()
        ];
        //退款信息
        $refund_info = [
            'm_id' => $trade['m_id'],
            'trade_no' => $trade['trade_no'],
            'payment_no' => $trade['payment_no'],
            'refund_no' => $refund_no,
            'pay_total' => $trade['pay_total'],
            'amount' => $amount,
            'trade_amount' => $trade['pay_total'],
            'note' => $note
        ];
        TradeRefund::query()->create($trade_refund);
        $class_name = '\App\Libs\Payment\\' . $payment['class_name'];
        $pay = new $class_name($trade['platform']);
        $res = $pay->refund($refund_info);
        if ($res === true) {
            return true;
        } else {
            TradeRefund::query()->where('refund_no', $refund_no)->update(['status' => TradeRefund::STATUS_FAIL, 'note' => $res]);//失败后修改退款单状态记录原因
            return __($res);
        }
    }
}

<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/5/14
 * Time: 3:47 PM
 */

namespace App\Libs\Payment;

use App\Models\Financial\BalanceDetail;
use App\Models\Member\Member;
use App\Models\System\Payment;

/**
 * 余额支付支付
 */
class Balance
{
    public string $platform = '';

    public function __construct($platform)
    {
        $this->platform = $platform;
    }

    /**
     * 获取支付信息
     * @param array $pay_info
     * @return array|string|void
     * @throws \App\Exceptions\ApiError
     */
    public function getPayData(array $pay_info)
    {
        Member::checkPayPassword($pay_info['m_id']);//判断支付密码
        //开始扣除余额
        $res_balance = \App\Models\Financial\Balance::updateAmount($pay_info['m_id'], -$pay_info['subtotal'], BalanceDetail::EVENT_ORDER_PAY, $pay_info['trade_no']);
        if ($res_balance['status']) {
            //支付成功修改交易单状态
            return [
                'trade_no' => $pay_info['trade_no'],
                'pay_total' => $pay_info['subtotal'],
                'payment_no' => $pay_info['trade_no'],
                'payment_id' => Payment::PAYMENT_BALANCE,
                'is_pay' => 0
            ];
        } else {
            return $res_balance['message'];
        }
    }

    /**
     * 退款申请
     * @param array $refund_info
     * @return bool|mixed
     */
    public function refund(array $refund_info)
    {
        //退款单号、退款金额
        $res_balance = \App\Models\Financial\Balance::updateAmount($refund_info['m_id'], $refund_info['amount'], BalanceDetail::EVENT_ORDER_REFUND, $refund_info['refund_no']);
        if ($res_balance['status']) {
            return true;
        } else {
            return $res_balance['message'];
        }
    }

}
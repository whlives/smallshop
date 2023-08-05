<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 11:09 AM
 */

namespace App\Models\Financial;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;

/**
 * 用户资金充值
 */
class BalanceRecharge extends BaseModel
{
    protected $table = 'balance_recharge';
    protected $guarded = ['id'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '未成功',
        self::STATUS_ON => '成功',
    ];

    //风险订单提示
    const FLAG_NO = 0;
    const FLAG_YES = 1;
    const FLAG_DESC = [
        self::FLAG_NO => '正常',
        self::FLAG_YES => '风险'
    ];

    /**
     * 生成充值单号
     * @return string
     */
    public static function getRechargeNo(): string
    {
        return date('ymdHis', time()) . rand(100000, 999999);
    }

    /**
     * 余额充值成功处理
     * @param $notify_data
     * @return bool
     */
    public static function updatePayStatus(array $notify_data)
    {
        $recharge_no = $notify_data['order_no'];
        $recharge = self::where('recharge_no', $recharge_no)->first();
        if (!$recharge) {
            return false;
        }
        if ($recharge['status'] == self::STATUS_OFF) {
            $recharge_update = [
                'trade_id' => $notify_data['trade_id'],
                'payment_id' => $notify_data['payment_id'],
                'payment_no' => $notify_data['payment_no'],
                'flag' => $notify_data['flag'],
                'status' => self::STATUS_ON,
                'pay_at' => get_date()
            ];
            try {
                DB::transaction(function () use ($notify_data, $recharge, $recharge_update) {
                    self::where(['recharge_no' => $notify_data['order_no'], 'status' => self::STATUS_OFF])->update($recharge_update);
                    if ($recharge_update['flag'] == self::FLAG_NO) {
                        Balance::updateAmount($recharge['m_id'], $recharge['amount'], BalanceDetail::EVENT_RECHARGE, $recharge['recharge_no']);
                    }
                });
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return true;
        }
    }

}

<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 11:09 AM
 */

namespace App\Models\Financial;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 用户资金明细
 */
class BalanceDetail extends BaseModel
{
    use SoftDeletes;

    protected $table = 'balance_detail';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    //类型
    const TYPE_INCR = 1;//增加
    const TYPE_RECR = 2;//减少

    const EVENT_SYSTEM_RECHARGE = 1;//系统充值
    const EVENT_SYSTEM_DEDUCT = 2;//系统扣除
    const EVENT_RECHARGE = 3;//充值
    const EVENT_WITHDRAW = 4;//提现
    const EVENT_WITHDRAW_REFUND = 5;//提现退款
    const EVENT_ORDER_PAY = 6;//订单支付
    const EVENT_ORDER_REFUND = 7;//订单退款
    const EVENT_RECOMMEND_ORDER = 8;//推荐订单提成
    const EVENT_POUNDAGE = 9;//手续费
    const EVENT_DESC = [
        self::EVENT_SYSTEM_RECHARGE => '系统充值',
        self::EVENT_SYSTEM_DEDUCT => '系统扣除',
        self::EVENT_RECHARGE => '充值',
        self::EVENT_WITHDRAW => '提现',
        self::EVENT_WITHDRAW_REFUND => '提现退款',
        self::EVENT_ORDER_PAY => '订单支付',
        self::EVENT_ORDER_REFUND => '订单退款',
        self::EVENT_RECOMMEND_ORDER => '推荐订单提成',
        self::EVENT_POUNDAGE => '手续费',
    ];

}

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
 * 用户积分明细
 */
class PointDetail extends BaseModel
{
    use SoftDeletes;

    protected $table = 'point_detail';
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
    const EVENT_ORDER_REWARD = 8;//订单活动额外奖励
    const EVENT_POUNDAGE = 9;//手续费
    const EVENT_SYSTEM_REWARD = 10;//系统奖励
    const EVENT_COUPONS_EXCHANGE = 11;//兑换优惠券
    const EVENT_GOODS_EXCHANGE = 12;//兑换商品
    const EVENT_DESC = [
        self::EVENT_SYSTEM_RECHARGE => '系统充值',
        self::EVENT_SYSTEM_DEDUCT => '系统扣除',
        self::EVENT_RECHARGE => '充值',
        self::EVENT_WITHDRAW => '提现',
        self::EVENT_WITHDRAW_REFUND => '提现退款',
        self::EVENT_ORDER_PAY => '订单支付',
        self::EVENT_ORDER_REFUND => '订单退款',
        self::EVENT_ORDER_REWARD => '订单活动额外奖励',
        self::EVENT_POUNDAGE => '手续费',
        self::EVENT_SYSTEM_REWARD => '系统奖励',
        self::EVENT_COUPONS_EXCHANGE => '兑换优惠券',
        self::EVENT_GOODS_EXCHANGE => '兑换商品',
    ];

}

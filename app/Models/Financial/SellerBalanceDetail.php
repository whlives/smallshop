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
 * 商家资金明细
 */
class SellerBalanceDetail extends BaseModel
{
    use SoftDeletes;

    //类型
    const TYPE_INCR = 1;//增加
    const TYPE_RECR = 2;//减少

    const EVENT_SYSTEM_RECHARGE = 1;//系统充值
    const EVENT_SYSTEM_DEDUCT = 2;//系统扣除
    const EVENT_ORDER = 3;//订单结算
    const EVENT_POUNDAGE = 4;//手续费
    const EVENT_WITHDRAW = 5;//提现
    const EVENT_WITHDRAW_REFUND = 6;//提现退款
    const EVENT_DESC = [
        self::EVENT_SYSTEM_RECHARGE => '系统充值',
        self::EVENT_SYSTEM_DEDUCT => '系统扣除',
        self::EVENT_ORDER => '订单结算',
        self::EVENT_POUNDAGE => '手续费',
        self::EVENT_WITHDRAW => '提现',
        self::EVENT_WITHDRAW_REFUND => '提现退款',
    ];

    protected $table = 'seller_balance_detail';
    protected $guarded = ['id'];
    
}
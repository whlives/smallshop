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
 * 退款单
 */
class TradeRefund extends BaseModel
{
    use SoftDeletes;

    protected $table = 'trade_refund';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_FAIL = 2;
    const STATUS_DESC = [
        self::STATUS_OFF => '待退款',
        self::STATUS_ON => '已退款',
        self::STATUS_FAIL => '失败',
    ];

    //类型
    const TYPE_TRADE = 1;
    const TYPE_ORDER = 2;
    const TYPE_REFUND = 3;
    const TYPE_RECHARGE = 4;
    const TYPE_DESC = [
        self::TYPE_TRADE => '交易单',
        self::TYPE_ORDER => '订单',
        self::TYPE_REFUND => '售后单',
        self::TYPE_RECHARGE => '充值'
    ];

}

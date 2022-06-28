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
 * 交易单
 */
class Trade extends BaseModel
{
    use SoftDeletes;

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_ABNORMAL = 2;
    const STATUS_REFUND = 3;
    const STATUS_DESC = [
        self::STATUS_OFF => '待支付',
        self::STATUS_ON => '已支付',
        self::STATUS_ABNORMAL => '异常',
        self::STATUS_REFUND => '已退回'
    ];

    //类型
    const TYPE_ORDER = 1;
    const TYPE_RECHARGE = 2;
    const TYPE_DESC = [
        self::TYPE_ORDER => '订单',
        self::TYPE_RECHARGE => '充值'
    ];

    //风险订单提示
    const FLAG_NO = 0;
    const FLAG_YES = 1;
    const FLAG_DESC = [
        self::FLAG_NO => '正常',
        self::FLAG_YES => '风险'
    ];

    protected $table = 'trade';
    protected $guarded = ['id'];

}
<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/24
 * Time: 11:09 AM
 */

namespace App\Models\Financial;

use App\Models\BaseModel;

/**
 * 商家提现
 */
class SellerWithdraw extends BaseModel
{
    protected $table = 'seller_withdraw';
    protected $guarded = ['id'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_REFUND = 2;
    const STATUS_DEDUCT = 3;
    const STATUS_DESC = [
        self::STATUS_OFF => '待审核',
        self::STATUS_ON => '已经审核',
        self::STATUS_REFUND => '拒绝并退还资金',
        self::STATUS_DEDUCT => '拒绝不退还资金'
    ];
    //会员显示状态
    const STATUS_MEMBER_DESC = [
        self::STATUS_OFF => '待审核',
        self::STATUS_ON => '提现成功',
        self::STATUS_REFUND => '提现失败',
        self::STATUS_DEDUCT => '提现失败'
    ];

    //提现方式
    const TYPE_BANK = 1;
    const TYPE_ALIPAY = 2;
    const TYPE_WECHAR = 3;
    const TYPE_DESC = [
        self::TYPE_BANK => '银行',
        self::TYPE_ALIPAY => '支付宝',
        self::TYPE_WECHAR => '微信',
    ];

    /**
     * 生成提现单号
     * @return string
     */
    public static function getWithdrawNo(): string
    {
        return date('ymdHis', time()) . rand(100000, 999999);
    }
}

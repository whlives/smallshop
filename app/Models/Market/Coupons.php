<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:46 PM
 */

namespace App\Models\Market;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 优惠券
 */
class Coupons extends BaseModel
{
    use SoftDeletes;

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //活动类型
    const TYPE_REDUCTION = 1;//满减
    const TYPE_DISCOUNT = 2;//折扣优惠
    const TYPE_DESC = [
        self::TYPE_REDUCTION => '满减',
        self::TYPE_DISCOUNT => '折扣优惠',
    ];

    //可否购买
    const IS_BUY_OFF = 0;//否
    const IS_BUY_ON = 1;//是
    const IS_BUY_DESC = [
        self::IS_BUY_OFF => '否',
        self::IS_BUY_ON => '是',
    ];

    //开放领取
    const OPEN_OFF = 0;
    const OPEN_ON = 1;
    const OPEN_DESC = [
        self::OPEN_OFF => '否',
        self::OPEN_ON => '是'
    ];

    protected $table = 'coupons';
    protected $guarded = ['id'];
    protected $hidden = ['deleted_at'];

    protected $dates = ['deleted_at'];
}
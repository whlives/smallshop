<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:33 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 运费模板
 */
class Delivery extends BaseModel
{
    use SoftDeletes;

    protected $table = 'delivery';
    protected $guarded = ['id'];
    protected $dates = ['deleted_at'];

    //状态
    const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_OFF => '锁定',
        self::STATUS_ON => '正常',
    ];

    //计算类型
    const TYPE_WEIGHT = 0;//按重量
    const TYPE_NUMBER = 1;//按件数
    const TYPE_DESC = [
        self::TYPE_WEIGHT => '按重量',
        self::TYPE_NUMBER => '按件数'
    ];

    //其他地区是否使用默认运费
    const OPEN_DEFAULT_OFF = 0;
    const OPEN_DEFAULT_ON = 1;
    const OPEN_DEFAULT_DESC = [
        self::OPEN_DEFAULT_OFF => '不启用',
        self::OPEN_DEFAULT_ON => '启用',
    ];

    //费用类型
    const PRICE_TYPE_UNIFIED = 0;
    const PRICE_TYPE_SPECIFY_AREA = 1;
    const PRICE_TYPE_DESC = [
        self::PRICE_TYPE_UNIFIED => '全国',
        self::PRICE_TYPE_SPECIFY_AREA => '指定地区'
    ];

    //满包邮条件类型
    const FREE_TYPE_MONEY = 0;
    const FREE_TYPE_NUMBER = 1;
    const FREE_TYPE_DESC = [
        self::FREE_TYPE_MONEY => '按金额',
        self::FREE_TYPE_NUMBER => '按件数'
    ];

}

<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/3
 * Time: 4:46 PM
 */

namespace App\Models\Market;

use App\Models\BaseModel;

/**
 * 优惠券规则
 */
class CouponsRule extends BaseModel
{
    //类型
    const TYPE_GOODS = 1;//商品
    const TYPE_BRAND = 2;//品牌
    const TYPE_CATEGORY = 3;//分类
    const TYPE_DESC = [
        self::TYPE_GOODS => '商品',
        self::TYPE_BRAND => '品牌',
        self::TYPE_CATEGORY => '分类',
    ];
    //对应的字段名称
    const TYPE_FIELD_NAME = [
        self::TYPE_GOODS => 'goods_id',
        self::TYPE_BRAND => 'brand_id',
        self::TYPE_CATEGORY => 'category_id',
    ];

    //条件类型
    const IN_TYPE_IN = 1;//包含
    const IN_TYPE_OUT = 2;//排除
    const IN_TYPE_DESC = [
        self::IN_TYPE_IN => '包含',
        self::IN_TYPE_OUT => '排除',
    ];

    protected $table = 'coupons_rule';
    protected $guarded = ['id'];
}
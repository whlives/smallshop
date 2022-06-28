<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/3/30
 * Time: 9:33 PM
 */

namespace App\Models\Goods;

use App\Models\BaseModel;

/**
 * 商品sku
 */
class GoodsSku extends BaseModel
{

    //状态
    const STATUS_DEL = 99;
    const STATUS_ON = 1;
    const STATUS_DESC = [
        self::STATUS_DEL => '删除',
        self::STATUS_ON => '正常',
    ];

    protected $table = 'goods_sku';
    protected $guarded = ['id'];

    public $timestamps = false;

}

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
 * 优惠券商品
 */
class GoodsCoupons extends BaseModel
{

    protected $table = 'goods_coupons';
    protected $guarded = ['id'];

    public $timestamps = false;

}

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
 * 商品属性信息
 */
class GoodsAttribute extends BaseModel
{

    protected $table = 'goods_attribute';
    protected $guarded = ['id'];

}

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
 * 商家分类
 */
class GoodsSellerCategory extends BaseModel
{
    protected $table = 'goods_seller_category';
    protected $guarded = ['id'];

    public $timestamps = false;

}

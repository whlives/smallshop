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
 * 商品图片
 */
class GoodsImage extends BaseModel
{

    protected $table = 'goods_image';
    protected $guarded = ['id'];

    public $timestamps = false;

}

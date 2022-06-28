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
 * 商品详情
 */
class GoodsContent extends BaseModel
{

    protected $table = 'goods_content';
    protected $guarded = ['id'];

    public $timestamps = false;

}

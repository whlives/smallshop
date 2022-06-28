<?php
/**
 * Created by PhpStorm
 * User: whlives
 * Date: 2022/4/14
 * Time: 2:45 PM
 */

namespace App\Models\Order;

use App\Models\BaseModel;

/**
 * 售后图片
 */
class RefundImage extends BaseModel
{

    protected $table = 'refund_image';
    protected $guarded = ['id'];

    public $timestamps = false;

}
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
 * 订单物流
 */
class OrderDelivery extends BaseModel
{
    protected $table = 'order_delivery';
    protected $guarded = ['id'];

}
